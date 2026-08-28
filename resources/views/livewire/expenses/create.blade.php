<?php

use App\Actions\Expenses\BuildExpenseShares;
use App\Actions\Expenses\ValidateExpenseSplit;
use App\Enums\Currency;
use App\Enums\ExpenseSplitType;
use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use App\Livewire\Concerns\TracksAnalyticsEvents;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    use TracksAnalyticsEvents;

    public Trip $trip;

    public string $name = '';
    public string $description = '';
    public string $link = '';
    public string $unit_price = '';
    public int $quantity = 1;
    public ?int $user_id = null;

    public string $split_type = 'equal';
    public array $participant_ids = [];
    public array $percentages = [];
    public array $fixed_amounts = [];

    public string $currency = '';
    public ?string $exchange_rate = null;

    public function with(): array
    {
        return ['title' => __('Add Expense')];
    }

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        if ($trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->trip = $trip->load(['participants', 'creator']);
        $this->user_id = Auth::id(); // Default to current user
        $this->participant_ids = $this->trip->members()->pluck('id')->all();
        $this->currency = ($this->trip->currency ?? Currency::default())->value;
    }

    /**
     * Prune stale split inputs whenever the selected participants or split type change.
     */
    public function updated(string $name): void
    {
        if ($name === 'participant_ids' || $name === 'split_type') {
            $this->percentages = collect($this->percentages)->only($this->participant_ids)->all();
            $this->fixed_amounts = collect($this->fixed_amounts)->only($this->participant_ids)->all();
        }
    }

    /**
     * Create a new expense.
     */
    public function store(): void
    {
        $eligibleUserIds = $this->trip->members()->pluck('id')->all();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
            'split_type' => ['required', Rule::enum(ExpenseSplitType::class)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'exchange_rate' => [
                'nullable', 'numeric', 'min:0.000001',
                'required_unless:currency,'.$this->trip->currency?->value,
            ],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'in:'.implode(',', $eligibleUserIds), 'distinct'],
        ]);

        $splitType = ExpenseSplitType::from($validated['split_type']);
        $totalCents = (int) bcmul(bcmul((string) $validated['unit_price'], '100', 0), (string) $validated['quantity'], 0);

        // A same-currency expense never carries a rate, regardless of what a
        // stale/hidden field might have posted — null is the one meaning
        // "same as the trip's currency" everywhere else reads it.
        $exchangeRate = $validated['currency'] === $this->trip->currency?->value
            ? null
            : $validated['exchange_rate'];

        app(ValidateExpenseSplit::class)->validate(
            $splitType,
            $this->participant_ids,
            $this->percentages,
            $this->fixed_amounts,
            $totalCents
        );

        DB::transaction(function () use ($validated, $splitType, $totalCents, $exchangeRate) {
            $expense = $this->trip->expenses()->create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'link' => $validated['link'] ?? null,
                'unit_price' => $validated['unit_price'],
                'quantity' => $validated['quantity'],
                'user_id' => $validated['user_id'],
                'split_type' => $splitType->value,
                'currency' => $validated['currency'],
                'exchange_rate' => $exchangeRate,
            ]);

            $expense->shares()->createMany(
                app(BuildExpenseShares::class)->build(
                    $totalCents,
                    $splitType,
                    $this->participant_ids,
                    $this->percentages,
                    $this->fixed_amounts
                )
            );
        });

        $this->trackEvent('expense_created', ['trip_id' => $this->trip->id, 'amount_cents' => $totalCents]);

        $this->redirect(route('trips.show', $this->trip), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Add Expense') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Add a new expense to') }} "{{ $trip->name }}"
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="store" class="space-y-6">
            <flux:field>
                <flux:input
                    wire:model="name"
                    :label="__('Expense Name')"
                    :placeholder="__('e.g., Hotel, Flight, Food')"
                    required
                    autofocus
                />
            </flux:field>

            <flux:field>
                <flux:textarea
                    wire:model="description"
                    :label="__('Description')"
                    :placeholder="__('Add a description for this expense...')"
                    rows="3"
                />
            </flux:field>

            <flux:field>
                <flux:input
                    wire:model="link"
                    type="url"
                    :label="__('Link')"
                    :placeholder="__('https://example.com')"
                />
            </flux:field>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:input
                        wire:model="unit_price"
                        type="number"
                        step="0.01"
                        :label="__('Unit Price')"
                        :placeholder="__('0.00')"
                        required
                    />
                </flux:field>

                <flux:field>
                    <flux:input
                        wire:model="quantity"
                        type="number"
                        :label="__('Quantity')"
                        :placeholder="__('1')"
                        required
                        min="1"
                    />
                </flux:field>
            </div>

            <flux:field>
                <flux:select
                    wire:model="user_id"
                    :label="__('Owner')"
                    :placeholder="__('Select expense owner')"
                    required
                >
                    <option value="{{ $trip->creator->id }}">{{ $trip->creator->fullName() }} ({{ __('Trip Creator') }})</option>
                    @foreach ($trip->participants as $participant)
                        <option value="{{ $participant->id }}">{{ $participant->fullName() }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:select wire:model.live="currency" :label="__('Currency')" required>
                        @foreach (\App\Enums\Currency::cases() as $currencyOption)
                            <option wire:key="currency-{{ $currencyOption->value }}" value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                @if ($currency !== $trip->currency?->value)
                    <flux:field>
                        <flux:input
                            wire:model="exchange_rate"
                            type="number"
                            step="0.000001"
                            :label="__('Exchange rate to :currency', ['currency' => $trip->currency->value])"
                            :description="__('1 :from = ___ :to', ['from' => $currency, 'to' => $trip->currency->value])"
                            required
                        />
                    </flux:field>
                @endif
            </div>

            <flux:separator />

            <flux:checkbox.group wire:model.live="participant_ids" :label="__('Split between')">
                @foreach ($trip->members() as $member)
                    <flux:checkbox wire:key="split-participant-{{ $member->id }}" value="{{ $member->id }}" :label="$member->fullName()" />
                @endforeach
            </flux:checkbox.group>
            @error('participant_ids')
                <flux:error>{{ $message }}</flux:error>
            @enderror

            <flux:field>
                <flux:select wire:model.live="split_type" :label="__('Split type')">
                    <option value="equal">{{ __('Equal') }}</option>
                    <option value="percentage">{{ __('Percentage') }}</option>
                    <option value="fixed">{{ __('Fixed amount') }}</option>
                </flux:select>
            </flux:field>

            @php
                $selectedMembers = $trip->members()->whereIn('id', $participant_ids);
                // wire:model.live means $currency is a client-mutable public
                // property — tryFrom() with a fallback avoids a ValueError if
                // it's ever tampered with into something other than a real
                // currency code.
                $currencySymbol = (\App\Enums\Currency::tryFrom($currency) ?? \App\Enums\Currency::default())->symbol();
            @endphp

            @if ($split_type === 'percentage')
                <div class="space-y-2">
                    @foreach ($selectedMembers as $member)
                        <div wire:key="split-percentage-{{ $member->id }}" class="flex items-center gap-3">
                            <flux:text class="flex-1 text-sm">{{ $member->fullName() }}</flux:text>
                            <flux:input
                                wire:model.live="percentages.{{ $member->id }}"
                                type="number"
                                step="0.01"
                                class="w-28"
                                suffix="%"
                            />
                        </div>
                    @endforeach
                    @php
                        $percentageSum = array_sum(array_map('floatval', $percentages));
                    @endphp
                    <flux:text class="text-sm {{ abs($percentageSum - 100) <= 0.5 ? 'text-green-500' : 'text-red-500' }}">
                        {{ number_format($percentageSum, 2) }}% / 100%
                    </flux:text>
                    @error('percentages')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @elseif ($split_type === 'fixed')
                <div class="space-y-2">
                    @foreach ($selectedMembers as $member)
                        <div wire:key="split-fixed-{{ $member->id }}" class="flex items-center gap-3">
                            <flux:text class="flex-1 text-sm">{{ $member->fullName() }}</flux:text>
                            <flux:input
                                wire:model.live="fixed_amounts.{{ $member->id }}"
                                type="number"
                                step="0.01"
                                class="w-28"
                                :prefix="$currencySymbol"
                            />
                        </div>
                    @endforeach
                    @php
                        $fixedSum = array_sum(array_map('floatval', $fixed_amounts));
                        $fixedTotal = (is_numeric($unit_price) ? (float) $unit_price : 0) * $quantity;
                    @endphp
                    <flux:text class="text-sm {{ abs($fixedSum - $fixedTotal) < 0.005 ? 'text-green-500' : 'text-red-500' }}">
                        {{ $currencySymbol }}{{ number_format($fixedSum, 2) }} / {{ $currencySymbol }}{{ number_format($fixedTotal, 2) }}
                    </flux:text>
                    @error('fixed_amounts')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @else
                <flux:text class="text-sm text-neutral-400">
                    {{ __('Each of the :count selected members pays an equal share.', ['count' => count($participant_ids)]) }}
                </flux:text>
            @endif

            @if ($name && $unit_price && $quantity)
                <flux:callout variant="subtle" class="bg-neutral-700/30">
                    <flux:text>
                        <strong>{{ __('Total') }}:</strong> {{ $currencySymbol }}{{ number_format((float) $unit_price * $quantity, 2) }}
                    </flux:text>
                </flux:callout>
            @endif

            <flux:separator />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="store">
                    <span wire:loading.remove wire:target="store">{{ __('Add Expense') }}</span>
                    <span wire:loading wire:target="store">{{ __('Adding...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.show', $trip)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
