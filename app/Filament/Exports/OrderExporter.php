<?php

namespace App\Filament\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order_number')
                ->label('Order #'),

            ExportColumn::make('created_at')
                ->label('Date')
                ->formatStateUsing(fn ($state): string => $state->format('d M Y H:i')),

            ExportColumn::make('recipient_name')
                ->label('Customer'),

            ExportColumn::make('phone')
                ->label('Phone'),

            ExportColumn::make('method')
                ->label('Method')
                ->state(fn (Order $record): string => $record->pickup_time ? 'Pickup' : 'Delivery'),

            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (OrderStatus $state): string => ucwords(str_replace('_', ' ', $state->value))),

            ExportColumn::make('items_detail')
                ->label('Products')
                ->state(function (Order $record): string {
                    return $record->items
                        ->map(function ($item): string {
                            $name = $item->product?->name ?? 'Product #'.$item->product_id;

                            return $name.' ('.$item->quantity.')';
                        })
                        ->join(', ');
                }),

            ExportColumn::make('total_price')
                ->label('Total')
                ->formatStateUsing(fn ($state): string => 'Rp '.number_format((float) $state, 0, ',', '.')),

            ExportColumn::make('shipping_cost')
                ->label('Shipping')
                ->formatStateUsing(fn ($state): string => $state > 0 ? 'Rp '.number_format((float) $state, 0, ',', '.') : '-'),

            ExportColumn::make('payment_method')
                ->label('Payment')
                ->formatStateUsing(fn (?string $state): string => self::paymentMethodLabel($state)),

            ExportColumn::make('address_detail')
                ->label('Address'),

            ExportColumn::make('postal_code')
                ->label('Postal Code'),

            ExportColumn::make('courier_info')
                ->label('Courier')
                ->state(function (Order $record): string {
                    $shipment = $record->shipment;

                    if (! $shipment || ! $shipment->courier) {
                        return '-';
                    }

                    return $shipment->courier.($shipment->courier_service ? ' • '.$shipment->courier_service : '');
                }),

            ExportColumn::make('waybill_id')
                ->label('Waybill')
                ->state(fn (Order $record): string => $record->shipment?->waybill_id ?? '-'),

            ExportColumn::make('pickup_time')
                ->label('Pickup Time')
                ->formatStateUsing(fn ($state): string => $state ? $state->format('d M Y H:i') : '-'),
        ];
    }

    /**
     * Eager-load relationships to avoid N+1 during export.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['items.product', 'shipment']);
    }

    // -------------------------------------------------------
    // XLSX Styling
    // -------------------------------------------------------

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor(Color::rgb(255, 255, 255))
            ->setBackgroundColor(Color::rgb(146, 64, 14)) // #92400E — primary
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style)
            ->setFontSize(11);
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options;

        // manual column widths — OpenSpout auto-width is unreliable
        $options->setColumnWidth(22, 1);  // order_number
        $options->setColumnWidth(18, 2);  // created_at
        $options->setColumnWidth(20, 3);  // recipient_name
        $options->setColumnWidth(16, 4);  // phone
        $options->setColumnWidth(10, 5);  // method
        $options->setColumnWidth(18, 6);  // status
        $options->setColumnWidth(50, 7);  // items_detail (wide — product list)
        $options->setColumnWidth(16, 8);  // total_price
        $options->setColumnWidth(16, 9);  // shipping_cost
        $options->setColumnWidth(22, 10); // payment_method
        $options->setColumnWidth(35, 11); // address_detail
        $options->setColumnWidth(12, 12); // postal_code
        $options->setColumnWidth(22, 13); // courier_info
        $options->setColumnWidth(18, 14); // waybill_id
        $options->setColumnWidth(18, 15); // pickup_time

        return $options;
    }

    public function configureXlsxWriterBeforeClose(XlsxWriter $writer): XlsxWriter
    {
        $sheetView = new SheetView;
        $sheetView->setFreezeRow(2); // freeze after header

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName('Orders');

        return $writer;
    }

    // -------------------------------------------------------
    // Custom Filename
    // -------------------------------------------------------

    public function getFileName(Export $export): string
    {
        return 'Orders-AlbaSambosa-'.now()->format('d-M-Y');
    }

    // -------------------------------------------------------
    // Notifications
    // -------------------------------------------------------

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Orders Export Ready';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = (string) $export->successful_rows.' orders exported successfully.';

        $failed = $export->getFailedRowsCount();

        if ($failed > 0) {
            $body .= ' '.$failed.' failed.';
        }

        return $body;
    }

    public static function modifyCompletedNotification(Notification $notification, Export $export): Notification
    {
        // Set document icon on download action links in-place
        $raw = invade($notification)->actions;
        if (is_array($raw)) {
            foreach ($raw as $action) {
                if ($action instanceof Action) {
                    $action->icon('heroicon-o-document-arrow-down');
                }
            }
        }

        return $notification
            ->icon('heroicon-o-check-circle')
            ->color('success');
    }

    // -------------------------------------------------------
    // Helpers (reuse same logic as OrderResource)
    // -------------------------------------------------------

    private static function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'bank_transfer_bca' => 'Bank Transfer BCA',
            'bank_transfer_bni' => 'Bank Transfer BNI',
            'bank_transfer_bri' => 'Bank Transfer BRI',
            'bca_va', 'bni_va', 'bri_va' => 'Virtual Account '.strtoupper(explode('_', $method)[0]),
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
