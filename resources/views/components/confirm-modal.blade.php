{{--
    A single, shared confirmation modal for a page's destructive/irreversible
    actions. The page root must declare the `confirmDestroy(heading, text,
    action, label, variant)` Alpine method (see trips/show.blade.php and
    trips/index.blade.php) — trigger elements call it on click instead of each
    mounting their own modal, so DOM/Alpine cost doesn't scale with list length
    and there's only one modal `name` to keep in sync.
--}}
@props([
    'name' => 'confirm-action',
    'wireTarget' => '',
])

<flux:modal :name="$name" class="max-w-sm">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" x-text="confirmHeading"></flux:heading>
            <flux:subheading class="mt-2" x-text="confirmText"></flux:subheading>
        </div>
        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button
                variant="danger"
                x-show="confirmVariant === 'danger'"
                x-on:click="confirmAction && confirmAction()"
                wire:loading.attr="disabled"
                wire:target="{{ $wireTarget }}"
                x-text="confirmLabel"
            ></flux:button>
            <flux:button
                variant="primary"
                x-show="confirmVariant === 'primary'"
                x-on:click="confirmAction && confirmAction()"
                wire:loading.attr="disabled"
                wire:target="{{ $wireTarget }}"
                x-text="confirmLabel"
            ></flux:button>
        </div>
    </div>
</flux:modal>
