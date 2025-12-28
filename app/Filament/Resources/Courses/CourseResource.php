<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\Pages\CreateCourse;
use App\Filament\Resources\Courses\Schemas\CourseForm;
use App\Filament\Resources\Courses\Tables\CoursesTable;
use App\Models\Course;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CourseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoursesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Training';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
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

        return $user->canAccessAdminResource('courses');
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

        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Filament::auth()->user();

        if ($user->is_superuser || $user->is_admin) {
            return true;
        }

        if (!$user->canEditAdminResource('courses')) {
            return false;
        }

        if ($user->isLeadingMentor() && $record->mentorGroup) {
            $fir = $user->getFirFromMentorGroup($record->mentorGroup->name);
            if ($fir && $user->leadingMentorFirs()->where('fir', $fir)->exists()) {
                return true;
            }
        }

        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = Filament::auth()->user();

        if ($user->is_superuser || $user->is_admin) {
            return true;
        }

        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        if ($user->is_superuser || $user->is_admin) {
            return $query;
        }

        if ($user->isLeadingMentor()) {
            $userFirs = $user->leadingMentorFirs()->pluck('fir')->toArray();

            if (!empty($userFirs)) {
                $query->whereHas('mentorGroup', function ($q) use ($userFirs) {
                    $q->where(function ($q2) use ($userFirs) {
                        foreach ($userFirs as $fir) {
                            $q2->orWhere('name', 'LIKE', "%{$fir}%");
                        }
                    });
                });
            }
        }

        return $query;
    }
}