<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Budgets') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Spend against budget across every trip, closest to (or over) budget first') }}
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
        <div class="flex flex-col gap-3">
            @foreach ($trips as $trip)
                <div wire:key="budget-trip-{{ $trip->id }}" class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <flux:heading size="lg">
                                <flux:link :href="route('trips.show', $trip)" wire:navigate class="text-white hover:text-neutral-200">
                                    {{ $trip->name }}
                                </flux:link>
                            </flux:heading>
                            <flux:text class="mt-1 text-sm text-neutral-400">
                                {{ __('By') }} {{ $trip->creator->fullName() }}
                            </flux:text>
                        </div>

                        @if ($summary = $trip->budget_summary)
                            <div class="shrink-0 text-right">
                                @if ($summary['overBudget'])
                                    <flux:text class="text-sm font-semibold text-red-400">
                                        ${{ number_format(abs($summary['remaining']), 2) }} {{ __('over budget') }}
                                    </flux:text>
                                @else
                                    <flux:text class="text-sm text-neutral-300">
                                        ${{ number_format($summary['remaining'], 2) }} {{ __('remaining') }}
                                    </flux:text>
                                @endif
                                <flux:text class="mt-0.5 block text-xs text-neutral-500">
                                    ${{ number_format($summary['spent'], 2) }} / ${{ number_format($summary['budget'], 2) }}
                                </flux:text>
                            </div>
                        @else
                            <flux:badge variant="ghost" size="sm" class="shrink-0 bg-neutral-700/50 text-neutral-400">
                                {{ __('No budget set') }}
                            </flux:badge>
                        @endif
                    </div>

                    @if ($summary)
                        <div class="mt-4 h-2 rounded-full bg-neutral-700/50 overflow-hidden">
                            <div
                                class="h-full rounded-full {{ $summary['overBudget'] ? 'bg-red-500' : '' }}"
                                style="width: {{ $summary['percentUsed'] }}%;{{ $summary['overBudget'] ? '' : ' background-color: var(--color-money-4);' }}"
                                role="progressbar"
                                aria-valuenow="{{ (int) round($summary['percentUsed']) }}"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-label="{{ __(':trip budget used', ['trip' => $trip->name]) }}"
                            ></div>
                        </div>
                    @else
                        <flux:text class="mt-4 block text-xs text-neutral-500">
                            {{ __('Spent so far') }}: ${{ number_format($trip->total_spent, 2) }}
                        </flux:text>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
