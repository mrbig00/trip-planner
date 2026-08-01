<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Trip;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    /**
     * Render the component.
     */
    public function render(): View
    {
        $trips = $this->trips();

        return view('livewire.dashboard', [
            'title' => __('Dashboard'),
            'trips' => $trips,
            'stats' => $this->stats($trips),
            'tripsPerMonth' => $this->tripsPerMonth($trips),
            'spendByTrip' => $this->spendByTrip($trips),
            'destinationStatus' => $this->destinationStatus($trips),
            'upcomingTrips' => $this->upcomingTrips($trips),
        ]);
    }

    /**
     * Get the trips the authenticated user created or participates in.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Trip>
     */
    private function trips()
    {
        return Trip::query()
            ->where(function ($query) {
                $query->where('user_id', Auth::id())
                    ->orWhereHas('participants', function ($query) {
                        $query->where('user_id', Auth::id());
                    });
            })
            ->with(['locations', 'expenses', 'participants'])
            ->get();
    }

    /**
     * Top-line stat tiles.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trip>  $trips
     * @return array<string, mixed>
     */
    private function stats($trips): array
    {
        $today = Carbon::today();
        $locations = $trips->flatMap->locations;

        return [
            'totalTrips' => $trips->count(),
            'upcomingTrips' => $trips->filter(fn (Trip $trip) => $trip->start_date && $trip->start_date->greaterThanOrEqualTo($today))->count(),
            'totalSpend' => $trips->flatMap->expenses->sum('total'),
            'acceptedDestinations' => $locations->where('accepted', true)->count(),
            'proposedDestinations' => $locations->where('accepted', false)->count(),
        ];
    }

    /**
     * Number of trips created per month for the last 12 months.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trip>  $trips
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function tripsPerMonth($trips): array
    {
        $months = collect(range(11, 0))->map(fn (int $offset) => Carbon::today()->subMonths($offset)->startOfMonth());

        $counts = $trips->countBy(fn (Trip $trip) => $trip->created_at->format('Y-m'));

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->format('M Y'))->all(),
            'data' => $months->map(fn (Carbon $month) => $counts->get($month->format('Y-m'), 0))->all(),
        ];
    }

    /**
     * Total spend for the trips with the highest expenses.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trip>  $trips
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    private function spendByTrip($trips): array
    {
        $ranked = $trips
            ->map(fn (Trip $trip) => [
                'name' => $trip->name,
                'total' => (float) $trip->expenses->sum('total'),
            ])
            ->filter(fn (array $trip) => $trip['total'] > 0)
            ->sortByDesc('total')
            ->take(8)
            ->values();

        return [
            'labels' => $ranked->pluck('name')->all(),
            'data' => $ranked->pluck('total')->all(),
        ];
    }

    /**
     * Accepted vs. still-undecided destinations across all trips.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trip>  $trips
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function destinationStatus($trips): array
    {
        $locations = $trips->flatMap->locations;

        return [
            'labels' => [__('Accepted'), __('Proposed')],
            'data' => [
                $locations->where('accepted', true)->count(),
                $locations->where('accepted', false)->count(),
            ],
        ];
    }

    /**
     * Upcoming trips, soonest first.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Trip>  $trips
     * @return \Illuminate\Support\Collection<int, Trip>
     */
    private function upcomingTrips($trips)
    {
        $today = Carbon::today();

        return $trips
            ->filter(fn (Trip $trip) => $trip->start_date && $trip->start_date->greaterThanOrEqualTo($today))
            ->sortBy('start_date')
            ->take(5)
            ->values();
    }
}
