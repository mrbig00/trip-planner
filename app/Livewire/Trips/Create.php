<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class Create extends Component
{
    public string $name = '';

    public string $description = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $budget = null;

    /**
     * Create a new trip.
     */
    public function store(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $trip = Trip::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        $this->redirect(route('trips.show', $trip), navigate: true);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.trips.create', [
            'title' => __('Create Trip'),
        ]);
    }
}
