<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;


class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'vatsim_id',
        'first_name',
        'last_name',
        'email',
        'subdivision',
        'rating',
        'last_rating_change',
        'is_staff',
        'is_superuser',
        'is_admin',
        'password',
        'solo_days_used',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_rating_change' => 'datetime',
        'is_staff' => 'boolean',
        'is_superuser' => 'boolean',
        'is_admin' => 'boolean',
        'rating' => 'integer',
        'vatsim_id' => 'integer',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'solo_days_used' => 'integer',
    ];

    public function getRouteKeyName()
    {
        return 'vatsim_id';
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function isMentor(): bool
    {
        return $this->hasAnyRole(['EDGG Mentor', 'EDMM Mentor', 'EDWW Mentor', 'ATD Leitung', 'VATGER Leitung']);
    }

    public function isSuperuser(): bool
    {
        return $this->is_superuser === true || $this->is_admin === true;
    }

    public function isLeadership(): bool
    {
        return $this->hasAnyRole(['ATD Leitung', 'VATGER Leitung']);
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function scopeMentors($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', ['EDGG Mentor', 'EDMM Mentor', 'EDWW Mentor']);
        });
    }

    public function scopeLeadership($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->whereIn('name', ['ATD Leitung', 'VATGER Leitung']);
        });
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function isVatsimUser(): bool
    {
        return !empty($this->vatsim_id);
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', true);
    }

    public function scopeVatsimUsers($query)
    {
        return $query->whereNotNull('vatsim_id');
    }

    public function endorsementActivities()
    {
        return $this->hasMany(EndorsementActivity::class, 'vatsim_id', 'vatsim_id');
    }

    public function hasActiveTier1Endorsements(): bool
    {
        if (!$this->isVatsimUser()) {
            return false;
        }

        return $this->endorsementActivities()
            ->where('activity_minutes', '>=', config('services.vateud.min_activity_minutes', 180))
            ->exists();
    }

    public function getEndorsementSummary(): array
    {
        if (!$this->isVatsimUser()) {
            return [
                'tier1_count' => 0,
                'tier2_count' => 0,
                'solo_count' => 0,
                'low_activity_count' => 0,
            ];
        }

        $tier1Count = $this->endorsementActivities()->count();
        $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);
        $lowActivityCount = $this->endorsementActivities()
            ->where('activity_minutes', '<', $minRequiredMinutes)
            ->count();

        return [
            'tier1_count' => $tier1Count,
            'tier2_count' => 0,
            'solo_count' => 0,
            'low_activity_count' => $lowActivityCount,
        ];
    }

    public function needsEndorsementAttention(): bool
    {
        if (!$this->isVatsimUser()) {
            return false;
        }

        return $this->endorsementActivities()
            ->where(function ($query) {
                $minRequiredMinutes = config('services.vateud.min_activity_minutes', 180);
                $query->where('activity_minutes', '<', $minRequiredMinutes)
                    ->orWhereNotNull('removal_date');
            })
            ->exists();
    }

    public function activeCourses()
    {
        return $this->belongsToMany(Course::class, 'course_trainees')
            ->whereNull('course_trainees.completed_at')
            ->withPivot([
                'claimed_by_mentor_id',
                'claimed_at',
                'completed_at',
                'remarks',
                'remark_author_id',
                'remark_updated_at',
                'custom_order',
            ])
            ->withTimestamps();
    }

    public function mentorCourses()
    {
        return $this->belongsToMany(Course::class, 'course_mentors');
    }

    public function activeRatingCourses()
    {
        return $this->activeCourses()->where('type', 'RTG');
    }

    public function waitingListEntries()
    {
        return $this->hasMany(WaitingListEntry::class);
    }

    public function familiarisations()
    {
        return $this->hasMany(Familiarisation::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            \Log::info("User {$this->id} granted admin access: superuser/admin");
            return true;
        }

        $isLM = $this->isLeadingMentor();

        if ($isLM) {
            \Log::info("User {$this->id} granted admin access: LM={$isLM}");
            return true;
        }

        $hasPermission = $this->hasPermission(permissionName: 'admin.access');
        \Log::info("User {$this->id} admin access check: hasPermission={$hasPermission}");

        return $hasPermission;
    }

    public function canAccessAdminResource(string $resource): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        if ($resource === 'courses' && ($this->isLeadingMentor() || $this->isChiefOfTraining())) {
            return true;
        }

        $permissionName = "admin.{$resource}.view";
        return $this->hasPermission($permissionName);
    }

    public function canEditAdminResource(string $resource): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        if ($resource === 'courses' && ($this->isLeadingMentor() || $this->isChiefOfTraining())) {
            return true;
        }

        $permissionName = "admin.{$resource}.edit";
        return $this->hasPermission($permissionName);
    }

    public function trainingLogs()
    {
        return $this->hasMany(TrainingLog::class, 'trainee_id');
    }

    public function examiner()
    {
        return $this->hasOne(Examiner::class);
    }

    public function isExaminer(): bool
    {
        return $this->examiner()->exists();
    }

    public function cpts()
    {
        return $this->hasMany(Cpt::class, 'trainee_id');
    }

    public function examinedCpts()
    {
        return $this->hasMany(Cpt::class, 'examiner_id');
    }

    public function localCpts()
    {
        return $this->hasMany(Cpt::class, 'local_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function chiefOfTrainingCourses()
    {
        return $this->belongsToMany(Course::class, 'chief_of_trainings');
    }

    public function leadingMentorFirs()
    {
        return $this->hasMany(LeadingMentor::class);
    }

    public function hasPermission(string $permissionName): bool
    {
        $hasDirectPermission = \DB::table('user_permissions')
            ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
            ->where('user_permissions.user_id', $this->id)
            ->where('permissions.name', $permissionName)
            ->exists();

        if ($hasDirectPermission) {
            \Log::info("User {$this->id} has direct permission: {$permissionName}");
            return true;
        }

        $hasRolePermission = \DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $this->id)
            ->where('permissions.name', $permissionName)
            ->exists();

        if ($hasRolePermission) {
            \Log::info("User {$this->id} has role permission: {$permissionName}");
            return true;
        }

        \Log::info("User {$this->id} does NOT have permission: {$permissionName}");
        return false;
    }

    private function findCourseByPosition(string $position): ?Course
    {
        $parts = explode('_', $position);
        if (count($parts) < 2) {
            return null;
        }

        $airportIcao = $parts[0];

        if (count($parts) > 2 && $parts[1] === 'GNDDEL') {
            $positionType = 'GND';
        } else {
            $positionType = $parts[1];
        }

        return Course::where('airport_icao', $airportIcao)
            ->where('position', $positionType)
            ->first();
    }

    public function canRemoveEndorsementForPosition(string $position): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        $allowedPositions = $this->mentorCourses
            ->flatMap(function (Course $course) {
                $airport = $course->airport_icao;
                $position = $course->position;

                if ($position === 'GND') {
                    return ["{$airport}_GNDDEL"];
                }

                return ["{$airport}_{$position}"];
            })
            ->unique()
            ->values();

        if ($allowedPositions->contains($position)) {
            return true;
        }

        $course = $this->findCourseByPosition($position);
        if ($course) {
            return $this->canManageEndorsementsFor($course);
        }

        return false;
    }

    public function getFirFromMentorGroup(?string $groupName): ?string
    {
        if (!$groupName) {
            return null;
        }

        if (str_contains($groupName, 'EDGG'))
            return 'EDGG';
        if (str_contains($groupName, 'EDMM'))
            return 'EDMM';
        if (str_contains($groupName, 'EDWW'))
            return 'EDWW';

        return null;
    }

    public function getAccessibleCourseIds(): array
    {
        if ($this->is_superuser || $this->is_admin) {
            return Course::pluck('id')->toArray();
        }

        $cacheKey = "user_{$this->id}_accessible_courses";

        return \Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $courseIds = [];

            $mentorCourseIds = \DB::table('course_mentors')
                ->where('user_id', $this->id)
                ->pluck('course_id')
                ->toArray();
            $courseIds = array_merge($courseIds, $mentorCourseIds);

            $cotCourseIds = \DB::table('chief_of_trainings')
                ->where('user_id', $this->id)
                ->pluck('course_id')
                ->toArray();
            $courseIds = array_merge($courseIds, $cotCourseIds);

            $lmFirs = \DB::table('leading_mentors')
                ->where('user_id', $this->id)
                ->pluck('fir')
                ->toArray();

            if (!empty($lmFirs)) {
                $lmCourseIds = \DB::table('courses')
                    ->join('roles', 'courses.mentor_group_id', '=', 'roles.id')
                    ->where(function ($query) use ($lmFirs) {
                        foreach ($lmFirs as $fir) {
                            $query->orWhere('roles.name', 'LIKE', "%{$fir}%");
                        }
                    })
                    ->pluck('courses.id')
                    ->toArray();
                $courseIds = array_merge($courseIds, $lmCourseIds);
            }

            return array_unique($courseIds);
        });
    }

    public function canViewCourse(Course $course): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        $accessibleCourseIds = $this->getAccessibleCourseIds();

        if (in_array($course->id, $accessibleCourseIds)) {
            return true;
        }

        $isMentor = \DB::table('course_mentors')
            ->where('course_id', $course->id)
            ->where('user_id', $this->id)
            ->exists();

        if ($isMentor) {
            return true;
        }

        $isCoT = \DB::table('chief_of_trainings')
            ->where('course_id', $course->id)
            ->where('user_id', $this->id)
            ->exists();

        if ($isCoT) {
            return true;
        }

        if ($course->mentor_group_id) {
            $mentorGroupName = \DB::table('roles')
                ->where('id', $course->mentor_group_id)
                ->value('name');

            if ($mentorGroupName) {
                $fir = $this->getFirFromMentorGroup($mentorGroupName);
                if ($fir) {
                    $isLM = \DB::table('leading_mentors')
                        ->where('user_id', $this->id)
                        ->where('fir', $fir)
                        ->exists();

                    if ($isLM) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function canEditTrainingLog(TrainingLog $log): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        if ($this->id === $log->mentor_id) {
            return true;
        }

        if (!$log->course_id) {
            return false;
        }

        $isCoT = \DB::table('chief_of_trainings')
            ->where('user_id', $this->id)
            ->where('course_id', $log->course_id)
            ->exists();

        if ($isCoT) {
            return true;
        }

        $course = $log->course;
        if (!$course || !$course->mentor_group_id) {
            return false;
        }

        $mentorGroupName = \DB::table('roles')
            ->where('id', $course->mentor_group_id)
            ->value('name');

        if (!$mentorGroupName) {
            return false;
        }

        $fir = $this->getFirFromMentorGroup($mentorGroupName);
        if (!$fir) {
            return false;
        }

        return \DB::table('leading_mentors')
            ->where('user_id', $this->id)
            ->where('fir', $fir)
            ->exists();
    }

    public function canManageEndorsementsFor(Course $course): bool
    {
        if ($this->is_superuser || $this->is_admin) {
            return true;
        }

        $isCoT = \DB::table('chief_of_trainings')
            ->where('user_id', $this->id)
            ->where('course_id', $course->id)
            ->exists();

        if ($isCoT) {
            return true;
        }

        if (!$course->mentor_group_id) {
            return false;
        }

        $mentorGroupName = \DB::table('roles')
            ->where('id', $course->mentor_group_id)
            ->value('name');

        if (!$mentorGroupName) {
            return false;
        }

        $fir = $this->getFirFromMentorGroup($mentorGroupName);
        if (!$fir) {
            return false;
        }

        return \DB::table('leading_mentors')
            ->where('user_id', $this->id)
            ->where('fir', $fir)
            ->exists();
    }

    public function isChiefOfTraining(): bool
    {
        return \DB::table('chief_of_trainings')
            ->where('user_id', $this->id)
            ->exists();
    }

    public function isLeadingMentor(): bool
    {
        return \DB::table('leading_mentors')
            ->where('user_id', $this->id)
            ->exists();
    }
}