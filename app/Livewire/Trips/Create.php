<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';
    public string $description = '';

    /**
     * Create a new trip.
     */
    public function store(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
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
