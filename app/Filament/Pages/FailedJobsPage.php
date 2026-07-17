<?php

namespace App\Filament\Pages;

use App\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class FailedJobsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 110;

    protected string $view = 'filament.pages.failed-jobs';

    public function getTitle(): string
    {
        return 'Failed Jobs';
    }

    public static function getNavigationLabel(): string
    {
        return 'Failed Jobs';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query()->orderBy('failed_at', 'desc'))
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->extraAttributes(['class' => 'font-mono text-xs']),

                TextColumn::make('failed_at')
                    ->label('Failed At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('exception')
                    ->label('Exception')
                    ->limit(80)
                    ->searchable()
                    ->extraAttributes(['class' => 'text-xs text-red-600']),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $uuid = $record->uuid;
                        if (! is_string($uuid) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
                            return;
                        }

                        try {
                            $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Retry failed')
                                ->body('Job payload is no longer valid. Delete this entry and let new jobs process.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($exitCode !== 0) {
                            Notification::make()
                                ->title('Retry failed.')
                                ->danger()
                                ->send();

                            return;
                        }
                    })
                    ->successNotificationTitle('Job retried successfully.'),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Failed Job')
                    ->modalDescription('Permanently delete from list. Job will not be retried.')
                    ->action(fn ($record) => $record->delete())
                    ->successNotificationTitle('Job deleted.'),
            ])
            ->emptyStateHeading('No failed jobs 🎉');
    }
}
