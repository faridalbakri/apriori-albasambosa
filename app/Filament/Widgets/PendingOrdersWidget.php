<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Order;
use App\Services\MidtransService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Log;

class PendingOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::where('status', OrderStatus::Pending->value)
                    ->where('created_at', '<', now()->subMinutes(30))
                    ->orderBy('created_at'),
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),

                TextColumn::make('recipient_name')
                    ->label('Customer'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i'),
            ])
            ->recordUrl(
                fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]),
            )
            ->heading('Pending Orders > 30 Minutes')
            ->emptyStateHeading('No pending orders overdue')
            ->emptyStateDescription('All pending orders are within normal timeframe.')
            ->paginated(false)
            ->headerActions([
                Action::make('syncAllPending')
                    ->label('Sync All')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Sync All Pending Orders')
                    ->modalDescription('Check payment status with Midtrans for all pending orders > 30 minutes and update their status.')
                    ->modalSubmitActionLabel('Yes, Sync All')
                    ->action(function () {
                        $orders = Order::where('status', OrderStatus::Pending->value)
                            ->where('created_at', '<', now()->subMinutes(30))
                            ->get();

                        $synced = 0;

                        foreach ($orders as $order) {
                            $status = MidtransService::getOrderStatus($order->order_number);

                            if ($status && isset($status->transaction_status)) {
                                ProcessMidtransWebhook::dispatch(
                                    $order->order_number,
                                    $status->transaction_status,
                                    $status->fraud_status ?? 'accept',
                                );
                                $synced++;
                            } else {
                                Log::info('Sync All Midtrans: no status returned', [
                                    'order_number' => $order->order_number,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title($synced > 0 ? "{$synced} orders queued for sync" : 'Nothing to sync')
                            ->body($synced > 0 ? 'Status will be updated shortly.' : 'Pending orders have no payment status from Midtrans yet.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
