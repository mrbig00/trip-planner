<?php

declare(strict_types=1);

namespace App\Livewire\Budgets;

use App\Models\Trip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * Get every trip the authenticated user created or participates in,
     * ranked so the trips closest to (or furthest over) their budget surface
     * first. Trips with no budget set sort last, grouped together.
     *
     * @return Collection<int, Trip>
     */
    private function trips(): Collection
    {
        return Trip::query()
            ->where(function ($query) {
                $query->where('user_id', Auth::id())
                    ->orWhereHas('participants', function ($q) {
                        $q->where('user_id', Auth::id());
                    });
            })
            ->with('expenses')
            ->get()
            ->sortByDesc(fn (Trip $trip) => $trip->budget_summary['percentRaw'] ?? -1)
            ->values();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.budgets.index', [
            'title' => __('Budgets'),
            'trips' => $this->trips(),
        ]);
    }
}
