<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Trip;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Actions\Trips\BuildActivityFeed;

class Index extends Component
{
    /**
     * Cap on how many events are shown — a personal activity feed, not an
     * unbounded audit log. Sorting is newest-first, so this only ever drops
     * the oldest tail.
     */
    private const MAX_EVENTS = 50;

    /**
     * Get every event across every trip the authenticated user created or
     * participates in, newest first, each tagged with the trip it belongs
     * to. Reuses BuildActivityFeed — the same event set the per-trip
     * "Recent Activity" card shows — just merged across trips instead of
     * scoped to one.
     *
     * @return Collection<int, array{type: string, at: \Illuminate\Support\Carbon, user: ?\App\Models\User, text: string, trip: Trip}>
     */
    private function events(): Collection
    {
        $trips = Trip::query()
            ->visibleTo(Auth::id())
            ->with(['creator', 'participants', 'locations.votes', 'locations.comments.user', 'expenses.owner', 'expenses.shares', 'settlements'])
            ->get();

        $buildActivityFeed = app(BuildActivityFeed::class);

        return $trips
            ->flatMap(fn (Trip $trip) => $buildActivityFeed->build($trip)->map(function (array $event) use ($trip) {
                $event['trip'] = $trip;

                return $event;
            }))
            ->sortByDesc('at')
            ->take(self::MAX_EVENTS)
            ->values();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.activity.index', [
            'title' => __('Activity'),
            'events' => $this->events(),
            'maxEvents' => self::MAX_EVENTS,
        ]);
    }
}
