<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class Edit extends Component
{
    public Trip $trip;

    public string $name = '';

    public string $description = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $budget = null;

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
        $this->start_date = $trip->start_date?->toDateString();
        $this->end_date = $trip->end_date?->toDateString();
        $this->budget = $trip->budget !== null ? (string) $trip->budget : null;
    }

    /**
     * Update the trip.
     */
    public function update(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
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
