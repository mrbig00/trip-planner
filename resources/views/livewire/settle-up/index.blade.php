@php use Illuminate\Support\Str; use App\Support\Money; @endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Settle Up') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Net balances and suggested transfers, netted across :count :trips combined', ['count' => $tripCount, 'trips' => Str::plural(__('trip'), $tripCount)]) }}
        </flux:subheading>
    </div>

    @if ($groups->isEmpty())
        <flux:callout variant="subtle">
            <flux:text class="text-center">
                {{ __("You haven't shared a trip with anyone yet.") }}
            </flux:text>
            <flux:button variant="primary" :href="route('trips.create')" wire:navigate class="mt-4">
                {{ __('Create Your First Trip') }}
            </flux:button>
        </flux:callout>
    @else
        @foreach ($groups as $group)
            @php $balances = $group['balances']; $transfers = $group['transfers']; $currency = $group['currency']; @endphp

            @if ($groups->count() > 1)
                <flux:subheading class="-mb-2">{{ $currency }}</flux:subheading>
            @endif

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <flux:subheading class="mb-3">{{ __('Balances') }}</flux:subheading>
                <div class="flex flex-wrap gap-3">
                    @foreach ($balances as $balance)
                        <div wire:key="balance-{{ $balance['user']->id }}-{{ $currency }}" class="flex items-center gap-2 p-2 rounded-lg border border-neutral-700">
                            <x-participant-avatar :name="$balance['user']->fullName()" :initials="$balance['user']->initials()" size="sm" />
                            <flux:text class="text-sm">{{ $balance['user']->fullName() }}</flux:text>
                            @if ($balance['balanceCents'] > 0)
                                <flux:badge color="green">{{ __('is owed') }} {{ Money::format($balance['balanceCents'], $currency) }}</flux:badge>
                            @elseif ($balance['balanceCents'] < 0)
                                <flux:badge color="red">{{ __('owes') }} {{ Money::format(abs($balance['balanceCents']), $currency) }}</flux:badge>
                            @else
                                <flux:badge>{{ __('Settled up') }}</flux:badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">{{ __('Suggested Transfers') }}</flux:heading>
                    <flux:badge>{{ count($transfers) }}</flux:badge>
                </div>

                @if (count($transfers) > 0)
                    <div class="space-y-2">
                        @foreach ($transfers as $transfer)
                            <div wire:key="transfer-{{ $transfer['from']->id }}-{{ $transfer['to']->id }}-{{ $currency }}" class="flex items-center gap-3 p-3 rounded-lg border border-neutral-700">
                                <div class="flex items-center gap-2">
                                    <x-participant-avatar :name="$transfer['from']->fullName()" :initials="$transfer['from']->initials()" size="sm" />
                                    <flux:text class="text-sm">{{ $transfer['from']->fullName() }}</flux:text>
                                </div>
                                <flux:icon.arrow-right class="h-4 w-4 text-neutral-400" />
                                <div class="flex items-center gap-2">
                                    <x-participant-avatar :name="$transfer['to']->fullName()" :initials="$transfer['to']->initials()" size="sm" />
                                    <flux:text class="text-sm">{{ $transfer['to']->fullName() }}</flux:text>
                                </div>
                                <flux:text class="ml-auto font-semibold">{{ Money::format($transfer['amountCents'], $currency) }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                    <flux:text class="mt-4 block text-xs text-neutral-500">
                        {{ __('Settle these from within each shared trip\'s own Settle Up card — this page is a combined read-only view.') }}
                    </flux:text>
                @else
                    <flux:callout variant="subtle">
                        <flux:text>{{ __('Everyone is settled up!') }}</flux:text>
                    </flux:callout>
                @endif
            </div>
        @endforeach
    @endif
</div>
