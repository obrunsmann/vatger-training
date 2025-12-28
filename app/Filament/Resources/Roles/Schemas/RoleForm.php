<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Schemas\Components\Section;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(1),

                Section::make('Permissions')
                    ->schema([
                        Forms\Components\Select::make('permissions')
                            ->label('Role Permissions')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Permissions granted to all users with this role')
                            ->options(function () {
                                return \App\Models\Permission::query()
                                    ->orderBy('group')
                                    ->orderBy('name')
                                    ->get()
                                    ->groupBy('group')
                                    ->flatMap(function ($permissions, $group) {
                                        return $permissions->pluck('name', 'id')->mapWithKeys(function ($name, $id) use ($group) {
                                            return [$id => ($group ? "[$group] " : '') . $name];
                                        });
                                    })
                                    ->toArray();
                            }),
                    ])->columns(1),
            ]);
    }
}