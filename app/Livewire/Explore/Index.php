<?php

declare(strict_types=1);

namespace App\Livewire\Explore;

use Livewire\Component;
use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    /**
     * Get every located pin (has coordinates) across every trip the
     * authenticated user created or participates in, each with its trip
     * eager-loaded so the map widget can link/identify without N+1 queries.
     *
     * @return Collection<int, Location>
     */
    private function locations(): Collection
    {
        return Location::query()
            ->whereHas('trip', fn ($query) => $query->visibleTo(Auth::id()))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('trip')
            ->get();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.explore.index', [
            'title' => __('Explore'),
            'locations' => $this->locations(),
        ]);
    }
}
