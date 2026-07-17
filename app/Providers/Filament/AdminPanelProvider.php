<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\PendingOrdersWidget;
use App\Filament\Widgets\SalesSummaryWidget;
use App\Filament\Widgets\TopAprioriRulesChart;
use App\Http\Middleware\EnsureAdminRole;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('AlbaSambosa')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->databaseNotifications()
            ->widgets([
                SalesSummaryWidget::class,
                TopAprioriRulesChart::class,
                PendingOrdersWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAdminRole::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => '<link href="https://fonts.googleapis.com/css2?family=Josefin+Sans:wght@400;700&display=swap" rel="stylesheet">',
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>
                    :root {
                        --color-primary: #92400E;
                        --color-accent: #CA8A04;
                        --color-background: #FEF3C7;
                        --color-foreground: #78350F;
                        --color-muted: #FDE68A;
                        --color-border: #FCD34D;
                        --color-destructive: #991b1b;
                        --color-success: #16a34a;
                        --color-warning: #d97706;
                        --color-info: #2563eb;
                    }
                    .fi-logo{font-family:\'Josefin Sans\',sans-serif!important}
                    .fi-sidebar, .fi-sidebar-nav{-ms-overflow-style:none;scrollbar-width:none}
                    .fi-sidebar::-webkit-scrollbar, .fi-sidebar-nav::-webkit-scrollbar{display:none}
                </style>',
            );
    }
}
