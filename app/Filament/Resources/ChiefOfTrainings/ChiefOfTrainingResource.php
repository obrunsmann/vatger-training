<?php

namespace App\Filament\Resources\ChiefOfTrainings;

use App\Filament\Resources\ChiefOfTrainings\Pages\EditChiefOfTraining;
use App\Filament\Resources\ChiefOfTrainings\Pages\ListChiefOfTrainings;
use App\Filament\Resources\ChiefOfTrainings\Pages\CreateChiefOfTraining;
use App\Filament\Resources\ChiefOfTrainings\Schemas\ChiefOfTrainingForm;
use App\Filament\Resources\ChiefOfTrainings\Tables\ChiefOfTrainingsTable;
use App\Models\ChiefOfTraining;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChiefOfTrainingResource extends Resource
{
    protected static ?string $model = ChiefOfTraining::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Chiefs of Training';

    public static function form(Schema $schema): Schema
    {
        return ChiefOfTrainingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChiefOfTrainingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Permissions';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChiefOfTrainings::route('/'),
            'create' => CreateChiefOfTraining::route('/create'),
            'edit' => EditChiefOfTraining::route('/{record}/edit'),
        ];
    }
}