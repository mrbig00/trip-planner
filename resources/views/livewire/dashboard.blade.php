<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('An overview of your trips, spending, and destinations') }}
        </flux:subheading>
    </div>

    @if ($trips->isEmpty())
        <flux:callout variant="subtle">
            <flux:text class="text-center">
                {{ __("You haven't created or joined any trips yet.") }}
            </flux:text>
            <flux:button variant="primary" :href="route('trips.create')" wire:navigate class="mt-4">
                {{ __('Create Your First Trip') }}
            </flux:button>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-400">
                        <flux:icon name="map" class="size-5" />
                    </div>
                    <flux:text class="text-neutral-400">{{ __('Total Trips') }}</flux:text>
                </div>
                <div class="mt-4 text-3xl font-semibold text-white">{{ $stats['totalTrips'] }}</div>
            </div>

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-400">
                        <flux:icon name="calendar" class="size-5" />
                    </div>
                    <flux:text class="text-neutral-400">{{ __('Upcoming Trips') }}</flux:text>
                </div>
                <div class="mt-4 text-3xl font-semibold text-white">{{ $stats['upcomingTrips'] }}</div>
            </div>

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-400">
                        <flux:icon name="currency-dollar" class="size-5" />
                    </div>
                    <flux:text class="text-neutral-400">{{ __('Total Spend') }}</flux:text>
                </div>
                @if (empty($stats['totalSpendByCurrency']))
                    <div class="mt-4 text-3xl font-semibold text-white">{{ \App\Support\Money::format(0, \App\Enums\Currency::default()) }}</div>
                @elseif (count($stats['totalSpendByCurrency']) === 1)
                    <div class="mt-4 text-3xl font-semibold text-white">
                        {{ \App\Support\Money::formatDecimal((string) reset($stats['totalSpendByCurrency']), array_key_first($stats['totalSpendByCurrency'])) }}
                    </div>
                @else
                    <div class="mt-4 space-y-0.5">
                        @foreach ($stats['totalSpendByCurrency'] as $currency => $amount)
                            <div class="text-xl font-semibold text-white">{{ \App\Support\Money::formatDecimal((string) $amount, $currency) }}</div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-400">
                        <flux:icon name="map-pin" class="size-5" />
                    </div>
                    <flux:text class="text-neutral-400">{{ __('Total Destinations') }}</flux:text>
                </div>
                <div class="mt-4 text-3xl font-semibold text-white">{{ $stats['acceptedDestinations'] + $stats['proposedDestinations'] }}</div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
            @php
                $totalDestinations = $stats['acceptedDestinations'] + $stats['proposedDestinations'];
                $acceptedShare = $totalDestinations > 0 ? round(($stats['acceptedDestinations'] / $totalDestinations) * 100) : 0;
            @endphp
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Destination Decisions') }}</flux:heading>
                <flux:text class="text-neutral-400">
                    {{ $stats['acceptedDestinations'] }} / {{ $totalDestinations }} {{ __('accepted') }}
                </flux:text>
            </div>
            <div
                class="mt-4 h-3 w-full overflow-hidden rounded-full bg-blue-500/15"
                role="progressbar"
                aria-valuenow="{{ $acceptedShare }}"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="{{ __('Destinations accepted') }}"
            >
                <div class="h-full rounded-full bg-blue-500" style="width: {{ $acceptedShare }}%"></div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <flux:heading size="lg">{{ __('Trips Created') }}</flux:heading>
                <flux:subheading>{{ __('Last 12 months') }}</flux:subheading>
                <div
                    class="relative mt-4 h-64"
                    x-data="barChart({ labels: @js($tripsPerMonth['labels']), data: @js($tripsPerMonth['data']) })"
                >
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <flux:heading size="lg">{{ __('Spend by Trip') }}</flux:heading>
                <flux:subheading>{{ __('Highest-spending trips') }}</flux:subheading>
                @if (count($spendByTrip['labels']) > 0)
                    <div
                        class="relative mt-4 h-64"
                        x-data="barChart({ labels: @js($spendByTrip['labels']), data: @js($spendByTrip['data']), horizontal: true })"
                    >
                        <canvas x-ref="canvas"></canvas>
                    </div>
                @else
                    <div class="mt-4 flex h-64 items-center justify-center">
                        <flux:text class="text-neutral-400">{{ __('No expenses recorded yet.') }}</flux:text>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
            <flux:heading size="lg">{{ __('Upcoming Trips') }}</flux:heading>
            <div class="mt-4 flex flex-col gap-3">
                @forelse ($upcomingTrips as $trip)
                    <div wire:key="upcoming-{{ $trip->id }}" class="flex items-center justify-between gap-4 rounded-lg border border-neutral-700 bg-neutral-800/50 px-4 py-3">
                        <div>
                            <flux:link :href="route('trips.show', $trip)" wire:navigate class="text-white hover:text-neutral-200">
                                {{ $trip->name }}
                            </flux:link>
                            <flux:text class="mt-1 text-neutral-400">
                                {{ $trip->start_date->format('M j, Y') }}
                            </flux:text>
                        </div>
                        <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                            {{ $trip->start_date->diffForHumans() }}
                        </flux:badge>
                    </div>
                @empty
                    <flux:text class="text-neutral-400">
                        {{ __('No upcoming trips with a start date yet.') }}
                    </flux:text>
                @endforelse
            </div>
        </div>
    @endif
</div>
