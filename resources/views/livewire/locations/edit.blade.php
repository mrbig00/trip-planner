<?php

use App\Models\Location;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public Trip $trip;
    public Location $location;

    public string $name = '';
    public ?string $price = null;
    public ?string $latitude = null;
    public ?string $longitude = null;
    public ?string $link = null;
    public ?string $picture = null;

    public function with(): array
    {
        return ['title' => __('Edit Location')];
    }

    /**
     * Mount the component.
     */
    public function mount(Trip $trip, Location $location): void
    {
        if ($trip->user_id !== Auth::id()) {
            abort(403);
        }

        if ($location->trip_id !== $trip->id) {
            abort(404);
        }

        $this->trip = $trip;
        $this->location = $location;
        $this->name = $location->name;
        $this->price = $location->price ? (string) $location->price : null;
        $this->latitude = $location->latitude ? (string) $location->latitude : null;
        $this->longitude = $location->longitude ? (string) $location->longitude : null;
        $this->link = $location->link;
        $this->picture = $location->picture;
    }

    /**
     * Update the location.
     */
    public function update(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'link' => ['nullable', 'url', 'max:255'],
            'picture' => ['nullable', 'url', 'max:255'],
        ]);

        $this->location->update($validated);

        $this->redirect(route('trips.show', $this->trip), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Edit Location') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Update location in') }} "{{ $trip->name }}"
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="update" class="space-y-6">
            <flux:field>
                <flux:input
                    wire:model="name"
                    :label="__('Location Name')"
                    :placeholder="__('e.g., Paris, France')"
                    required
                    autofocus
                />
            </flux:field>

            <flux:field>
                <flux:input
                    wire:model="price"
                    type="number"
                    step="0.01"
                    :label="__('Price')"
                    :placeholder="__('0.00')"
                />
            </flux:field>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:input
                        wire:model="latitude"
                        type="number"
                        step="0.000001"
                        :label="__('Latitude')"
                        :placeholder="__('e.g., 48.8566')"
                    />
                </flux:field>

                <flux:field>
                    <flux:input
                        wire:model="longitude"
                        type="number"
                        step="0.000001"
                        :label="__('Longitude')"
                        :placeholder="__('e.g., 2.3522')"
                    />
                </flux:field>
            </div>

            <flux:field>
                <flux:input
                    wire:model="link"
                    type="url"
                    :label="__('Link')"
                    :placeholder="__('https://example.com')"
                />
            </flux:field>

            <flux:field>
                <flux:input
                    wire:model="picture"
                    type="url"
                    :label="__('Picture URL')"
                    :placeholder="__('https://example.com/image.jpg')"
                />
            </flux:field>

            <flux:separator />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                    <span wire:loading.remove wire:target="update">{{ __('Update Location') }}</span>
                    <span wire:loading wire:target="update">{{ __('Updating...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.show', $trip)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
