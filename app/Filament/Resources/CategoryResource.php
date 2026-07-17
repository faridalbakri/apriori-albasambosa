<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 11;

    public static function getModelLabel(): string
    {
        return 'Category';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Categories';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->paginated(false)
            ->recordActions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit')
                    ->modalHeading('Edit Category')
                    ->modalSubmitActionLabel('Save')
                    ->form([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->fillForm(fn (Category $record) => $record->only('name'))
                    ->action(function (Category $record, array $data) {
                        $record->update($data);

                        Notification::make()
                            ->success()
                            ->title('Category updated')
                            ->send();
                    }),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete')
                    ->modalHeading('Delete Category')
                    ->modalDescription(fn (Category $record) => "Delete \"{$record->name}\"?")
                    ->modalSubmitActionLabel('Yes, Delete')
                    ->before(function (Category $record, Action $action) {
                        $count = $record->products()->count();
                        if ($count > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete category')
                                ->body("Category \"{$record->name}\" has {$count} product(s). Move or delete them first.")
                                ->send();

                            $action->halt();
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Category deleted')
                    ),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
        ];
    }
}
