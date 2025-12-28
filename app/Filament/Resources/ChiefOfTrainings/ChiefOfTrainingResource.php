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
use Filament\Facades\Filament;

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

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_admin || $user->is_superuser) {
            return true;
        }

        return $user->canAccessAdminResource('chief_of_trainings');
    }

    public static function canCreate(): bool
    {
        $user = Filament::auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_superuser || $user->is_admin) {
            return true;
        }

        return $user->canEditAdminResource('chief_of_trainings');
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Filament::auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->is_superuser || $user->is_admin) {
            return true;
        }

        return $user->canEditAdminResource('chief_of_trainings');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Filament::auth()->user();

        if (!$user) {
            return false;
        }

        return $user->is_superuser || $user->is_admin;
    }
}