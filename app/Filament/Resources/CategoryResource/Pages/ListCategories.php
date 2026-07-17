<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Add')
                ->icon('heroicon-o-plus')
                ->modalHeading('New Category')
                ->modalSubmitActionLabel('Save')
                ->form([
                    TextInput::make('name')
                        ->label('Category Name')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data): void {
                    Category::create($data);

                    Notification::make()
                        ->success()
                        ->title('Category created')
                        ->send();
                }),
        ];
    }
}
