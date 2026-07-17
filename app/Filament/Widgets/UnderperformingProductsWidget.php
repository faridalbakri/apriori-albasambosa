<?php

namespace App\Filament\Widgets;

use App\Models\AprioriRule;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class UnderperformingProductsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Pre-computed stats from Apriori rules, keyed by product ID.
     *
     * @var array<int, array{name: string, avg_lift: float, best_pair: string}>
     */
    protected array $stats = [];

    public function mount(): void
    {
        $this->stats = Cache::remember('apriori:underperforming', 3600, fn () => $this->computeStats());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable(),

                TextColumn::make('total_sold')
                    ->label('Sold'),

                TextColumn::make('best_pair')
                    ->label('Often Bought Together')
                    ->state(fn (Product $record): string => $this->stats[$record->id]['best_pair'] ?? '-'),

                TextColumn::make('avg_lift')
                    ->label('Avg Lift')
                    ->state(fn (Product $record): string => isset($this->stats[$record->id])
                        ? number_format($this->stats[$record->id]['avg_lift'], 2)
                        : '-'),
            ])
            ->defaultSort('total_sold', 'asc')
            ->heading('Underperforming Products — Bundling Potential')
            ->paginated([5, 10, 25, 50])
            ->paginationMode(PaginationMode::Default);
    }

    private function getQuery(): Builder
    {
        $productIds = array_keys($this->stats);

        return Product::whereIn('id', $productIds)
            ->where('stock', '>', 0);
    }

    /**
     * Find products that appear as consequent in rules with lift > 1
     * and compute avg lift + best antecedent pair.
     *
     * Rules store product IDs (H3 fix), so we resolve names from DB.
     */
    private function computeStats(): array
    {
        $rules = AprioriRule::where('lift', '>', 1)->get();

        if ($rules->isEmpty()) {
            return [];
        }

        // Build product ID → name map
        $productIds = collect();
        foreach ($rules as $rule) {
            $productIds = $productIds->concat($rule->antecedent ?? [])->concat($rule->consequent ?? []);
        }
        $productNameMap = Product::whereIn('id', $productIds->unique()->values())->pluck('name', 'id')->toArray();

        $stats = [];

        foreach ($rules as $rule) {
            $antecedentIds = $rule->antecedent ?? [];
            $consequentIds = $rule->consequent ?? [];

            // Build antecedent display name
            $antecedentNames = array_map(fn ($id) => $productNameMap[(int) $id] ?? "Product #{$id}", $antecedentIds);
            $antecedentDisplay = implode(' + ', $antecedentNames);

            foreach ($consequentIds as $prodId) {
                $prodId = (int) $prodId;

                if (! isset($stats[$prodId])) {
                    $stats[$prodId] = [
                        'name' => $productNameMap[$prodId] ?? "Product #{$prodId}",
                        'lift_sum' => 0,
                        'lift_count' => 0,
                        'best_pair' => null,
                        'best_pair_support' => 0,
                    ];
                }

                $stats[$prodId]['lift_sum'] += $rule->lift;
                $stats[$prodId]['lift_count']++;

                if ($rule->support > $stats[$prodId]['best_pair_support']) {
                    $stats[$prodId]['best_pair_support'] = $rule->support;
                    $stats[$prodId]['best_pair'] = $antecedentDisplay;
                }
            }
        }

        // Compute avg lift
        foreach ($stats as $id => &$data) {
            $data['avg_lift'] = $data['lift_count'] > 0
                ? $data['lift_sum'] / $data['lift_count']
                : 0;
        }
        unset($data);

        return $stats;
    }
}
