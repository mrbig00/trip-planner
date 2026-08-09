@php use Illuminate\Support\Str; @endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('People') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Everyone you\'ve traveled with, across every trip') }}
        </flux:subheading>
    </div>

    @if ($companions->isEmpty())
        <flux:callout variant="subtle">
            <flux:text class="text-center">
                {{ __("You haven't shared a trip with anyone yet.") }}
            </flux:text>
            <flux:button variant="primary" :href="route('trips.create')" wire:navigate class="mt-4">
                {{ __('Create Your First Trip') }}
            </flux:button>
        </flux:callout>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($companions as $companion)
                <div wire:key="companion-{{ $companion['user']->id }}" class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <x-participant-avatar
                                :name="$companion['user']->fullName()"
                                :initials="$companion['user']->initials()"
                                size="md"
                            />
                            <div class="min-w-0">
                                <flux:heading size="lg">{{ $companion['user']->fullName() }}</flux:heading>
                                <flux:text class="text-sm text-neutral-400">
                                    {{ $companion['trips']->count() }} {{ __(Str::plural('trip', $companion['trips']->count())) }} {{ __('together') }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="shrink-0 text-right">
                            @if ($companion['netCents'] > 0)
                                <flux:text class="text-sm font-semibold text-green-400">
                                    ${{ number_format($companion['netCents'] / 100, 2) }} {{ __('owed to you') }}
                                </flux:text>
                            @elseif ($companion['netCents'] < 0)
                                <flux:text class="text-sm font-semibold text-red-400">
                                    ${{ number_format(abs($companion['netCents']) / 100, 2) }} {{ __('you owe') }}
                                </flux:text>
                            @else
                                <flux:text class="text-sm text-neutral-400">{{ __('Settled up') }}</flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($companion['trips'] as $trip)
                            <flux:link :href="route('trips.show', $trip)" wire:navigate class="hover:no-underline">
                                <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300 hover:bg-neutral-700">
                                    {{ $trip->name }}
                                </flux:badge>
                            </flux:link>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
