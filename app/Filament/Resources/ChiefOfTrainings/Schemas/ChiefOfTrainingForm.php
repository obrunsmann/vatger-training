<?php

namespace App\Filament\Resources\ChiefOfTrainings\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms;

class ChiefOfTrainingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name . ' (' . $record->vatsim_id . ')')
                    ->searchable(['first_name', 'last_name', 'vatsim_id'])
                    ->required()
                    ->helperText('Select the user who will be Chief of Training for this course'),
                
                Forms\Components\Select::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->required()
                    ->helperText('Select the course this user will manage'),
            ]);
    }
}