<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use App\Enums\Currency;
use Livewire\Component;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Concerns\TracksAnalyticsEvents;

class Edit extends Component
{
    use TracksAnalyticsEvents;

    public Trip $trip;

    public string $name = '';

    public string $description = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $budget = null;

    public string $currency = '';

    /**
     * Once a trip has an expense, its currency is baked into every expense
     * that has no exchange_rate (null there means "same as the trip's
     * currency" — see Expense::convertToTripCurrencyCents()). Changing the
     * trip's currency afterward would silently reinterpret those expenses'
     * amounts as being in the new currency, so the field is locked as soon
     * as the trip has its first expense.
     */
    public bool $currencyLocked = false;

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
        $this->currency = ($trip->currency ?? Currency::default())->value;
        $this->currencyLocked = $trip->expenses()->exists();
    }

    /**
     * Update the trip.
     */
    public function update(): void
    {
        if ($this->budget === '') {
            $this->budget = null;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
        ]);

        // $currencyLocked is a plain public Livewire property — a tampered
        // request could set it to false and slip a currency change past the
        // disabled control in the view, so the lock is re-checked here
        // against the trip's actual persisted expenses, never trusted from
        // the client.
        if ($this->trip->expenses()->exists()) {
            $validated['currency'] = $this->trip->currency->value;
        }

        $this->trip->update($validated);

        $this->trackEvent('trip_updated', ['trip_id' => $this->trip->id]);

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
