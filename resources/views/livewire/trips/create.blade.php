<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Create New Trip') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Plan a new trip with your friends') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="store" class="space-y-6">
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
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="store">
                    <span wire:loading.remove wire:target="store">{{ __('Create Trip') }}</span>
                    <span wire:loading wire:target="store">{{ __('Creating...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
