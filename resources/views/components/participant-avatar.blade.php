@props([
    'name',
    'initials',
    'size' => 'sm',
    'colorSlot' => null,
])

@php
    // Every case is written out literally (not interpolated) so Tailwind's
    // static content scan can find and generate it — see the Phase A plan
    // note in resources/css/app.css about --color-participant-* tokens.
    $ring = match ($colorSlot) {
        1 => 'ring-2 ring-[var(--color-participant-1)]',
        2 => 'ring-2 ring-[var(--color-participant-2)]',
        3 => 'ring-2 ring-[var(--color-participant-3)]',
        5 => 'ring-2 ring-[var(--color-participant-5)]',
        7 => 'ring-2 ring-[var(--color-participant-7)]',
        default => '',
    };

    // Dicebear generates a stable illustrated avatar from the participant's
    // name, so everyone gets a distinct, friendly face instead of a flat
    // initials badge. The name is hashed first so no personal data is sent to
    // the third-party service; the same name still yields the same avatar.
    $avatarUrl = $name
        ? 'https://api.dicebear.com/9.x/avataaars/svg?seed='.hash('sha256', $name)
        : null;
@endphp

<flux:avatar :name="$name" :initials="$initials" :src="$avatarUrl" :size="$size" {{ $attributes->class([$ring]) }} />
