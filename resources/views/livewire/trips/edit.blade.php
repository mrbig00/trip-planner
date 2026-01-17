<?php

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public Trip $trip;
    public string $name = '';
    public string $description = '';

    public function with(): array
    {
        return ['title' => __('Edit Trip')];
    }

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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Edit Trip') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Update trip details') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="update" class="space-y-6">
            <flux:field>
                <flux:input
                    wire:model="name"
                    :label="__('Trip Name')"
                    :placeholder="__('e.g., Summer Vacation 2025')"
                    required
                    autofocus
                />
            </flux:field>

            <flux:field>
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Tell us about your trip...')"
                    rows="5"
                />
            </flux:field>

            <flux:separator />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                    <span wire:loading.remove wire:target="update">{{ __('Update Trip') }}</span>
                    <span wire:loading wire:target="update">{{ __('Updating...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.show', $trip)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
