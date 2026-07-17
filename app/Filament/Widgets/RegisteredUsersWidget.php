<?php

namespace App\Filament\Widgets;

use App\Enums\AnonymizationActionType;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\AnonymizationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RegisteredUsersWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::where('role', 'customer')
                    ->whereNull('anonymized_at')
                    ->withCount(['orders as completed_orders_count' => fn ($q) => $q->where('status', 'completed')]),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->badge()
                    ->state(fn ($record): string => $record->email_verified_at ? 'Verified' : 'Unverify')
                    ->color(fn ($state): string => $state === 'Verified' ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable(),

                TextColumn::make('completed_orders_count')
                    ->label('Orders')
                    ->sortable(),
            ])
            ->heading('Registered Users')
            ->emptyStateHeading('All users have been anonymized')
            ->paginated([5, 10, 25, 50])
            ->paginationMode(PaginationMode::Default)
            ->actions([
                Action::make('anonymize')
                    ->label('Anonymize')
                    ->icon('heroicon-o-shield-check')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Anonymize User Data')
                    ->modalDescription('User identity data will be permanently masked. Transaction data remains intact. This cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Anonymize')
                    ->action(function (User $record): void {
                        $service = app(AnonymizationService::class);

                        if (! $service->canAnonymizeUser($record, 0, skipRetentionCheck: true)) {
                            $activeOrders = $record->orders()
                                ->whereIn('status', AnonymizationService::ACTIVE_STATUSES)
                                ->pluck('order_number')
                                ->toArray();

                            $body = $activeOrders
                                ? 'User has active orders: '.implode(', ', $activeOrders)
                                : 'User cannot be anonymized. They may already be anonymized.';

                            Notification::make()
                                ->title('Cannot anonymize')
                                ->body($body)
                                ->danger()
                                ->send();

                            return;
                        }

                        $service->anonymizeUser($record, AnonymizationActionType::ForgottenManual);

                        Notification::make()
                            ->title('Success')
                            ->body('User data has been anonymized.')
                            ->success()
                            ->send();

                        $this->redirect(UserResource::getUrl('index'));
                    }),
            ]);
    }
}
