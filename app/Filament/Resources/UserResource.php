<?php

namespace App\Filament\Resources;

use App\Enums\AnonymizationActionType;
use App\Filament\Resources\UserResource\Pages;
use App\Models\AnonymizationLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = AnonymizationLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 100;

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        AnonymizationActionType::ForgottenManual => 'Manual',
                        AnonymizationActionType::AutoAnonymizeGuest => 'Auto Guest',
                        AnonymizationActionType::AutoAnonymizeRegistered => 'Auto User',
                    })
                    ->color(fn ($state): string => match ($state) {
                        AnonymizationActionType::ForgottenManual => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('Anonymization History');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
