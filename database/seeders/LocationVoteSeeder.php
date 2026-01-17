<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocationVoteSeeder extends Seeder
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

        $totalVotes = 0;

        foreach ($trips as $trip) {
            $locations = $trip->locations;

            if ($locations->isEmpty()) {
                continue;
            }

            // Get all users who can vote (participants + creator)
            $eligibleVoters = $trip->participants->push($trip->creator)->unique('id');

            if ($eligibleVoters->isEmpty()) {
                continue;
            }

            // Add votes to each location
            foreach ($locations as $location) {
                // Randomly select some voters (at least one, up to all eligible voters)
                $votersCount = rand(1, $eligibleVoters->count());
                $voters = $eligibleVoters->random($votersCount);

                foreach ($voters as $voter) {
                    // Check if user already voted for this location
                    if (!$location->votes()->where('user_id', $voter->id)->exists()) {
                        $location->votes()->attach($voter->id);
                        $totalVotes++;
                    }
                }
            }
        }
    }
}
