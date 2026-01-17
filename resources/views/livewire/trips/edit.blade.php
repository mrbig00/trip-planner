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
