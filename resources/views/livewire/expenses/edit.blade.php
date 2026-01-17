<?php

use App\Models\Expense;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public Trip $trip;
    public Expense $expense;

    public string $name = '';
    public string $description = '';
    public string $link = '';
    public string $unit_price = '';
    public int $quantity = 1;
    public ?int $user_id = null;

    public function with(): array
    {
        return ['title' => __('Edit Expense')];
    }

    /**
     * Mount the component.
     */
    public function mount(Trip $trip, Expense $expense): void
    {
        // Only expense owner or trip creator can edit
        if ($expense->user_id !== Auth::id() && $trip->user_id !== Auth::id()) {
            abort(403);
        }

        if ($expense->trip_id !== $trip->id) {
            abort(404);
        }

        $this->trip = $trip->load(['participants', 'creator']);
        $this->expense = $expense;
        $this->name = $expense->name;
        $this->description = $expense->description ?? '';
        $this->link = $expense->link ?? '';
        $this->unit_price = (string) $expense->unit_price;
        $this->quantity = $expense->quantity;
        $this->user_id = $expense->user_id ?? $trip->user_id;
    }

    /**
     * Update the expense.
     */
    public function update(): void
    {
        // Only expense owner or trip creator can update
        if ($this->expense->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $eligibleUserIds = $this->trip->participants->pluck('id')->push($this->trip->user_id)->unique()->toArray();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'link' => ['nullable', 'url', 'max:255'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
        ]);

        $this->expense->update($validated);

        $this->redirect(route('trips.show', $this->trip), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Edit Expense') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Update expense in') }} "{{ $trip->name }}"
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="update" class="space-y-6">
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
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                    <span wire:loading.remove wire:target="update">{{ __('Update Expense') }}</span>
                    <span wire:loading wire:target="update">{{ __('Updating...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.show', $trip)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
