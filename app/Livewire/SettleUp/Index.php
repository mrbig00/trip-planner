<?php

declare(strict_types=1);

namespace App\Livewire\SettleUp;

use App\Actions\Expenses\BuildBalanceSummary;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * Get every trip the authenticated user created or participates in.
     *
     * @return Collection<int, Trip>
     */
    private function trips(): Collection
    {
        return Trip::query()
            ->visibleTo(Auth::id())
            ->with(['creator', 'participants', 'expenses.shares', 'settlements'])
            ->get();
    }

    /**
     * Net every member's balance across all of the given trips combined,
     * keyed by user_id => signed cents. Summing a person's balance here
     * across all their trips always matches summing their balance on each
     * trip's own detail page, since this is literally the same per-trip
     * `Trip::balances()` figures added together — not re-derived some other
     * way.
     *
     * @param  Collection<int, Trip>  $trips
     * @return Collection<int, int>
     */
    private function combinedBalances(Collection $trips): Collection
    {
        $totals = [];

        foreach ($trips as $trip) {
            foreach ($trip->balances() as $userId => $cents) {
                $totals[$userId] = ($totals[$userId] ?? 0) + $cents;
            }
        }

        return collect($totals);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $trips = $this->trips();
        $balancesInCents = $this->combinedBalances($trips);
        $users = User::query()->whereIn('id', $balancesInCents->keys())->get()->keyBy('id');

        $summary = app(BuildBalanceSummary::class)->build($balancesInCents, $users);

        return view('livewire.settle-up.index', [
            'title' => __('Settle Up'),
            'tripCount' => $trips->count(),
            'balances' => $summary['balances']->sortByDesc('balanceCents')->values(),
            'transfers' => $summary['transfers'],
        ]);
    }
}
