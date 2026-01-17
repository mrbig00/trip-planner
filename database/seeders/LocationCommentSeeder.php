<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\LocationComment;
use App\Models\Trip;
use Illuminate\Database\Seeder;

class LocationCommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $trips = Trip::with(['locations', 'participants', 'creator'])->get();

        if ($trips->isEmpty()) {
            $this->command->warn('No trips found. Please run TripSeeder first.');

            return;
        }

        $totalComments = 0;

        foreach ($trips as $trip) {
            $locations = $trip->locations;

            if ($locations->isEmpty()) {
                continue;
            }

            // Get all users who can comment (participants + creator)
            $eligibleUsers = $trip->participants->push($trip->creator)->unique('id');

            if ($eligibleUsers->isEmpty()) {
                continue;
            }

            // Add comments to each location
            foreach ($locations as $location) {
                // Randomly select number of comments (1-5 comments per location)
                $commentsCount = rand(1, 5);

                for ($i = 0; $i < $commentsCount; $i++) {
                    // Randomly select a user
                    $user = $eligibleUsers->random();

                    // Create comment
                    LocationComment::factory()->create([
                        'location_id' => $location->id,
                        'user_id' => $user->id,
                    ]);

                    $totalComments++;
                }
            }
        }
    }
}
