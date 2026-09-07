<?php

declare(strict_types=1);

namespace App\Livewire\Activity;

use App\Models\Trip;
use App\Models\Expense;
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
     * Deleted expenses are fetched once for every visible trip up front and
     * handed to build() per trip, rather than letting each call run its own
     * onlyTrashed() query — build() only does that itself as a single-trip
     * fallback (see its docblock), and with potentially dozens of visible
     * trips here (refreshed every 30s via wire:poll), that fallback would
     * turn into one extra query per trip on every request.
     *
     * @return Collection<int, array{type: string, at: \Illuminate\Support\Carbon, user: ?\App\Models\User, text: string, trip: Trip}>
     */
    private function events(): Collection
    {
        $trips = Trip::query()
            ->visibleTo(Auth::id())
            // No expenses.shares here: BuildActivityFeed never reads shares,
            // only total/owner/createdBy/updated_by/deleted_by — Trips\Show's
            // own eager-load needs shares for its balances/cost-breakdown
            // features, this page doesn't share that need.
            ->with(['creator', 'participants', 'locations.votes', 'locations.comments.user', 'expenses.owner', 'expenses.createdBy', 'settlements'])
            ->get();

        $trashedExpensesByTrip = Expense::onlyTrashed()
            ->whereIn('trip_id', $trips->pluck('id'))
            ->get()
            ->groupBy('trip_id');

        $buildActivityFeed = app(BuildActivityFeed::class);

        return $trips
            ->flatMap(function (Trip $trip) use ($buildActivityFeed, $trashedExpensesByTrip) {
                $trashedExpenses = $trashedExpensesByTrip->get($trip->id, collect());

                return $buildActivityFeed->build($trip, $trashedExpenses)->map(function (array $event) use ($trip) {
                    $event['trip'] = $trip;

                    return $event;
                });
            })
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
