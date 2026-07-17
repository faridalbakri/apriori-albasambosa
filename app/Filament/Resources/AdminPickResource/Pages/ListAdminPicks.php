<?php

namespace App\Filament\Resources\AdminPickResource\Pages;

use App\Filament\Resources\AdminPickResource;
use App\Models\AdminPick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAdminPicks extends ListRecords
{
    protected static string $resource = AdminPickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add')
                ->icon('heroicon-o-plus')
                ->form([
                    Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->unique('admin_picks', 'product_id'),
                ])
                ->action(function (array $data): void {
                    if (AdminPick::count() >= 5) {
                        Notification::make()
                            ->title('Maximum 5 Admin Picks')
                            ->danger()
                            ->send();

                        return;
                    }

                    AdminPick::create(['product_id' => $data['product_id']]);

                    Notification::make()
                        ->title('Product added successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
