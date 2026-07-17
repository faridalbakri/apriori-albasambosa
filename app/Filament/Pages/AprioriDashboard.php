<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AprioriRulesWidget;
use App\Filament\Widgets\UnderperformingProductsWidget;
use Filament\Pages\Page;

class AprioriDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'apriori';

    protected string $view = 'filament.pages.apriori-dashboard';

    public function getTitle(): string
    {
        return 'Apriori';
    }

    public static function getNavigationLabel(): string
    {
        return 'Apriori';
    }

    protected function getFooterWidgets(): array
    {
        return [
            AprioriRulesWidget::class,
            UnderperformingProductsWidget::class,
        ];
    }
}
