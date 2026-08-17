@php
    $activityIcon = fn ($type) => \App\Actions\Trips\BuildActivityFeed::iconFor($type);
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6" wire:poll.30s>
    <div>
        <flux:heading size="xl">{{ __('Activity') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('What\'s happened across all your trips, newest first') }}
        </flux:subheading>
    </div>

    @if ($events->isEmpty())
        <flux:callout variant="subtle">
            <flux:text class="text-center">
                {{ __("Nothing's happened yet — comments, votes, expenses, and settlements will show up here.") }}
            </flux:text>
            <flux:button variant="primary" :href="route('trips.create')" wire:navigate class="mt-4">
                {{ __('Create Your First Trip') }}
            </flux:button>
        </flux:callout>
    @else
        <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
            <div class="space-y-3">
                @foreach ($events as $event)
                    <div wire:key="activity-{{ $loop->index }}" class="flex items-center gap-3">
                        @if ($event['user'])
                            <x-participant-avatar
                                :name="$event['user']->fullName()"
                                :initials="$event['user']->initials()"
                                :color-slot="$event['trip']->colorSlotFor($event['user'])"
                                size="xs"
                            />
                        @else
                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-700/50 shrink-0">
                                <flux:icon :icon="$activityIcon($event['type'])" class="h-3.5 w-3.5 text-neutral-400" />
                            </div>
                        @endif
                        <flux:text class="text-sm flex-1">{{ $event['text'] }}</flux:text>
                        <flux:link :href="route('trips.show', $event['trip'])" wire:navigate class="shrink-0">
                            <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300 hover:bg-neutral-700">
                                {{ $event['trip']->name }}
                            </flux:badge>
                        </flux:link>
                        <flux:text class="text-xs text-neutral-500 shrink-0">{{ $event['at']->diffForHumans() }}</flux:text>
                    </div>
                @endforeach
            </div>

            @if ($events->count() >= $maxEvents)
                <flux:text class="mt-4 block text-xs text-neutral-500">
                    {{ __('Showing the :count most recent events.', ['count' => $maxEvents]) }}
                </flux:text>
            @endif
        </div>
    @endif
</div>
