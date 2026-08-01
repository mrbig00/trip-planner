<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Trip;
use Livewire\Component;
use App\Models\Location;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;

class Dashboard extends Component
{
    /**
     * Render the component.
     */
    public function render(): View
    {
        $trips = $this->trips();
        $locations = $this->locations($trips);
        $upcomingTrips = $this->upcomingTrips($trips);

        return view('livewire.dashboard', [
            'title' => __('Dashboard'),
            'trips' => $trips,
            'stats' => $this->stats($trips, $locations, $upcomingTrips),
            'tripsPerMonth' => $this->tripsPerMonth($trips),
            'spendByTrip' => $this->spendByTrip($trips),
            'upcomingTrips' => $upcomingTrips->take(5)->values(),
        ]);
    }

    /**
     * Get the trips the authenticated user created or participates in.
     *
     * @return Collection<int, Trip>
     */
    private function trips(): Collection
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
     * All locations across the given trips.
     *
     * @param  Collection<int, Trip>  $trips
     * @return \Illuminate\Support\Collection<int, Location>
     */
    private function locations(Collection $trips): \Illuminate\Support\Collection
    {
        return $trips->flatMap->locations;
    }

    /**
     * Trips with a future start date, soonest first.
     *
     * @param  Collection<int, Trip>  $trips
     * @return \Illuminate\Support\Collection<int, Trip>
     */
    private function upcomingTrips(Collection $trips): \Illuminate\Support\Collection
    {
        $today = Carbon::today();

        return $trips
            ->filter(fn (Trip $trip) => $trip->start_date && $trip->start_date->greaterThanOrEqualTo($today))
            ->sortBy('start_date')
            ->values();
    }

    /**
     * Top-line stat tiles.
     *
     * @param  Collection<int, Trip>  $trips
     * @param  \Illuminate\Support\Collection<int, Location>  $locations
     * @param  \Illuminate\Support\Collection<int, Trip>  $upcomingTrips
     * @return array<string, mixed>
     */
    private function stats(Collection $trips, \Illuminate\Support\Collection $locations, \Illuminate\Support\Collection $upcomingTrips): array
    {
        return [
            'totalTrips' => $trips->count(),
            'upcomingTrips' => $upcomingTrips->count(),
            'totalSpend' => $trips->flatMap->expenses->sum('total'),
            'acceptedDestinations' => $locations->where('accepted', true)->count(),
            'proposedDestinations' => $locations->where('accepted', false)->count(),
        ];
    }

    /**
     * Number of trips created per month for the last 12 months.
     *
     * @param  Collection<int, Trip>  $trips
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    private function tripsPerMonth(Collection $trips): array
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
     * @param  Collection<int, Trip>  $trips
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    private function spendByTrip(Collection $trips): array
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
}
