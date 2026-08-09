<?php

declare(strict_types=1);

namespace App\Livewire\People;

use App\Models\Trip;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * Get every unique person the authenticated user has traveled with,
     * each with the trips shared and the net amount owed between the two of
     * them, aggregated across every shared trip.
     *
     * The net figure is a genuine bilateral ledger, not an inference from
     * the group-wide minimal-transaction settlement plan: for each expense,
     * whoever paid is directly owed by each of their (non-payer) sharers,
     * and each recorded settlement directly adjusts the balance between
     * exactly the two people on it. Reusing the settlement plan here (as an
     * earlier version of this method did) was tried and reverted — on a
     * trip with 3+ distinct balances, the plan's minimal pairing is chosen
     * by array order, not by who actually transacted with whom, so it can
     * attribute a debt to the wrong companion entirely (not just show $0
     * for a real one). This ledger-based sum has no such ambiguity: a $0
     * result only ever means these two people had no direct expense or
     * settlement together on that trip, which is simply true.
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

            foreach ($others as $other) {
                $entry = $companions->get($other->id) ?? [
                    'user' => $other,
                    'trips' => collect(),
                    'netCents' => 0,
                ];

                $entry['trips']->put($trip->id, $trip);
                $entry['netCents'] += $this->bilateralNetCents($trip, $userId, $other->id);

                $companions->put($other->id, $entry);
            }
        }

        return $companions->sortBy(fn (array $entry) => $entry['user']->fullName())->values();
    }

    /**
     * Net cents owed between exactly $userId and $otherId on one trip:
     * positive means $otherId owes $userId, negative means the reverse.
     * Requires `expenses.shares` and `settlements` to be eager-loaded.
     */
    private function bilateralNetCents(Trip $trip, int $userId, int $otherId): int
    {
        $netCents = 0;

        foreach ($trip->expenses as $expense) {
            $payerId = $expense->user_id;

            if ($payerId === null) {
                continue;
            }

            foreach ($expense->shares as $share) {
                if ($share->user_id === $payerId) {
                    continue; // paying your own share isn't a debt to anyone
                }

                if ($payerId === $userId && $share->user_id === $otherId) {
                    $netCents += Money::toCents((string) $share->amount);
                } elseif ($payerId === $otherId && $share->user_id === $userId) {
                    $netCents -= Money::toCents((string) $share->amount);
                }
            }
        }

        foreach ($trip->settlements as $settlement) {
            if ($settlement->from_user_id === $otherId && $settlement->to_user_id === $userId) {
                $netCents -= $settlement->amount_cents;
            } elseif ($settlement->from_user_id === $userId && $settlement->to_user_id === $otherId) {
                $netCents += $settlement->amount_cents;
            }
        }

        return $netCents;
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
