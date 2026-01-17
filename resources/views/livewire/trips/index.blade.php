<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('My Trips') }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ __('Plan and manage your trips with friends') }}
            </flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('trips.create')" wire:navigate>
            {{ __('New Trip') }}
        </flux:button>
    </div>

    <flux:field>
        <flux:input
            wire:model.live.debounce.300ms="search"
            :placeholder="__('Search trips...')"
            class="max-w-md"
        />
    </flux:field>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->trips() as $trip)
            <div wire:key="trip-{{ $trip->id }}" class="group flex flex-col gap-4 rounded-xl border border-neutral-700 bg-neutral-800/50 p-6 transition-all hover:border-neutral-600 hover:bg-neutral-800">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <flux:heading size="lg">
                            <flux:link :href="route('trips.show', $trip)" wire:navigate class="text-white hover:text-neutral-200">
                                {{ $trip->name }}
                            </flux:link>
                        </flux:heading>
                        @if ($trip->description)
                            <flux:text class="mt-2 text-neutral-300 line-clamp-2">
                                {{ $trip->description }}
                            </flux:text>
                        @endif
                    </div>
                    @if ($trip->user_id === Auth::id())
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon-only class="text-neutral-400 hover:text-white">
                                <flux:icon.chevrons-up-down />
                            </flux:button>
                            <flux:menu>
                                <flux:menu.item :href="route('trips.edit', $trip)" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $trip->id }})" wire:confirm="{{ __('Are you sure you want to delete this trip?') }}">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                        {{ __('By') }} {{ $trip->creator->fullName() }}
                    </flux:badge>
                    @if ($trip->participants->count() > 0)
                        <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                            {{ $trip->participants->count() }} {{ __('participants') }}
                        </flux:badge>
                    @endif
                    @if ($trip->locations->count() > 0)
                        <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                            {{ $trip->locations->count() }} {{ __('locations') }}
                        </flux:badge>
                    @endif
                    @if ($trip->expenses->count() > 0)
                        <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                            {{ $trip->expenses->count() }} {{ __('expenses') }}
                        </flux:badge>
                    @endif
                </div>

                <div class="mt-auto pt-2">
                    <flux:button variant="ghost" size="sm" :href="route('trips.show', $trip)" wire:navigate class="w-full text-neutral-300 hover:text-white hover:bg-neutral-700/50">
                        {{ __('View Details') }}
                    </flux:button>
                </div>
            </div>
        @empty
            <flux:callout class="col-span-full" variant="subtle">
                <flux:text class="text-center">
                    {{ __('No trips found.') }}
                </flux:text>
                <flux:button variant="primary" :href="route('trips.create')" wire:navigate class="mt-4">
                    {{ __('Create Your First Trip') }}
                </flux:button>
            </flux:callout>
        @endforelse
    </div>
</div>
