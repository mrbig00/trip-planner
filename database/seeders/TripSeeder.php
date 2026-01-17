<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Location;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        // Create a summer vacation trip
        $summerTrip = Trip::create([
            'user_id' => $users->first()->id,
            'name' => 'Summer Vacation 2025',
            'description' => 'An amazing trip to explore beautiful beaches and coastal cities across Europe. Perfect for relaxing and enjoying the Mediterranean lifestyle.',
        ]);

        // Add participants
        if ($users->count() > 1) {
            $summerTrip->participants()->attach($users->skip(1)->take(2)->pluck('id'));
        }

        // Add locations for summer trip
        $summerTrip->locations()->createMany([
            [
                'name' => 'Santorini, Greece',
                'price' => 1200.00,
                'latitude' => 36.3932,
                'longitude' => 25.4615,
                'link' => 'https://www.santorini.gr/',
                'picture' => null,
                'accepted' => true,
            ],
            [
                'name' => 'Barcelona, Spain',
                'price' => 800.00,
                'latitude' => 41.3851,
                'longitude' => 2.1734,
                'link' => 'https://www.barcelona.com/',
                'picture' => null,
                'accepted' => true,
            ],
            [
                'name' => 'Amalfi Coast, Italy',
                'price' => 1500.00,
                'latitude' => 40.6340,
                'longitude' => 14.6027,
                'link' => 'https://www.visitamalficoast.com/',
                'picture' => null,
                'accepted' => false,
            ],
        ]);

        // Add expenses for summer trip
        $summerEligibleUsers = $summerTrip->load('participants')->participants->pluck('id')->push($summerTrip->user_id)->unique();
        $summerTrip->expenses()->createMany([
            [
                'name' => 'Flight Tickets',
                'unit_price' => 450.00,
                'quantity' => 2,
                'user_id' => $summerEligibleUsers->random(),
            ],
            [
                'name' => 'Hotel Booking',
                'unit_price' => 150.00,
                'quantity' => 14,
                'user_id' => $summerEligibleUsers->random(),
            ],
            [
                'name' => 'Rental Car',
                'unit_price' => 75.00,
                'quantity' => 7,
                'user_id' => $summerEligibleUsers->random(),
            ],
            [
                'name' => 'Travel Insurance',
                'unit_price' => 89.00,
                'quantity' => 1,
                'user_id' => $summerEligibleUsers->random(),
            ],
        ]);

        // Create a mountain adventure trip
        $mountainTrip = Trip::create([
            'user_id' => $users->first()->id,
            'name' => 'Alpine Adventure',
            'description' => 'Experience the breathtaking beauty of the Swiss Alps. Perfect for hiking enthusiasts and nature lovers.',
        ]);

        // Add participants
        if ($users->count() > 1) {
            $mountainTrip->participants()->attach($users->skip(1)->take(1)->pluck('id'));
        }

        // Add locations for mountain trip
        $mountainTrip->locations()->createMany([
            [
                'name' => 'Interlaken, Switzerland',
                'price' => 950.00,
                'latitude' => 46.6863,
                'longitude' => 7.8632,
                'link' => 'https://www.interlaken.ch/',
                'picture' => null,
                'accepted' => true,
            ],
            [
                'name' => 'Zermatt, Switzerland',
                'price' => 1100.00,
                'latitude' => 46.0207,
                'longitude' => 7.7491,
                'link' => 'https://www.zermatt.ch/',
                'picture' => null,
                'accepted' => true,
            ],
        ]);

        // Add expenses for mountain trip
        $mountainEligibleUsers = $mountainTrip->load('participants')->participants->pluck('id')->push($mountainTrip->user_id)->unique();
        $mountainTrip->expenses()->createMany([
            [
                'name' => 'Train Passes',
                'unit_price' => 320.00,
                'quantity' => 2,
                'user_id' => $mountainEligibleUsers->random(),
            ],
            [
                'name' => 'Mountain Huts',
                'unit_price' => 85.00,
                'quantity' => 5,
                'user_id' => $mountainEligibleUsers->random(),
            ],
            [
                'name' => 'Hiking Equipment Rental',
                'unit_price' => 45.00,
                'quantity' => 7,
                'user_id' => $mountainEligibleUsers->random(),
            ],
        ]);

        // Create a city exploration trip
        $cityTrip = Trip::create([
            'user_id' => $users->first()->id,
            'name' => 'European Capitals Tour',
            'description' => 'Explore the rich history and culture of Europe\'s most iconic capital cities.',
        ]);

        // Add participants
        if ($users->count() > 2) {
            $cityTrip->participants()->attach($users->skip(1)->take(1)->pluck('id'));
        }

        // Add locations for city trip
        $cityTrip->locations()->createMany([
            [
                'name' => 'Paris, France',
                'price' => 900.00,
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'link' => 'https://www.paris.fr/',
                'picture' => null,
                'accepted' => true,
            ],
            [
                'name' => 'Berlin, Germany',
                'price' => 750.00,
                'latitude' => 52.5200,
                'longitude' => 13.4050,
                'link' => 'https://www.berlin.de/',
                'picture' => null,
                'accepted' => true,
            ],
            [
                'name' => 'Prague, Czech Republic',
                'price' => 600.00,
                'latitude' => 50.0755,
                'longitude' => 14.4378,
                'link' => 'https://www.prague.eu/',
                'picture' => null,
                'accepted' => false,
            ],
        ]);

        // Add expenses for city trip
        $cityEligibleUsers = $cityTrip->load('participants')->participants->pluck('id')->push($cityTrip->user_id)->unique();
        $cityTrip->expenses()->createMany([
            [
                'name' => 'Interrail Pass',
                'unit_price' => 350.00,
                'quantity' => 2,
                'user_id' => $cityEligibleUsers->random(),
            ],
            [
                'name' => 'City Tours',
                'unit_price' => 35.00,
                'quantity' => 6,
                'user_id' => $cityEligibleUsers->random(),
            ],
            [
                'name' => 'Museum Passes',
                'unit_price' => 75.00,
                'quantity' => 3,
                'user_id' => $cityEligibleUsers->random(),
            ],
            [
                'name' => 'Meals Budget',
                'unit_price' => 50.00,
                'quantity' => 10,
                'user_id' => $cityEligibleUsers->random(),
            ],
        ]);

        // Create more trips using factories if more users exist
        if ($users->count() > 1) {
            $additionalTrips = Trip::factory()
                ->count(5)
                ->create([
                    'user_id' => $users->random()->id,
                ]);

            foreach ($additionalTrips as $trip) {
                // Add some participants randomly
                $participantIds = $users->where('id', '!=', $trip->user_id)
                    ->random(rand(0, min(3, $users->count() - 1)))
                    ->pluck('id');
                if ($participantIds->isNotEmpty()) {
                    $trip->participants()->attach($participantIds);
                }

                // Add locations
                Location::factory()
                    ->count(rand(2, 4))
                    ->create(['trip_id' => $trip->id]);

                // Add expenses
                $eligibleUsers = $trip->load('participants')->participants->pluck('id')->push($trip->user_id)->unique();
                Expense::factory()
                    ->count(rand(3, 6))
                    ->create([
                        'trip_id' => $trip->id,
                        'user_id' => $eligibleUsers->random(),
                    ]);
            }
        }
    }
}
