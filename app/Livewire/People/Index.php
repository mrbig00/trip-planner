<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Actions\Expenses\CalculateSettlementPlan;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * Get every unique person the authenticated user has traveled with,
     * each with the trips shared and the net amount owed between the two of
     * them, aggregated across every shared trip. The net figure sums the
     * actual settlement-plan transfers between the two of them per trip
     * (the same minimal-transaction algorithm the Settle Up card uses) —
     * positive means they owe the user, negative means the user owes them.
     * A trip's minimal plan may route a debt through a third member instead
     * of a direct transfer between the two of them, in which case that trip
     * contributes $0 to the pair's net even though both had some imbalance.
     *
     * @return Collection<int, array{user: User, trips: Collection<int, Trip>, netCents: int}>
     */
    private function companions(): Collection
    {
        $userId = Auth::id();

        $trips = Trip::query()
            ->visibleTo($userId)
            ->with(['creator', 'participants', 'expenses.shares', 'settlements'])
            ->get();

        $companions = collect();

        foreach ($trips as $trip) {
            $others = $trip->members()->reject(fn (User $member) => $member->id === $userId);

            if ($others->isEmpty()) {
                continue;
            }

            $transfers = app(CalculateSettlementPlan::class)->calculate($trip->balances());

            foreach ($others as $other) {
                $entry = $companions->get($other->id) ?? [
                    'user' => $other,
                    'trips' => collect(),
                    'netCents' => 0,
                ];

                $entry['trips']->put($trip->id, $trip);

                foreach ($transfers as $transfer) {
                    if ($transfer['from'] === $other->id && $transfer['to'] === $userId) {
                        $entry['netCents'] += $transfer['amount'];
                    } elseif ($transfer['from'] === $userId && $transfer['to'] === $other->id) {
                        $entry['netCents'] -= $transfer['amount'];
                    }
                }

                $companions->put($other->id, $entry);
            }
        }

        return $companions->sortBy(fn (array $entry) => $entry['user']->fullName())->values();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.people.index', [
            'title' => __('People'),
            'companions' => $this->companions(),
        ]);
    }
}
