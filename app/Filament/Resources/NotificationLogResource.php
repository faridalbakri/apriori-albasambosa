<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    public static function getModelLabel(): string
    {
        return 'Notification Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Notification Logs';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('metadata.channel')->label('Channel')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'whatsapp' => 'success',
                        'biteship' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('metadata.order_number')->label('Order #'),
                TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->modalWidth('sm'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('metadata.channel')->label('Channel')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'whatsapp' => 'success',
                        'biteship' => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('metadata.order_number')->label('Order #'),
                TextEntry::make('metadata.phone')->label('Phone')
                    ->visible(fn ($record) => ($record->metadata['channel'] ?? '') === 'whatsapp'),
                TextEntry::make('metadata.waybill_id')->label('Waybill ID')
                    ->visible(fn ($record) => ($record->metadata['channel'] ?? '') === 'biteship'),
                TextEntry::make('metadata.status')->label('Order Status')
                    ->visible(fn ($record) => ($record->metadata['channel'] ?? '') === 'whatsapp'),
                TextEntry::make('user.name')->label('Customer'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('created_at')->label('Time')->dateTime('d M Y H:i:s'),
                TextEntry::make('metadata.error')->label('Error')
                    ->visible(fn ($record) => $record->status === 'failed'),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
        ];
    }
}
