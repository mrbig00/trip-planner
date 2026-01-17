<?php

use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public Trip $trip;

    public string $name = '';
    public string $description = '';
    public string $link = '';
    public string $unit_price = '';
    public int $quantity = 1;
    public ?int $user_id = null;

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
    }

    /**
     * Create a new expense.
     */
    public function store(): void
    {
        $eligibleUserIds = $this->trip->participants->pluck('id')->push($this->trip->user_id)->unique()->toArray();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
        ]);

        $this->trip->expenses()->create($validated);

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

            @if ($name && $unit_price && $quantity)
                <flux:callout variant="subtle" class="bg-neutral-700/30">
                    <flux:text>
                        <strong>{{ __('Total') }}:</strong> ${{ number_format((float) $unit_price * $quantity, 2) }}
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
