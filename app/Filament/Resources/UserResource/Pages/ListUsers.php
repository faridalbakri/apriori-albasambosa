<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\RegisteredUsersWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RegisteredUsersWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retentionSettings')
                ->label('Data Retention')
                ->icon('heroicon-o-clock')
                ->schema([
                    TextInput::make('guestRetentionMonths')
                        ->label('Guest Retention (months)')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(Cache::get('retention.guest_months', 24)),
                    TextInput::make('registeredRetentionMonths')
                        ->label('Registered Retention (months)')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(Cache::get('retention.registered_months', 36)),
                    Placeholder::make('schedule_info')
                        ->content('Auto-anonymization runs on the 1st of each month at 01:00 WIB. Changes apply on next execution.')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    Cache::forever('retention.guest_months', (int) $data['guestRetentionMonths']);
                    Cache::forever('retention.registered_months', (int) $data['registeredRetentionMonths']);

                    Notification::make()
                        ->title('Retention settings saved.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
