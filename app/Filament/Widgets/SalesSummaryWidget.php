<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayOrders = Order::whereDate('created_at', today())
            ->whereIn('status', [
                OrderStatus::Settlement->value,
                OrderStatus::Processing->value,
                OrderStatus::ReadyPickup->value,
                OrderStatus::Shipping->value,
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ])
            ->count();

        $todayRevenue = Order::whereDate('created_at', today())
            ->whereIn('status', [
                OrderStatus::Settlement->value,
                OrderStatus::Processing->value,
                OrderStatus::ReadyPickup->value,
                OrderStatus::Shipping->value,
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ])
            ->sum('total_price');

        $monthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', [
                OrderStatus::Settlement->value,
                OrderStatus::Processing->value,
                OrderStatus::ReadyPickup->value,
                OrderStatus::Shipping->value,
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ])
            ->count();

        $monthRevenue = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereIn('status', [
                OrderStatus::Settlement->value,
                OrderStatus::Processing->value,
                OrderStatus::ReadyPickup->value,
                OrderStatus::Shipping->value,
                OrderStatus::Delivered->value,
                OrderStatus::Completed->value,
            ])
            ->sum('total_price');

        $pendingCount = Order::where('status', OrderStatus::Pending->value)->count();

        return [
            Stat::make('Today Orders', $todayOrders)
                ->description($pendingCount > 0 ? "{$pendingCount} pending payment" : 'All done')
                ->icon('heroicon-o-shopping-bag')
                ->color($pendingCount > 0 ? 'warning' : 'success'),

            Stat::make('Today Revenue', 'Rp '.number_format((float) $todayRevenue, 0, ',', '.'))
                ->icon('heroicon-o-banknotes')
                ->color('primary'),

            Stat::make('Monthly Orders', $monthOrders)
                ->icon('heroicon-o-calendar')
                ->color('info'),

            Stat::make('Monthly Revenue', 'Rp '.number_format((float) $monthRevenue, 0, ',', '.'))
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }
}
