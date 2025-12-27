<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VatEudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSoloDays extends Command
{
    protected $signature = 'solo:sync-days';
    protected $description = 'Sync solo days used from VatEUD for all users with active solos';

    protected VatEudService $vatEudService;

    public function __construct(VatEudService $vatEudService)
    {
        parent::__construct();
        $this->vatEudService = $vatEudService;
    }

    public function handle(): int
    {
        $this->info('Starting solo days sync from VatEUD...');

        try {
            $soloEndorsements = $this->vatEudService->getSoloEndorsements();
            
            if (empty($soloEndorsements)) {
                $this->info('No solo endorsements found in VatEUD');
                return 0;
            }

            $this->info('Found ' . count($soloEndorsements) . ' solo endorsements');

            $userSoloDays = collect($soloEndorsements)
                ->groupBy('user_cid')
                ->map(function ($userSolos) {
                    return $userSolos->max('position_days') ?? 0;
                });

            $bar = $this->output->createProgressBar($userSoloDays->count());
            $bar->start();

            $updatedCount = 0;
            foreach ($userSoloDays as $vatsimId => $soloDays) {
                try {
                    $user = User::where('vatsim_id', $vatsimId)->first();
                    
                    if ($user) {
                        $currentDays = $user->solo_days_used ?? 0;
                        $newDays = (int) $soloDays;

                        if ($this->shouldResetSoloDays($user)) {
                            $this->line("\nResetting solo days for {$user->name} ({$vatsimId}) due to rating upgrade");
                            $user->solo_days_used = 0;
                            $user->rating_upgraded_at = null;
                            $user->save();

                            Log::info('Solo days reset due to rating upgrade', [
                                'vatsim_id' => $vatsimId,
                                'user_name' => $user->name,
                                'old_rating' => $user->last_known_rating,
                                'new_rating' => $user->rating,
                            ]);
                        }
                        // Only update if VatEUD shows more days
                        elseif ($newDays > $currentDays) {
                            $user->solo_days_used = $newDays;
                            $user->save();
                            
                            $this->line("\nUpdated {$user->name} ({$vatsimId}): {$currentDays} → {$newDays} days");
                        }
                        
                        $updatedCount++;
                    } else {
                        $this->warn("\nUser with VATSIM ID {$vatsimId} not found in database");
                    }
                } catch (\Exception $e) {
                    $this->error("\nFailed to update user {$vatsimId}: " . $e->getMessage());
                    Log::error('Failed to update solo days for user', [
                        'vatsim_id' => $vatsimId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Processed {$updatedCount} users.");
            
            return 0;

        } catch (\Exception $e) {
            $this->error('Error during solo days sync: ' . $e->getMessage());
            Log::error('Solo days sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function shouldResetSoloDays(User $user): bool
    {
        if (!$user->rating_upgraded_at) {
            return false;
        }

        if ($user->rating === $user->last_known_rating) {
            return false;
        }

        // Rating has increased - reset solo days
        if ($user->rating > $user->last_known_rating) {
            return true;
        }

        return false;
    }
}