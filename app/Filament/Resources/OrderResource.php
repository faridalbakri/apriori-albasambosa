<?php

namespace App\Filament\Resources;

use App\Actions\TransitionOrderStatus;
use App\Contracts\DeliveryService;
use App\Enums\AnonymizationActionType;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\OrderResource\Pages;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Order;
use App\Services\AnonymizationService;
use App\Services\MidtransService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Order';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Orders';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('recipient_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('method')
                    ->label('Method')
                    ->state(fn (Order $record): string => $record->pickup_time ? 'Pickup' : 'Delivery')
                    ->badge()
                    ->color(fn (Order $record): string => $record->pickup_time ? 'success' : 'info')
                    ->icon(fn (Order $record): string => $record->pickup_time ? 'heroicon-o-shopping-bag' : 'heroicon-o-truck')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('pickup_time', $direction)),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => self::formatStatusLabel($state))
                    ->color(fn (OrderStatus $state): string => match ($state->value) {
                        'pending' => 'warning',
                        'settlement' => 'success',
                        'processing' => 'info',
                        'ready_pickup' => 'info',
                        'shipping' => 'primary',
                        'delivered' => 'success',
                        'completed' => 'success',
                        'expire', 'cancel', 'failed' => 'danger',
                        'refund_pending', 'refund_done' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('total_price')
                    ->label('Subtotal')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(
                fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]),
            )
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(
                        fn ($s) => [$s->value => self::formatStatusLabel($s)],
                    )),
                Filter::make('created_at')
                    ->label('Date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('From')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('date_to')
                            ->label('To')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->closeOnDateSelection(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['date_to'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                ExportAction::make()->exporter(OrderExporter::class)->label('Export All')->columnMappingColumns(3),
            ])
            ->actions([
                ViewAction::make(),
                // Primary: next workflow step (only 1 shown at a time via visible())
                self::makeTransitionAction('processing'),
                self::makeTransitionAction('ready_pickup'),
                self::makeTransitionAction('shipping'),
                self::makeTransitionAction('delivered'),
                self::makeTransitionAction('completed'),
                // Secondary: destructive + rare actions in dropdown
                ActionGroup::make([
                    self::makeTransitionAction('cancel'),
                    self::makeTransitionAction('failed'),
                    self::makeTransitionAction('refund_pending'),
                    self::makeTransitionAction('refund_done'),
                    Action::make('syncMidtrans')
                        ->label('Sync Midtrans')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (Order $record): bool => $record->status->value === 'pending')
                        ->requiresConfirmation()
                        ->modalHeading('Sync Midtrans')
                        ->modalDescription('Check payment status with Midtrans for this order.')
                        ->modalSubmitActionLabel('Yes, Sync')
                        ->action(function (Order $record): void {
                            $status = MidtransService::getOrderStatus($record->order_number);
                            if ($status && isset($status->transaction_status)) {
                                ProcessMidtransWebhook::dispatch(
                                    $record->order_number,
                                    $status->transaction_status,
                                    $status->fraud_status ?? 'accept',
                                );
                            }
                        })
                        ->successNotificationTitle('Midtrans sync scheduled.'),
                    Action::make('anonymizeUser')
                        ->label('Anonymize')
                        ->icon('heroicon-o-shield-check')
                        ->color('danger')
                        ->authorize(fn () => auth()->user()?->role === 'admin')
                        ->visible(function (Order $record): bool {
                            if ($record->user_id === null) {
                                return false;
                            }

                            $user = $record->user;

                            return $user && $user->anonymized_at === null;
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Anonymize Customer Data')
                        ->modalDescription('Customer identity data will be permanently masked. Transaction data remains intact. This cannot be undone.')
                        ->modalSubmitActionLabel('Yes, Anonymize')
                        ->action(function (Order $record): void {
                            $user = $record->user;

                            if (! $user || $user->anonymized_at !== null) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('User already anonymized or not found.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $service = app(AnonymizationService::class);

                            if (! $service->canAnonymizeUser($user, 0, skipRetentionCheck: true)) {
                                $activeOrders = $user->orders()
                                    ->whereIn('status', AnonymizationService::ACTIVE_STATUSES)
                                    ->pluck('order_number')
                                    ->toArray();

                                if ($activeOrders) {
                                    $body = 'User has active orders: '.implode(', ', $activeOrders);
                                } else {
                                    $body = 'User cannot be anonymized. Check they haven\'t been anonymized already.';
                                }

                                Notification::make()
                                    ->title('Cannot anonymize')
                                    ->body($body)
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $service->anonymizeUser(
                                $user,
                                AnonymizationActionType::ForgottenManual,
                            );

                            Notification::make()
                                ->title('Success')
                                ->body('Customer data has been anonymized.')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                ExportBulkAction::make()->exporter(OrderExporter::class)->label('Export Selected')->columnMappingColumns(3),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Order Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order_number')->label('Order #'),
                                TextEntry::make('pickup_time')
                                    ->label('Method')
                                    ->state(fn (Order $record): string => $record->pickup_time ? 'Pickup' : 'Delivery')
                                    ->badge()
                                    ->color(fn (Order $record): string => $record->pickup_time ? 'success' : 'info')
                                    ->icon(fn (Order $record): string => $record->pickup_time ? 'heroicon-o-shopping-bag' : 'heroicon-o-truck'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (OrderStatus $state): string => self::formatStatusLabel($state))
                                    ->color(fn (OrderStatus $state): string => match ($state->value) {
                                        'pending' => 'warning',
                                        'settlement' => 'success',
                                        'processing' => 'info',
                                        'ready_pickup' => 'info',
                                        'shipping' => 'primary',
                                        'delivered' => 'success',
                                        'completed' => 'success',
                                        'expire', 'cancel', 'failed' => 'danger',
                                        'refund_pending', 'refund_done' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('created_at')->label('Date')->dateTime('d M Y H:i'),
                                TextEntry::make('recipient_name')->label('Customer'),
                                TextEntry::make('phone')->label('Phone'),
                                TextEntry::make('payment_method')
                                    ->label('Payment')
                                    ->formatStateUsing(fn (?string $state): string => self::paymentMethodLabel($state)),
                                TextEntry::make('pickup_time')
                                    ->label('Pickup Time')
                                    ->html()
                                    ->formatStateUsing(fn ($state): string => Carbon::parse($state)->format('d M Y').'<br>'.Carbon::parse($state)->format('H:i').' - '.Carbon::parse($state)->addHour()->format('H:i'))
                                    ->visible(fn (Order $record): bool => $record->pickup_time !== null),
                                TextEntry::make('address_detail')
                                    ->label('Address')
                                    ->visible(fn (Order $record): bool => $record->pickup_time === null),
                            ]),
                    ]),

                Section::make('Order Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('product.name')->label('Product'),
                                        TextEntry::make('price')->label('Price')->money('IDR'),
                                        TextEntry::make('quantity')->label('Qty'),
                                        TextEntry::make('subtotal')
                                            ->label('Subtotal')
                                            ->money('IDR')
                                            ->state(fn ($record) => $record->price * $record->quantity),
                                    ]),
                            ]),
                        TextEntry::make('totals_summary')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Order $record): string {
                                $currency = fn ($value): string => 'Rp '.number_format((float) $value, 0, ',', '.');

                                $html = '<div style="text-align:right;">';

                                // Subtotal
                                $html .= '<div style="display:flex;justify-content:flex-end;gap:16px;align-items:baseline;padding:2px 0;">';
                                $html .= '<span style="color:#6b7280;font-size:0.875rem;">Subtotal</span>';
                                $html .= '<span style="font-weight:500;">'.$currency($record->total_price).'</span>';
                                $html .= '</div>';

                                // Shipping (delivery only)
                                if ($record->pickup_time === null) {
                                    $html .= '<div style="display:flex;justify-content:flex-end;gap:16px;align-items:baseline;padding:2px 0;">';
                                    $html .= '<span style="color:#6b7280;font-size:0.875rem;">Shipping</span>';
                                    $html .= '<span style="font-weight:500;">'.$currency($record->shipping_cost ?? 0).'</span>';
                                    $html .= '</div>';

                                    // Grand total
                                    $grandTotal = (float) $record->total_price + (float) ($record->shipping_cost ?? 0);
                                    $html .= '<div style="display:flex;justify-content:flex-end;gap:16px;align-items:baseline;padding:4px 0 0 0;border-top:1px solid var(--color-border, #e5e7eb);margin-top:4px;">';
                                    $html .= '<span style="color:#374151;font-size:0.875rem;font-weight:600;">Total</span>';
                                    $html .= '<span style="font-weight:700;">'.$currency($grandTotal).'</span>';
                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return $html;
                            }),
                    ]),

                Section::make('Status History')
                    ->schema([
                        ViewEntry::make('statusLogs')
                            ->label('')
                            ->view('filament.infolists.entries.status-timeline'),
                    ]),

                Section::make('Delivery')
                    ->visible(fn (Order $record): bool => $record->pickup_time === null)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('shipment_kurir')
                                    ->label('Courier')
                                    ->state(function (Order $record): string {
                                        if ($record->shipment) {
                                            $label = $record->shipment->courier.' • '.$record->shipment->courier_service;

                                            if ($record->shipment->tracking_status) {
                                                $label .= ' • '.self::trackingLabel($record->shipment->tracking_status);
                                            }

                                            return $label;
                                        }

                                        return match ($record->status->value) {
                                            'pending' => 'Awaiting payment',
                                            'expire' => 'Payment expired',
                                            'failed' => 'Payment failed',
                                            'settlement' => 'Awaiting processing',
                                            'processing' => 'Preparing',
                                            'ready_pickup' => 'Ready for pickup',
                                            'cancel' => 'Cancelled',
                                            'refund_pending' => 'Refund pending',
                                            'refund_done' => 'Refund completed',
                                            default => '-',
                                        };
                                    })
                                    ->badge()
                                    ->color(fn (Order $record): string => $record->shipment ? 'primary' : 'gray'),
                                TextEntry::make('shipment.waybill_id')
                                    ->label('Waybill ID')
                                    ->placeholder('Not set')
                                    ->visible(fn (Order $record): bool => $record->shipment !== null),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    /**
     * Build a transition action button for a target status.
     * Only shown when the order's current status allows the transition.
     */
    private static function makeTransitionAction(string $targetStatus): Action
    {
        $label = match ($targetStatus) {
            'processing' => 'Process',
            'ready_pickup' => 'Ready for Pickup',
            'shipping' => 'Ship',
            'delivered' => 'Delivered',
            'completed' => 'Complete',
            'cancel' => 'Cancel',
            'failed' => 'Fail',
            'refund_pending' => 'Refund',
            'refund_done' => 'Refund Done',
            default => $targetStatus,
        };

        $icon = match ($targetStatus) {
            'processing' => 'heroicon-o-cog',
            'ready_pickup' => 'heroicon-o-check-circle',
            'shipping' => 'heroicon-o-truck',
            'delivered' => 'heroicon-o-map-pin',
            'completed' => 'heroicon-o-check-badge',
            'cancel' => 'heroicon-o-x-circle',
            'failed' => 'heroicon-o-exclamation-circle',
            'refund_pending' => 'heroicon-o-arrow-uturn-left',
            'refund_done' => 'heroicon-o-check',
            default => 'heroicon-o-arrow-right',
        };

        $color = match ($targetStatus) {
            'cancel', 'failed' => 'danger',
            'refund_pending', 'refund_done' => 'warning',
            'completed' => 'success',
            default => 'primary',
        };

        return Action::make('transitionTo'.ucfirst($targetStatus))
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(function (Order $record) use ($targetStatus): bool {
                $allowed = TransitionOrderStatus::VALID_TRANSITIONS[$record->status->value] ?? [];

                if (! in_array($targetStatus, $allowed, true)) {
                    return false;
                }

                // Filter by shipping method
                $isPickup = $record->pickup_time !== null;

                return match ($targetStatus) {
                    'ready_pickup' => $isPickup,        // pickup orders only
                    'shipping' => ! $isPickup,          // delivery orders only
                    'cancel', 'failed' => false,         // only via webhook/scheduler
                    'refund_pending' => $record->statusLogs
                        ->contains(fn ($log) => $log->new_status === 'settlement'),
                    default => true,
                };
            })
            ->requiresConfirmation()
            ->modalHeading(fn (): string => "{$label} Order")
            ->modalDescription(function (Order $record) use ($targetStatus, $label): string {
                $warning = in_array($targetStatus, ['completed', 'refund_done'], true)
                    ? ' This action CANNOT be undone.'
                    : '';

                $stockNote = in_array($targetStatus, ['cancel', 'failed'], true)
                    ? ' Stock will be restored.'
                    : '';

                return "Update order {$record->order_number} to \"{$label}\".{$warning}{$stockNote}";
            })
            ->modalSubmitActionLabel(fn (): string => "Yes, {$label}")
            ->modalIcon(fn () => in_array($targetStatus, ['completed', 'refund_done'], true) ? 'heroicon-o-exclamation-triangle' : null)
            ->action(function (Order $record) use ($targetStatus): void {
                $actor = auth()->user();
                $newStatus = OrderStatus::from($targetStatus);

                try {
                    app(TransitionOrderStatus::class)($record, $newStatus, $actor);

                    // Auto-create Biteship delivery order on shipping transition
                    if ($targetStatus === 'shipping') {
                        self::createBiteshipOrder($record);
                    }
                } catch (InvalidStatusTransitionException $e) {
                    Notification::make()
                        ->title('Transition failed')
                        ->body('Order status has changed. Please refresh.')
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * Create a Biteship delivery order for a shipment.
     * Called when admin transitions an order to "shipping" status.
     */
    private static function createBiteshipOrder(Order $order): void
    {
        $shipment = $order->shipment;

        if (! $shipment || ! $shipment->courier) {
            Log::info('Biteship: no shipment or courier set, skipping auto-create', [
                'order_id' => $order->id,
            ]);

            return;
        }

        if ($shipment->waybill_id) {
            // Already has a waybill — Biteship order already created
            return;
        }

        // test isolation — skip real API calls in mock mode
        if (config('services.biteship.mock')) {
            $shipment->update(['waybill_id' => 'mock-waybill-'.$order->id]);

            return;
        }

        try {
            /** @var DeliveryService $biteship */
            $biteship = app(DeliveryService::class);

            $order->loadMissing('items.product');

            $items = $order->items->map(fn ($item) => [
                'name' => $item->product->name,
                'value' => (int) $item->price,
                'quantity' => $item->quantity,
                'weight' => 1000, // default 1kg, refine with product weight field later
            ])->values()->toArray();

            $data = [
                'shipper_contact_name' => config('services.biteship.origin_contact_name', 'AlbaSambosa'),
                'shipper_contact_phone' => config('services.biteship.origin_contact_phone'),
                'shipper_contact_email' => config('services.biteship.origin_contact_email'),
                'origin_contact_name' => config('services.biteship.origin_contact_name', 'AlbaSambosa'),
                'origin_contact_phone' => config('services.biteship.origin_contact_phone'),
                'origin_address' => config('services.biteship.origin_address'),
                'origin_postal_code' => (int) config('services.biteship.origin_postal_code'),
                'destination_contact_name' => $order->recipient_name,
                'destination_contact_phone' => $order->phone,
                'destination_address' => $order->address_detail,
                'destination_postal_code' => (int) $order->postal_code,
                'courier_company' => $shipment->courier,
                'courier_type' => $shipment->courier_service,
                'delivery_type' => 'now',
                'items' => $items,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'shipment_id' => $shipment->id,
                ],
            ];

            $response = $biteship->createOrder($data);

            // Store courier waybill_id for webhook matching; fallback to Biteship order ID
            $shipment->update([
                'waybill_id' => $response['waybill_id'] ?? $response['id'] ?? null,
            ]);

            Log::info('Biteship order created', [
                'order_id' => $order->id,
                'biteship_order_id' => $response['id'] ?? null,
                'courier_waybill_id' => $response['waybill_id'] ?? null,
            ]);

            Notification::make()
                ->title('Biteship delivery order created')
                ->body('Waybill will update automatically via webhook.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Biteship create order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Failed to create Biteship order')
                ->body($e->getMessage().' Use "Manual Update" to input delivery data.')
                ->danger()
                ->send();
        }
    }

    private static function formatStatusLabel(OrderStatus $status): string
    {
        return ucwords(str_replace('_', ' ', $status->value));
    }

    private static function trackingLabel(?string $status): string
    {
        return match ($status) {
            'created' => 'Created',
            'allocated' => 'Courier assigned',
            'picking_up' => 'Heading to store',
            'on_delivery' => 'On delivery',
            'delivered' => 'Delivered',
            default => str_replace('_', ' ', ucwords($status ?? '', '_')),
        };
    }

    private static function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'bank_transfer_bca' => 'Bank Transfer BCA',
            'bank_transfer_bni' => 'Bank Transfer BNI',
            'bank_transfer_bri' => 'Bank Transfer BRI',
            'bca_va' => 'Virtual Account BCA',
            'bni_va' => 'Virtual Account BNI',
            'bri_va' => 'Virtual Account BRI',
            'gopay' => 'GoPay',
            'qris' => 'QRIS',
            'shopeepay' => 'ShopeePay',
            'credit_card' => 'Credit Card',
            'cstore' => 'Convenience Store',
            'echannel' => 'Mandiri Bill',
            'permata_va' => 'Permata VA',
            'other_va' => 'Other VA',
            null, '' => '-',
            default => str_replace('_', ' ', ucwords($method, '_')),
        };
    }
}
