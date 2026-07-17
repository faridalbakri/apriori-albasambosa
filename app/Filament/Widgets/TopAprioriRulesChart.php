<?php

namespace App\Filament\Widgets;

use App\Models\AprioriRule;
use App\Models\Product;
use Filament\Widgets\ChartWidget;

class TopAprioriRulesChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Top 5 Bundling Rules';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
                'datalabels' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['display' => false],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $rules = AprioriRule::orderByDesc('lift')->take(5)->get();

        if ($rules->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $productIds = collect();
        foreach ($rules as $r) {
            $productIds = $productIds->concat($r->antecedent ?? [])->concat($r->consequent ?? []);
        }
        $nameMap = Product::whereIn('id', $productIds->unique()->values())->pluck('name', 'id')->toArray();

        $labels = [];
        $liftData = [];

        foreach ($rules as $r) {
            $ante = array_map(fn ($id) => $nameMap[(int) $id] ?? "P#{$id}", $r->antecedent ?? []);
            $cons = array_map(fn ($id) => $nameMap[(int) $id] ?? "P#{$id}", $r->consequent ?? []);
            $labels[] = implode(' + ', $ante).' → '.implode(' + ', $cons);
            $liftData[] = round((float) $r->lift, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Lift',
                    'data' => $liftData,
                    'backgroundColor' => '#CA8A04',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
