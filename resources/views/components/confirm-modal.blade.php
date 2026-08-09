@props([
    'name',
    'heading',
    'text',
    'action',
    'confirmLabel' => __('Delete'),
    'confirmVariant' => 'danger',
])

<flux:modal :name="$name" class="max-w-sm">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $heading }}</flux:heading>
            <flux:subheading class="mt-2">{{ $text }}</flux:subheading>
        </div>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button
                :variant="$confirmVariant"
                wire:click="{{ $action }}"
                wire:loading.attr="disabled"
                wire:target="{{ $action }}"
            >
                {{ $confirmLabel }}
            </flux:button>
        </div>
    </div>
</flux:modal>
