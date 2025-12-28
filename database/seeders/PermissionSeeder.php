<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Endorsement permissions
            ['name' => 'endorsements.remove', 'description' => 'Can initiate endorsement removal process', 'group' => 'endorsements'],
            ['name' => 'endorsements.view-statistics', 'description' => 'Can view endorsement statistics', 'group' => 'endorsements'],
            
            // Training log permissions
            ['name' => 'training-logs.edit-all-course', 'description' => 'Can edit all logs for assigned courses', 'group' => 'training_logs'],
            ['name' => 'training-logs.edit-all-fir', 'description' => 'Can edit all logs for assigned FIR', 'group' => 'training_logs'],
            ['name' => 'training-logs.view-statistics', 'description' => 'Can view training log statistics', 'group' => 'training_logs'],
            
            // Course permissions
            ['name' => 'courses.view-all-fir', 'description' => 'Can view all courses in assigned FIR', 'group' => 'courses'],
            ['name' => 'courses.edit', 'description' => 'Can edit course details', 'group' => 'courses'],
            ['name' => 'courses.view-mentor-statistics', 'description' => 'Can view mentor statistics for courses', 'group' => 'courses'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }
    }
}