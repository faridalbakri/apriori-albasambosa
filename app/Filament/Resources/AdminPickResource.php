<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminPickResource\Pages;
use App\Models\AdminPick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminPickResource extends Resource
{
    protected static ?string $model = AdminPick::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static string|\UnitEnum|null $navigationGroup = 'Products';

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return 'Admin Pick';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Admin Picks';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Product'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->actions([
                Action::make('edit')
                    ->iconButton()
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->unique('admin_picks', 'product_id', ignoreRecord: true),
                    ])
                    ->fillForm(fn (AdminPick $record): array => [
                        'product_id' => $record->product_id,
                    ])
                    ->action(function (AdminPick $record, array $data): void {
                        $record->update(['product_id' => $data['product_id']]);

                        Notification::make()
                            ->title('Product updated.')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->iconButton()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AdminPick $record): void {
                        $record->delete();

                        Notification::make()
                            ->title('Product removed.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminPicks::route('/'),
        ];
    }
}
