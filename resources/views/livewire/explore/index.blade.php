<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Explore') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Every located destination across all your trips — click a pin to open its trip') }}
        </flux:subheading>
    </div>

    @if ($locations->isEmpty())
        <flux:callout variant="subtle">
            <flux:text class="text-center">
                {{ __("None of your locations have coordinates yet — add a location with a latitude/longitude to see it here.") }}
            </flux:text>
            <flux:button variant="primary" :href="route('trips.index')" wire:navigate class="mt-4">
                {{ __('Go to Trips') }}
            </flux:button>
        </flux:callout>
    @else
        <div class="overflow-x-auto">
            <x-locations-map-widget :locations="$locations" :width="900" :height="600" />
        </div>
        <flux:text class="text-xs text-neutral-500">
            {{ __('Map thumbnails © OpenStreetMap contributors') }}
        </flux:text>
    @endif
</div>
