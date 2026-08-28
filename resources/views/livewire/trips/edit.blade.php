<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Edit Trip') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('Update trip details') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-neutral-700 bg-neutral-800/50 p-6">
        <form wire:submit="update" class="space-y-6">
            <flux:field>
                <flux:input
                    wire:model="name"
                    :label="__('Trip Name')"
                    :placeholder="__('e.g., Summer Vacation 2025')"
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

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:field>
                    <flux:input
                        type="date"
                        wire:model="start_date"
                        :label="__('Start Date')"
                    />
                </flux:field>

                <flux:field>
                    <flux:input
                        type="date"
                        wire:model="end_date"
                        :label="__('End Date')"
                    />
                </flux:field>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:field>
                    <flux:input
                        type="number"
                        step="0.01"
                        min="0"
                        wire:model="budget"
                        :label="__('Budget')"
                        :placeholder="__('Optional')"
                        prefix="$"
                    />
                </flux:field>

                <flux:field>
                    <flux:select
                        wire:model="currency"
                        :label="__('Currency')"
                        :description="$currencyLocked ? __('Locked once the trip has an expense.') : null"
                        :disabled="$currencyLocked"
                        required
                    >
                        @foreach (\App\Enums\Currency::cases() as $currencyOption)
                            <option value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <flux:separator />

            <div class="flex items-center gap-3">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="update">
                    <span wire:loading.remove wire:target="update">{{ __('Update Trip') }}</span>
                    <span wire:loading wire:target="update">{{ __('Updating...') }}</span>
                </flux:button>
                <flux:button variant="ghost" :href="route('trips.show', $trip)" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
