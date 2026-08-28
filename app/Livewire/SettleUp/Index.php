<?php

declare(strict_types=1);

namespace App\Livewire\SettleUp;

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Actions\Expenses\BuildBalanceSummary;

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
     * grouped by each trip's own currency (different trips can use different
     * currencies — summing across them would otherwise silently add, say,
     * USD and EUR as if they were equal). Within a currency group, summing a
     * person's balance here across all their trips in that currency always
     * matches summing their balance on each trip's own detail page, since
     * this is literally the same per-trip `Trip::balances()` figures added
     * together — not re-derived some other way.
     *
     * @param  Collection<int, Trip>  $trips
     * @return Collection<string, Collection<int, int>> currency code => (user_id => signed cents)
     */
    private function combinedBalancesByCurrency(Collection $trips): Collection
    {
        $totals = [];

        foreach ($trips as $trip) {
            $currency = ($trip->currency ?? Currency::default())->value;

            foreach ($trip->balances() as $userId => $cents) {
                $totals[$currency][$userId] = ($totals[$currency][$userId] ?? 0) + $cents;
            }
        }

        return collect($totals)->map(fn (array $perUser) => collect($perUser));
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        $trips = $this->trips();
        $balancesByCurrency = $this->combinedBalancesByCurrency($trips);

        $allUserIds = $balancesByCurrency->flatMap(fn (Collection $balances) => $balances->keys())->unique();
        $users = User::query()->whereIn('id', $allUserIds)->get()->keyBy('id');

        $groups = $balancesByCurrency->map(function (Collection $balancesInCents, string $currency) use ($users) {
            $summary = app(BuildBalanceSummary::class)->build($balancesInCents, $users);

            return [
                'currency' => $currency,
                'balances' => $summary['balances']->sortByDesc('balanceCents')->values(),
                'transfers' => $summary['transfers'],
            ];
        })->sortKeys()->values();

        return view('livewire.settle-up.index', [
            'title' => __('Settle Up'),
            'tripCount' => $trips->count(),
            'groups' => $groups,
        ]);
    }
}
