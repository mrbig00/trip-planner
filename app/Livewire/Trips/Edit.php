<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Edit extends Component
{
    public Trip $trip;
    public string $name = '';
    public string $description = '';

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        if ($trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->trip = $trip;
        $this->name = $trip->name;
        $this->description = $trip->description ?? '';
    }

    /**
     * Update the trip.
     */
    public function update(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->trip->update($validated);

        $this->redirect(route('trips.show', $this->trip), navigate: true);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.trips.edit', [
            'title' => __('Edit Trip'),
        ]);
    }
}
