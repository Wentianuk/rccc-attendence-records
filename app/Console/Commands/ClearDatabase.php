<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Models\AttendanceRecord;
use App\Services\CompreFaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClearDatabase extends Command
{
    protected $signature = 'db:clear';
    protected $description = 'Clear all data from the database and CompreFace';

    private $compreFaceService;

    public function __construct(CompreFaceService $compreFaceService)
    {
        parent::__construct();
        $this->compreFaceService = $compreFaceService;
    }

    public function handle()
    {
        if (!$this->confirm('This will delete ALL data from the database and CompreFace. Are you sure you want to continue?')) {
            $this->info('Operation cancelled.');
            return;
        }

        $this->info('Starting database cleanup...');

        // Get all members before deletion
        $members = Member::withTrashed()->get();

        foreach ($members as $member) {
            $this->info("Processing member: {$member->full_name}");

            // Delete face from CompreFace
            if ($member->face_id) {
                try {
                    $this->compreFaceService->deleteFace($member->face_id);
                    $this->info("Deleted face ID: {$member->face_id} from CompreFace");
                } catch (\Exception $e) {
                    $this->warn("Failed to delete face from CompreFace: {$e->getMessage()}");
                    Log::warning("Failed to delete face from CompreFace", [
                        'face_id' => $member->face_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Delete photo file
            if ($member->photo) {
                try {
                    Storage::disk('public')->delete($member->photo);
                    $this->info("Deleted photo: {$member->photo}");
                } catch (\Exception $e) {
                    $this->warn("Failed to delete photo file: {$e->getMessage()}");
                }
            }
        }

        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Clear all tables
            $this->info('Clearing attendance records...');
            DB::table('attendance_records')->truncate();

            $this->info('Clearing members table...');
            DB::table('members')->truncate();

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Clear photos directory
            $this->info('Clearing photos directory...');
            Storage::disk('public')->deleteDirectory('member-photos');
            Storage::disk('public')->makeDirectory('member-photos');

            $this->info('Database cleared successfully!');
            $this->info('You can now start fresh with new registrations.');

        } catch (\Exception $e) {
            // Re-enable foreign key checks even if there's an error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->error('An error occurred while clearing the database:');
            $this->error($e->getMessage());
            Log::error('Error clearing database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 