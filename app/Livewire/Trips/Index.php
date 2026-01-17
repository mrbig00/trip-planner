<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';

    /**
     * Get the trips for the authenticated user.
     *
     * @return Collection
     */
    public function trips()
    {
        return Trip::query()
            ->where(function ($query) {
                $query->where('user_id', Auth::id())
                    ->orWhereHas('participants', function ($q) {
                        $q->where('user_id', Auth::id());
                    });
            })
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->with(['creator', 'participants', 'locations', 'expenses'])
            ->latest()
            ->get();
    }

    /**
     * Delete a trip.
     */
    public function delete(Trip $trip): void
    {
        if ($trip->user_id !== Auth::id()) {
            abort(403);
        }

        $trip->delete();
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.trips.index', [
            'title' => __('My Trips'),
        ]);
    }
}
