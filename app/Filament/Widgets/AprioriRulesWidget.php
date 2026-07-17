<?php

namespace App\Filament\Widgets;

use App\Models\AprioriRule;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

class AprioriRulesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public int|float $minSupport = 2;

    public int|float $minConfidence = 60;

    public int $minTransactions = 50;

    public function mount(): void
    {
        $this->minSupport = (int) (config('apriori.min_support', 0.02) * 100);
        $this->minConfidence = (int) (config('apriori.min_confidence', 0.6) * 100);
        $this->minTransactions = (int) config('apriori.min_transactions', 50);
    }

    public function table(Table $table): Table
    {
        $nameMap = $this->productNameMap();

        return $table
            ->query(AprioriRule::query())
            ->heading($this->headingText())
            ->headerActions([
                $this->settingsAction(),
                $this->generateAction(),
            ])
            ->columns([
                TextColumn::make('antecedent')
                    ->label('If Bought')
                    ->formatStateUsing(fn ($state) => $this->resolveNames($state, $nameMap))
                    ->searchable(),

                TextColumn::make('consequent')
                    ->label('Then Buy')
                    ->formatStateUsing(fn ($state) => $this->resolveNames($state, $nameMap))
                    ->searchable(),

                TextColumn::make('support')
                    ->label('Support')
                    ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 1).'%')
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 1).'%')
                    ->sortable(),

                TextColumn::make('lift')
                    ->label('Lift')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2))
                    ->sortable()
                    ->color(fn ($state) => (float) $state > 1 ? 'success' : 'danger'),
            ])
            ->defaultSort('lift', 'desc')
            ->filters([
                Filter::make('min_confidence')
                    ->label('Confidence ≥ 80%')
                    ->query(fn (Builder $query) => $query->where('confidence', '>=', 0.8)),

                Filter::make('min_lift')
                    ->label('Lift > 1')
                    ->query(fn (Builder $query) => $query->where('lift', '>', 1)),
            ])
            ->paginated([5, 10, 25, 50])
            ->paginationMode(PaginationMode::Default);
    }

    private function headingText(): string
    {
        $count = AprioriRule::count();

        return "Apriori Rules · {$count} rules · Support {$this->minSupport}% · Confidence {$this->minConfidence}% · Min {$this->minTransactions} transactions";
    }

    private function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Settings')
            ->color('gray')
            ->icon('heroicon-o-cog-6-tooth')
            ->form([
                TextInput::make('min_support')
                    ->label('Min Support (%)')
                    ->numeric()
                    ->minValue(0.1)
                    ->maxValue(100)
                    ->default($this->minSupport),
                TextInput::make('min_confidence')
                    ->label('Min Confidence (%)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default($this->minConfidence),
                TextInput::make('min_transactions')
                    ->label('Min Transactions')
                    ->numeric()
                    ->minValue(1)
                    ->default($this->minTransactions),
            ])
            ->action(function (array $data): void {
                $this->updateEnv([
                    'APRIORI_MIN_SUPPORT' => (float) $data['min_support'] / 100,
                    'APRIORI_MIN_CONFIDENCE' => (float) $data['min_confidence'] / 100,
                    'APRIORI_MIN_TRANSACTIONS' => (int) $data['min_transactions'],
                ]);

                Artisan::call('config:clear');

                $this->minSupport = $data['min_support'];
                $this->minConfidence = $data['min_confidence'];
                $this->minTransactions = $data['min_transactions'];

                Notification::make()
                    ->title('Settings saved!')
                    ->success()
                    ->send();
            });
    }

    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label('Generate Rules')
            ->color('primary')
            ->icon('heroicon-o-play')
            ->action(function (): void {
                $support = $this->minSupport / 100;
                $confidence = $this->minConfidence / 100;

                config(['apriori.min_transactions' => 1]);

                Artisan::call('apriori:mine', [
                    '--minsupport' => $support,
                    '--minconfidence' => $confidence,
                    '--force' => true,
                ]);

                $count = AprioriRule::count();

                if ($count === 0) {
                    Notification::make()
                        ->title('No rules found.')
                        ->body('Threshold too high. Try lowering Support or Confidence.')
                        ->warning()
                        ->send();
                } else {
                    Notification::make()
                        ->title("{$count} rules found!")
                        ->body('Table below has been updated.')
                        ->success()
                        ->send();
                }

                $this->redirect(url('/admin/apriori'));
            });
    }

    private function updateEnv(array $values): void
    {
        $path = base_path('.env');
        $content = file_get_contents($path);

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*$/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= "\n{$replacement}";
            }
        }

        file_put_contents($path, $content);
    }

    private function productNameMap(): array
    {
        $allIds = AprioriRule::all()
            ->flatMap(fn (AprioriRule $r) => array_merge($r->antecedent ?? [], $r->consequent ?? []))
            ->unique()
            ->values()
            ->toArray();

        return Product::whereIn('id', $allIds)->pluck('name', 'id')->toArray();
    }

    private function resolveNames($ids, array $nameMap): string
    {
        if (empty($ids)) {
            return '-';
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return implode(' + ', array_map(
            fn ($id) => $nameMap[(int) $id] ?? "Product #{$id}",
            $ids
        ));
    }
}
