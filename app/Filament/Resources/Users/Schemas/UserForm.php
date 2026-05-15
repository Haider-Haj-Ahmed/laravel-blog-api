<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('username')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                DateTimePicker::make('phone_verified_at'),
                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->default(false)
                    ->disabled(function (?object $record): bool {
                        if (! $record) {
                            return false;
                        }

                        if (auth()->id() === $record->getKey()) {
                            return true;
                        }

                        return (bool) $record->is_admin
                            && \App\Models\User::query()->where('is_admin', true)->count() <= 1;
                    }),
            ]);
    }
}
