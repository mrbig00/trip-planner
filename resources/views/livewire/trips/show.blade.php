@php use App\Support\Money; use App\Enums\Currency; @endphp

<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    x-data="{
        confirmHeading: '',
        confirmText: '',
        confirmLabel: '{{ __('Delete') }}',
        confirmVariant: 'danger',
        confirmAction: null,
        confirmDestroy(heading, text, action, label = '{{ __('Delete') }}', variant = 'danger') {
            this.confirmHeading = heading;
            this.confirmText = text;
            this.confirmAction = action;
            this.confirmLabel = label;
            this.confirmVariant = variant;
            this.$dispatch('modal-show', { name: 'confirm-action' });
        },
    }"
>
    <flux:link :href="route('trips.index')" wire:navigate class="text-sm text-neutral-400 hover:text-white inline-flex items-center gap-1">
        <flux:icon.chevron-left class="h-4 w-4" />
        {{ __('Trips') }}
    </flux:link>

    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <flux:heading size="xl">{{ $trip->name }}</flux:heading>
            @if ($trip->description)
                <flux:text class="mt-2">
                    {{ $trip->description }}
                </flux:text>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                    {{ __('Created by') }} {{ $trip->creator->fullName() }}
                </flux:badge>
                <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                    {{ $trip->created_at->format('M d, Y') }}
                </flux:badge>
            </div>
            <div class="mt-3 flex items-center gap-3">
                <div class="flex -space-x-2">
                    @foreach ($trip->members() as $member)
                        <x-participant-avatar
                            :name="$member->fullName()"
                            :initials="$member->initials()"
                            :color-slot="$trip->colorSlotFor($member)"
                            size="xs"
                        />
                    @endforeach
                </div>
                <flux:badge size="sm">{{ __('Total') }}: {{ Money::formatDecimal((string) $this->totalExpenses, $trip->currency ?? Currency::default()) }}</flux:badge>
                @if ($trip->countdownLabel())
                    <flux:badge size="sm" variant="ghost" class="bg-neutral-700/50 text-neutral-300">
                        {{ $trip->countdownLabel() }}
                    </flux:badge>
                @endif
            </div>
            @if ($trip->budget_summary)
                @php $summary = $trip->budget_summary; @endphp
                <div class="mt-3 max-w-xs">
                    <div class="flex items-center justify-between mb-1">
                        <flux:text class="text-xs text-neutral-400">{{ __('Budget') }}</flux:text>
                        @if ($summary['overBudget'])
                            <flux:text class="text-xs text-red-400">
                                {{ Money::formatDecimal((string) abs($summary['remaining']), $trip->currency ?? Currency::default()) }} {{ __('over budget') }}
                            </flux:text>
                        @else
                            <flux:text class="text-xs text-neutral-400">
                                {{ Money::formatDecimal((string) $summary['spent'], $trip->currency ?? Currency::default()) }} / {{ Money::formatDecimal((string) $summary['budget'], $trip->currency ?? Currency::default()) }}
                            </flux:text>
                        @endif
                    </div>
                    <div class="h-2 rounded-full bg-neutral-700/50 overflow-hidden">
                        <div
                            class="h-full rounded-full {{ $summary['overBudget'] ? 'bg-red-500' : '' }}"
                            style="width: {{ $summary['percentUsed'] }}%;{{ $summary['overBudget'] ? '' : ' background-color: var(--color-money-4);' }}"
                        ></div>
                    </div>
                </div>
            @endif
        </div>
        @if ($trip->user_id === Auth::id())
            <div class="flex items-center gap-2">
                <flux:button variant="ghost" :href="route('trips.edit', $trip)" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    x-on:click="confirmDestroy('{{ __('Delete trip?') }}', '{{ __('Are you sure you want to delete this trip? This action cannot be undone.') }}', () => $wire.delete())"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        @endif
    </div>

    @if ($this->recentActivity->isNotEmpty())
        @php
            $activityIcon = fn ($type) => \App\Actions\Trips\BuildActivityFeed::iconFor($type);
        @endphp
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Recent Activity') }}</flux:heading>
            <div class="space-y-3">
                @foreach ($this->recentActivity as $event)
                    <div wire:key="activity-{{ $loop->index }}" class="flex items-center gap-3">
                        @if ($event['user'])
                            <x-participant-avatar
                                :name="$event['user']->fullName()"
                                :initials="$event['user']->initials()"
                                :color-slot="$trip->colorSlotFor($event['user'])"
                                size="xs"
                            />
                        @else
                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-neutral-700/50 shrink-0">
                                <flux:icon :icon="$activityIcon($event['type'])" class="h-3.5 w-3.5 text-neutral-400" />
                            </div>
                        @endif
                        <flux:text class="text-sm flex-1">{{ $event['text'] }}</flux:text>
                        <flux:text class="text-xs text-neutral-500 shrink-0">{{ $event['at']->diffForHumans() }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Locations') }}</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge>{{ $trip->locations->count() }}</flux:badge>
                    @if ($trip->user_id === Auth::id())
                        <flux:button variant="ghost" size="sm" :href="route('locations.create', $trip)" wire:navigate>
                            {{ __('Add Location') }}
                        </flux:button>
                    @endif
                </div>
            </div>
            @php
                // Once one location is accepted, it's the only one shown by
                // default — the rest stay collapsed behind the toggle below
                // until asked for. With nothing accepted yet, everyone still
                // needs to see (and vote on) every option, so show them all.
                $hasAcceptedLocation = $trip->locations->contains('accepted', true);
                $pendingLocationCount = $trip->locations->where('accepted', false)->count();
                $visibleLocations = $hasAcceptedLocation && ! $showAllLocations
                    ? $trip->locations->where('accepted', true)
                    : $trip->locations;
            @endphp
            @if ($visibleLocations->contains(fn ($location) => $location->latitude && $location->longitude))
                <flux:text class="text-[10px] opacity-50 -mt-3 mb-3 block">{{ __('Map thumbnails © OpenStreetMap contributors') }}</flux:text>
            @endif
            @if ($trip->locations->count() > 0)
                <div class="space-y-3">
                    @foreach ($visibleLocations->sortByDesc('accepted')->values() as $location)
                            <div wire:key="location-{{ $location->id }}" class="p-3 rounded-lg border border-neutral-200 dark:border-neutral-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="font-medium">{{ $location->name }}</flux:text>
                                        @php
                                            $voteCount = $location->votes->count();
                                            $hasVoted = $location->hasVoteFrom(Auth::user());
                                        @endphp
                                        @if ($voteCount > 0)
                                            <button
                                                type="button"
                                                wire:click="showVoters({{ $location->id }})"
                                                title="{{ __('View voters') }}"
                                                class="inline-flex items-center gap-1 rounded-full bg-neutral-700/50 text-neutral-400 text-xs px-2 py-0.5 hover:text-neutral-200 cursor-pointer"
                                            >
                                                <flux:icon.heart variant="mini" class="h-3 w-3" />
                                                {{ $voteCount }}
                                            </button>
                                        @endif
                                    </div>
                                    @if ($location->price)
                                        <flux:text class="text-sm mt-1">
                                            {{ __('Price') }}: {{ Money::formatDecimal((string) $location->price, $location->currency ?? $trip->currency ?? Currency::default()) }}
                                        </flux:text>
                                    @endif
                                    @if ($location->latitude && $location->longitude)
                                        <div class="mt-2">
                                            <x-location-map-thumbnail :latitude="$location->latitude" :longitude="$location->longitude" width="140" height="100" />
                                            <flux:text class="text-[11px] mt-1 opacity-60">
                                                {{ number_format($location->latitude, 6) }}, {{ number_format($location->longitude, 6) }}
                                            </flux:text>
                                        </div>
                                    @endif
                                    @if ($location->link)
                                        <flux:link :href="$location->link" target="_blank" class="text-xs mt-1 text-blue-400 hover:text-blue-300">
                                            {{ __('View Link') }}
                                        </flux:link>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="toggleVote({{ $location->id }})"
                                    {{-- The `!` (important) modifier is required here: Flux's ghost-variant button
                                    already sets `text-zinc-800 dark:text-white`, and Tailwind resolves same-specificity
                                    utility conflicts by order in the compiled stylesheet, not by order in this class
                                    attribute, so a plain override class would otherwise silently lose. --}}
                                    class="{{ $hasVoted ? '!text-red-500 hover:!text-red-400' : '!text-neutral-300 hover:!text-white' }}"
                                >
                                    {{-- inline-block overrides Tailwind's `svg { display: block }` preflight rule: without
                                    it, the icon and label get wrapped together in a plain (non-flex) <span> by Flux's
                                    automatic wire:click loading-state handling, and the block-level icon forces the
                                    label onto its own line below it. --}}
                                    <flux:icon.heart variant="{{ $hasVoted ? 'solid' : 'outline' }}" class="h-4 w-4 inline-block" />
                                    {{ $hasVoted ? __('Voted') : __('Vote') }}
                                </flux:button>
                                @if ($location->accepted)
                                    <flux:badge color="green">{{ __('Accepted') }}</flux:badge>
                                @else
                                    <flux:badge color="amber">{{ __('Pending') }}</flux:badge>
                                    @if ($trip->user_id === Auth::id())
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="acceptLocation({{ $location->id }})"
                                            class="text-neutral-300 hover:text-white"
                                        >
                                            {{ __('Accept') }}
                                        </flux:button>
                                    @endif
                                @endif
                                @if ($trip->user_id === Auth::id())
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon-only class="text-neutral-400 hover:text-white">
                                            <flux:icon.ellipsis-vertical />
                                        </flux:button>
                                        <flux:menu>
                                            <flux:menu.item :href="route('locations.edit', [$trip, $location])" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                            <flux:menu.item
                                                variant="danger"
                                                x-on:click="confirmDestroy('{{ __('Delete location?') }}', '{{ __('Are you sure you want to delete this location? This action cannot be undone.') }}', () => $wire.deleteLocation({{ $location->id }}))"
                                            >
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                                </div>
                            </div>

                            {{-- Comments Section --}}
                            <div class="mt-3 pt-3 border-t border-neutral-700">
                                @if ($location->comments->count() > 0)
                                    <div class="flex items-center justify-between mb-3">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="toggleLocationComments({{ $location->id }})"
                                            class="text-xs text-neutral-400 hover:text-white"
                                        >
                                            {{ $expandedLocationId === $location->id ? __('Hide') : __('Show') }} {{ $location->comments->count() }} {{ $location->comments->count() === 1 ? __('comment') : __('comments') }}
                                        </flux:button>
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="openAddCommentModal({{ $location->id }})"
                                            class="text-xs text-blue-400 hover:text-blue-300"
                                        >
                                            {{ __('Add Comment') }}
                                        </flux:button>
                                    </div>

                                    @if ($expandedLocationId === $location->id)
                                        <div class="space-y-3">
                                            @foreach ($location->comments as $comment)
                                                <div wire:key="comment-{{ $comment->id }}" class="flex items-start gap-3 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700/20">
                                                    <x-participant-avatar
                                                        :name="$comment->user->fullName()"
                                                        :initials="$comment->user->initials()"
                                                        :color-slot="$trip->colorSlotFor($comment->user)"
                                                        size="sm"
                                                    />
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <flux:text class="text-sm font-medium">{{ $comment->user->fullName() }}</flux:text>
                                                            <flux:text class="text-xs text-neutral-500">{{ $comment->created_at->diffForHumans() }}</flux:text>
                                                        </div>
                                                        <flux:text class="text-sm text-neutral-300">{{ $comment->content }}</flux:text>
                                                    </div>
                                                    @if ($comment->user_id === Auth::id() || $trip->user_id === Auth::id())
                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon-only
                                                            class="text-red-400/70 hover:text-red-400"
                                                            title="{{ __('Delete comment') }}"
                                                            x-on:click="confirmDestroy('{{ __('Delete comment?') }}', '{{ __('Are you sure you want to delete this comment? This action cannot be undone.') }}', () => $wire.deleteComment({{ $comment->id }}))"
                                                        >
                                                            <flux:icon.x-mark class="h-3 w-3" />
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="openAddCommentModal({{ $location->id }})"
                                        class="text-xs text-blue-400 hover:text-blue-300"
                                    >
                                        {{ __('Add Comment') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    @if ($hasAcceptedLocation && $pendingLocationCount > 0)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            wire:click="toggleShowAllLocations"
                            class="text-xs text-neutral-400 hover:text-white"
                        >
                            @if ($showAllLocations)
                                {{ __('Hide unvoted locations') }}
                            @else
                                {{ $pendingLocationCount === 1 ? __('Show 1 unvoted location') : __('Show :count unvoted locations', ['count' => $pendingLocationCount]) }}
                            @endif
                        </flux:button>
                    @endif
                </div>
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('No locations added yet.') }}</flux:text>
                    </flux:callout>
            @endif
        </div>

        <div class="flex flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Expenses') }}</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge>{{ $trip->expenses->count() }}</flux:badge>
                    @if ($trip->user_id === Auth::id())
                        <flux:button variant="ghost" size="sm" :href="route('expenses.create', $trip)" wire:navigate>
                            {{ __('Add Expense') }}
                        </flux:button>
                    @endif
                </div>
            </div>
            @if ($trip->expenses->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-neutral-700">
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-300">
                                    {{ __('Name') }}
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-300">
                                    {{ __('Description') }}
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-300">
                                    {{ __('Link') }}
                                </th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-300">
                                    {{ __('Owner') }}
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-neutral-300">
                                    {{ __('Unit Price') }}
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-neutral-300">
                                    {{ __('Quantity') }}
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-neutral-300">
                                    {{ __('Total') }}
                                </th>
                                <th class="px-4 py-3 text-right text-sm font-semibold text-neutral-300">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trip->expenses as $expense)
                                <tr wire:key="expense-{{ $expense->id }}" class="border-b border-neutral-700/50 hover:bg-neutral-800/30 transition-colors">
                                    @php
                                        $canEditExpense = $this->canManageExpense($expense);
                                    @endphp
                                    <td class="px-4 py-3">
                                        <flux:text class="font-medium">{{ $expense->name }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($expense->description)
                                            <flux:text class="text-sm text-neutral-400 line-clamp-1">{{ $expense->description }}</flux:text>
                                        @elseif ($canEditExpense)
                                            <button type="button" wire:click="openEditExpenseModal({{ $expense->id }})" class="text-sm italic text-neutral-500 hover:text-neutral-300">
                                                {{ __('+ Add description') }}
                                            </button>
                                        @else
                                            <flux:text class="text-sm text-neutral-500">—</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($expense->link)
                                            <flux:link :href="$expense->link" target="_blank" class="text-sm text-blue-400 hover:text-blue-300">
                                                {{ __('Open') }}
                                            </flux:link>
                                        @elseif ($canEditExpense)
                                            <button type="button" wire:click="openEditExpenseModal({{ $expense->id }})" class="text-sm italic text-neutral-500 hover:text-neutral-300">
                                                {{ __('+ Add link') }}
                                            </button>
                                        @else
                                            <flux:text class="text-sm text-neutral-500">—</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($expense->owner)
                                            <div class="flex items-center gap-2">
                                                <x-participant-avatar
                                                    :name="$expense->owner->fullName()"
                                                    :initials="$expense->owner->initials()"
                                                    :color-slot="$trip->colorSlotFor($expense->owner)"
                                                    size="xs"
                                                />
                                                <flux:text class="text-sm">{{ $expense->owner->fullName() }}</flux:text>
                                            </div>
                                            @if ($expense->createdBy && $expense->created_by !== $expense->user_id)
                                                <flux:text class="text-xs text-neutral-500 mt-0.5">
                                                    {{ __('Added by :name', ['name' => $expense->createdBy->fullName()]) }}
                                                </flux:text>
                                            @endif
                                        @else
                                            <flux:text class="text-sm text-neutral-500">—</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:text>{{ Money::formatDecimal((string) $expense->unit_price, $expense->currency ?? $trip->currency ?? Currency::default()) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:text>{{ $expense->quantity }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <flux:text class="font-semibold">{{ Money::formatDecimal((string) $expense->total, $expense->currency ?? $trip->currency ?? Currency::default()) }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($canEditExpense)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon-only
                                                    wire:click="openEditExpenseModal({{ $expense->id }})"
                                                    class="text-neutral-400 hover:text-white"
                                                    title="{{ __('Edit') }}"
                                                >
                                                    <flux:icon.pencil class="h-4 w-4" />
                                                </flux:button>
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon-only
                                                    class="text-red-400/70 hover:text-red-400"
                                                    title="{{ __('Delete') }}"
                                                    x-on:click="confirmDestroy('{{ __('Delete expense?') }}', '{{ __('Are you sure you want to delete this expense? This action cannot be undone.') }}', () => $wire.deleteExpense({{ $expense->id }}))"
                                                >
                                                    <flux:icon.x-mark class="h-4 w-4" />
                                                </flux:button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-neutral-700 bg-neutral-800/30">
                                <td class="px-4 py-3" colspan="4">
                                    <flux:text class="font-semibold">{{ __('Total') }}</flux:text>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:text class="font-semibold text-lg">{{ Money::formatDecimal((string) $this->totalExpenses, $trip->currency ?? Currency::default()) }}</flux:text>
                                </td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if ($this->costBreakdown->isNotEmpty())
                    @php
                        // Canvas drawing doesn't follow CSS dark: variants, so these are
                        // the literal dark-tuned hexes from app.css's .dark override —
                        // matching the existing (also non-adaptive) bar chart convention.
                        $breakdownColor = fn ($slot) => match ($slot) {
                            1 => '#3987e5',
                            2 => '#d95926',
                            3 => '#199e70',
                            5 => '#d55181',
                            7 => '#9085e9',
                            default => '#71717a',
                        };
                    @endphp
                    <div class="mt-6 pt-6 border-t border-neutral-700/50">
                        <flux:subheading class="mb-3">{{ __('Cost Breakdown') }}</flux:subheading>
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <div
                                class="relative h-44 w-44 shrink-0"
                                x-data="doughnutChart({
                                    labels: @js($this->costBreakdown->pluck('user')->map->fullName()),
                                    data: @js($this->costBreakdown->pluck('amountCents')->map(fn ($cents) => $cents / 100)),
                                    colors: @js($this->costBreakdown->pluck('slot')->map($breakdownColor)),
                                    valuePrefix: @js(($trip->currency ?? Currency::default())->symbol()),
                                })"
                            >
                                <canvas x-ref="canvas"></canvas>
                            </div>
                            <div class="flex-1 w-full space-y-2">
                                @foreach ($this->costBreakdown as $row)
                                    <div class="flex items-center gap-2">
                                        <span class="h-3 w-3 rounded-full shrink-0" style="background-color: {{ $breakdownColor($row['slot']) }}"></span>
                                        <flux:text class="text-sm flex-1">{{ $row['user']->fullName() }}</flux:text>
                                        <flux:text class="text-sm font-medium">{{ Money::format($row['amountCents'], $trip->currency ?? Currency::default()) }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('No expenses added yet.') }}</flux:text>
                    </flux:callout>
            @endif
        </div>

        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Participants') }}</flux:heading>
                <div class="flex items-center gap-2">
                    <flux:badge>{{ $trip->participants->count() }}</flux:badge>
                    @if ($trip->user_id === Auth::id())
                        <flux:button variant="ghost" size="sm" wire:click="openAddParticipantModal">
                            {{ __('Add Participant') }}
                        </flux:button>
                    @endif
                </div>
            </div>
            @if ($trip->participants->count() > 0)
                <div class="flex flex-wrap gap-3">
                    @foreach ($trip->participants as $participant)
                        <div wire:key="participant-{{ $participant->id }}" class="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700">
                            <x-participant-avatar :name="$participant->fullName()" :initials="$participant->initials()" :color-slot="$trip->colorSlotFor($participant)" size="sm" />
                            <flux:badge>{{ $participant->fullName() }}</flux:badge>
                            @if ($trip->user_id === Auth::id())
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon-only
                                    class="text-red-400/70 hover:text-red-400"
                                    title="{{ __('Remove participant') }}"
                                    x-on:click="confirmDestroy('{{ __('Remove participant?') }}', '{{ __('Are you sure you want to remove this participant from the trip? This action cannot be undone.') }}', () => $wire.removeParticipant({{ $participant->id }}), '{{ __('Remove') }}')"
                                >
                                    <flux:icon.x-mark class="h-4 w-4" />
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('No participants yet.') }}</flux:text>
                    @if ($trip->user_id === Auth::id())
                        <flux:text class="mt-2">{{ __('Click "Add Participant" to invite friends to your trip.') }}</flux:text>
                    @endif
                </flux:callout>
            @endif
        </div>
        </div>
    </div>

    @php
        // Trip pages aren't currently scoped by membership at the route
        // level (unlike Trips\Index, Budgets, etc. — see BuildActivityFeed's
        // ->visibleTo() usage there), so an unrelated authenticated user can
        // open any trip's URL. Documents can carry sensitive attachments, so
        // — independent of the server-side checks on every document
        // action — the section itself is only rendered for actual members.
        // $this->isTripMember (not a raw expression here) so this stays the
        // same single source of truth the component's own authorization
        // checks use — see Show::getIsTripMemberProperty().
    @endphp
    @if ($this->isTripMember)
    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
            <div class="flex items-center gap-2">
                <flux:badge>{{ $trip->documents->count() }}</flux:badge>
                <flux:button variant="ghost" size="sm" wire:click="openAddDocumentModal">
                    {{ __('Add Document') }}
                </flux:button>
            </div>
        </div>
        @if ($trip->documents->count() > 0)
            <div class="space-y-3">
                @foreach ($trip->documents as $document)
                    <div wire:key="document-{{ $document->id }}" class="flex items-start gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-neutral-700/50">
                            <flux:icon :icon="$document->icon()" class="h-4 w-4 text-neutral-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <flux:text class="font-medium truncate">{{ $document->title }}</flux:text>
                            @if ($document->description)
                                <flux:text class="text-sm mt-1 text-neutral-400">{{ $document->description }}</flux:text>
                            @endif
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                                <flux:text class="text-xs text-neutral-500">{{ $document->humanSize() }}</flux:text>
                                <flux:text class="text-xs text-neutral-500">&middot;</flux:text>
                                <flux:text class="text-xs text-neutral-500">
                                    {{ __('Uploaded by') }} {{ $document->uploader->fullName() }}
                                </flux:text>
                                <flux:text class="text-xs text-neutral-500">&middot;</flux:text>
                                <flux:text class="text-xs text-neutral-500">{{ $document->created_at->diffForHumans() }}</flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon-only
                                title="{{ __('Download') }}"
                                wire:click="downloadDocument({{ $document->id }})"
                            >
                                <flux:icon.arrow-down-tray class="h-4 w-4" />
                            </flux:button>
                            @if ($this->canManageDocument($document))
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon-only
                                    title="{{ __('Edit') }}"
                                    wire:click="openEditDocumentModal({{ $document->id }})"
                                >
                                    <flux:icon.pencil class="h-4 w-4" />
                                </flux:button>
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon-only
                                    class="text-red-400/70 hover:text-red-400"
                                    title="{{ __('Delete') }}"
                                    x-on:click="confirmDestroy('{{ __('Delete document?') }}', '{{ __('Are you sure you want to delete this document? This action cannot be undone.') }}', () => $wire.deleteDocument({{ $document->id }}))"
                                >
                                    <flux:icon.trash class="h-4 w-4" />
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <flux:callout variant="subtle">
                <flux:text>{{ __('No documents yet.') }}</flux:text>
                <flux:text class="mt-2">{{ __('Click "Add Document" to attach tickets, reservations, or other files to this trip.') }}</flux:text>
            </flux:callout>
        @endif
    </div>
    @endif

    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700/50 p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Settle Up') }}</flux:heading>
            <flux:badge>{{ count($this->settlementTransfers) }}</flux:badge>
        </div>

        @if ($trip->expenses->count() > 0)
            <div class="mb-6">
                <flux:subheading class="mb-3">{{ __('Balances') }}</flux:subheading>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->balances as $balance)
                        <div wire:key="balance-{{ $balance['user']->id }}" class="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700">
                            <x-participant-avatar :name="$balance['user']->fullName()" :initials="$balance['user']->initials()" :color-slot="$trip->colorSlotFor($balance['user'])" size="sm" />
                            <flux:text class="text-sm">{{ $balance['user']->fullName() }}</flux:text>
                            @if ($balance['balanceCents'] > 0)
                                <flux:badge color="green">{{ __('is owed') }} {{ Money::format($balance['balanceCents'], $trip->currency ?? Currency::default()) }}</flux:badge>
                            @elseif ($balance['balanceCents'] < 0)
                                <flux:badge color="red">{{ __('owes') }} {{ Money::format(abs($balance['balanceCents']), $trip->currency ?? Currency::default()) }}</flux:badge>
                            @else
                                <flux:badge>{{ __('Settled up') }}</flux:badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <flux:subheading class="mb-3">{{ __('Suggested Transfers') }}</flux:subheading>
            @if (count($this->settlementTransfers) > 0)
                <div class="space-y-2">
                    @foreach ($this->settlementTransfers as $transfer)
                        <div wire:key="transfer-{{ $transfer['from']->id }}-{{ $transfer['to']->id }}" class="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700/50">
                            <div class="flex items-center gap-2">
                                <x-participant-avatar :name="$transfer['from']->fullName()" :initials="$transfer['from']->initials()" :color-slot="$trip->colorSlotFor($transfer['from'])" size="sm" />
                                <flux:text class="text-sm">{{ $transfer['from']->fullName() }}</flux:text>
                            </div>
                            <flux:icon.arrow-right class="h-4 w-4 text-neutral-400" />
                            <div class="flex items-center gap-2">
                                <x-participant-avatar :name="$transfer['to']->fullName()" :initials="$transfer['to']->initials()" :color-slot="$trip->colorSlotFor($transfer['to'])" size="sm" />
                                <flux:text class="text-sm">{{ $transfer['to']->fullName() }}</flux:text>
                            </div>
                            <div class="ml-auto flex items-center gap-3">
                                <flux:text class="font-semibold">{{ Money::format($transfer['amountCents'], $trip->currency ?? Currency::default()) }}</flux:text>
                                @if ($trip->user_id === Auth::id() || $transfer['from']->id === Auth::id() || $transfer['to']->id === Auth::id())
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        class="text-neutral-300 hover:text-white"
                                        x-on:click="confirmDestroy('{{ __('Mark transfer as settled?') }}', '{{ __('Are you sure you want to mark this transfer as settled? This cannot be undone.') }}', () => $wire.markTransferSettled({{ $transfer['from']->id }}, {{ $transfer['to']->id }}, {{ $transfer['amountCents'] }}), '{{ __('Mark as settled') }}', 'primary')"
                                    >
                                        {{ __('Mark as settled') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('Everyone is settled up!') }}</flux:text>
                </flux:callout>
            @endif

            @if ($this->recentSettlements->isNotEmpty())
                <flux:subheading class="mt-6 mb-3">{{ __('Recent Settlements') }}</flux:subheading>
                <div class="space-y-2">
                    @foreach ($this->recentSettlements as $settlement)
                        <div wire:key="settlement-{{ $settlement['id'] }}" class="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700/50 opacity-80">
                            <flux:icon.check-circle class="h-4 w-4 text-green-500" />
                            <div class="flex items-center gap-2">
                                <x-participant-avatar :name="$settlement['from']->fullName()" :initials="$settlement['from']->initials()" :color-slot="$trip->colorSlotFor($settlement['from'])" size="sm" />
                                <flux:text class="text-sm">{{ $settlement['from']->fullName() }}</flux:text>
                            </div>
                            <flux:icon.arrow-right class="h-4 w-4 text-neutral-400" />
                            <div class="flex items-center gap-2">
                                <x-participant-avatar :name="$settlement['to']->fullName()" :initials="$settlement['to']->initials()" :color-slot="$trip->colorSlotFor($settlement['to'])" size="sm" />
                                <flux:text class="text-sm">{{ $settlement['to']->fullName() }}</flux:text>
                            </div>
                            <flux:text class="ml-auto text-sm">{{ Money::format($settlement['amountCents'], $trip->currency ?? Currency::default()) }}</flux:text>
                            <flux:badge color="green" size="sm">{{ __('Settled') }}</flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <flux:callout variant="subtle">
                <flux:text>{{ __('No expenses added yet.') }}</flux:text>
            </flux:callout>
        @endif
    </div>

    <flux:modal
        name="voters-modal"
        :show="$showVotersModal"
        wire:model="showVotersModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            @if ($this->selectedLocationId)
                @php
                    $selectedLocation = $trip->locations->firstWhere('id', $this->selectedLocationId);
                @endphp
                <div>
                    <flux:heading size="lg">
                        {{ __('Voters for') }} "{{ $selectedLocation?->name }}"
                    </flux:heading>
                    <flux:subheading class="mt-1">
                        {{ __('People who voted for this location') }}
                    </flux:subheading>
                </div>

                @if ($this->selectedLocationVoters->count() > 0)
                    <div class="space-y-3">
                        @foreach ($this->selectedLocationVoters as $voter)
                            <div class="flex items-center gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700">
                                <x-participant-avatar
                                    :name="$voter->fullName()"
                                    :initials="$voter->initials()"
                                    :color-slot="$trip->colorSlotFor($voter)"
                                    size="sm"
                                />
                                <div class="flex-1">
                                    <flux:text class="font-medium">{{ $voter->fullName() }}</flux:text>
                                    <flux:text class="text-sm text-neutral-400">{{ $voter->email }}</flux:text>
                                </div>
                                <flux:badge variant="ghost" size="sm" class="bg-neutral-700/50 text-neutral-300">
                                    <flux:icon.heart class="h-3 w-3" />
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="subtle">
                        <flux:text>{{ __('No voters yet.') }}</flux:text>
                    </flux:callout>
                @endif
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" wire:click="closeVotersModal">
                        {{ __('Close') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="add-participant-modal"
        :show="$showAddParticipantModal"
        wire:model="showAddParticipantModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Participant') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Search for users by name or email') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:input
                    wire:model.live.debounce.300ms="participantSearch"
                    :label="__('Search Users')"
                    :placeholder="__('Type name or email...')"
                    autofocus
                />
            </flux:field>

            @if (!empty($participantSearch))
                @if ($this->searchableUsers->count() > 0)
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach ($this->searchableUsers as $user)
                            <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-800/50 transition-colors">
                                <div class="flex items-center gap-3 flex-1">
                                    <x-participant-avatar
                                        :name="$user->fullName()"
                                        :initials="$user->initials()"
                                        size="sm"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <flux:text class="font-medium">{{ $user->fullName() }}</flux:text>
                                        <flux:text class="text-sm text-neutral-400">{{ $user->email }}</flux:text>
                                    </div>
                                </div>
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    wire:click="addParticipant({{ $user->id }})"
                                >
                                    {{ __('Add') }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="subtle">
                        <flux:text>{{ __('No users found matching your search.') }}</flux:text>
                    </flux:callout>
                @endif
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('Start typing to search for users...') }}</flux:text>
                </flux:callout>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary" wire:click="closeAddParticipantModal">
                        {{ __('Close') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="add-comment-modal"
        :show="$showAddCommentModal"
        wire:model="showAddCommentModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            @if ($selectedLocationIdForComment)
                @php
                    $selectedLocation = $trip->locations->firstWhere('id', $selectedLocationIdForComment);
                @endphp
                <div>
                    <flux:heading size="lg">{{ __('Add Comment') }}</flux:heading>
                    <flux:subheading class="mt-1">
                        {{ __('Comment on') }} "{{ $selectedLocation?->name }}"
                    </flux:subheading>
                </div>

                <flux:field>
                    <flux:textarea
                        wire:model="commentTexts.{{ $selectedLocationIdForComment }}"
                        :label="__('Comment')"
                        :placeholder="__('Write your comment...')"
                        rows="4"
                        autofocus
                    />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button
                        variant="ghost"
                        wire:click="closeAddCommentModal"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        variant="primary"
                        wire:click="addComment"
                        wire:loading.attr="disabled"
                        wire:target="addComment"
                    >
                        <span wire:loading.remove wire:target="addComment">{{ __('Post Comment') }}</span>
                        <span wire:loading wire:target="addComment">{{ __('Posting...') }}</span>
                    </flux:button>
                </div>
            @endif
        </div>
    </flux:modal>

    <flux:modal
        name="edit-expense-modal"
        :show="$showEditExpenseModal"
        wire:model="showEditExpenseModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit Expense') }}</flux:heading>
            </div>

            <flux:field>
                <flux:input
                    wire:model="editingExpense.name"
                    :label="__('Expense Name')"
                    required
                    autofocus
                />
            </flux:field>

            <flux:field>
                <flux:textarea
                    wire:model="editingExpense.description"
                    :label="__('Description')"
                    rows="3"
                />
            </flux:field>

            <flux:field>
                <flux:input
                    wire:model="editingExpense.link"
                    type="url"
                    :label="__('Link')"
                    :placeholder="__('https://...')"
                />
            </flux:field>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:input
                        wire:model.live.debounce.300ms="editingExpense.unit_price"
                        type="number"
                        step="0.01"
                        :label="__('Unit Price')"
                        required
                        min="0"
                    />
                </flux:field>

                <flux:field>
                    <flux:input
                        wire:model.live.debounce.300ms="editingExpense.quantity"
                        type="number"
                        :label="__('Quantity')"
                        required
                        min="1"
                    />
                </flux:field>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <flux:field>
                    <flux:select wire:model.live="editingExpense.currency" :label="__('Currency')" required>
                        @foreach (\App\Enums\Currency::cases() as $currencyOption)
                            <option wire:key="currency-{{ $currencyOption->value }}" value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                @php
                    $editingExpenseCurrency = ($editingExpense['currency'] ?? '') ?: ($trip->currency ?? Currency::default())->value;
                @endphp

                @if ($editingExpenseCurrency !== $trip->currency?->value)
                    <flux:field>
                        <flux:input
                            wire:model="editingExpense.exchange_rate"
                            type="number"
                            step="0.000001"
                            :label="__('Exchange rate to :currency', ['currency' => $trip->currency->value])"
                            :description="__('1 :from = ___ :to', ['from' => $editingExpenseCurrency, 'to' => $trip->currency->value])"
                            required
                        />
                        <div wire:loading wire:target="editingExpense.currency">
                            <flux:text class="text-xs text-neutral-400 mt-1">{{ __("Looking up today's rate…") }}</flux:text>
                        </div>
                        @if (! ($editingExpense['exchange_rate'] ?? null))
                            <flux:text class="text-xs text-neutral-500 mt-1" wire:loading.remove wire:target="editingExpense.currency">
                                {{ __("Couldn't find today's rate automatically — enter it yourself.") }}
                            </flux:text>
                        @endif
                    </flux:field>
                @endif
            </div>

            @php
                // wire:model.live means editingExpense.currency is a
                // client-mutable public property — tryFrom() with a fallback
                // avoids a ValueError if it's ever tampered with into
                // something other than a real currency code.
                $editCurrencySymbol = (Currency::tryFrom($editingExpenseCurrency) ?? Currency::default())->symbol();
            @endphp

            @if (isset($editingExpense['unit_price']) && isset($editingExpense['quantity']) && is_numeric($editingExpense['unit_price']))
                <flux:callout variant="subtle" class="bg-neutral-700/30">
                    <flux:text>
                        <strong>{{ __('Total') }}:</strong> {{ $editCurrencySymbol }}{{ number_format((float) $editingExpense['unit_price'] * (int) $editingExpense['quantity'], 2) }}
                    </flux:text>
                </flux:callout>
            @endif

            <flux:field>
                <flux:select
                    wire:model="editingExpense.user_id"
                    :label="__('Owner')"
                    required
                >
                    <option value="{{ $trip->creator->id }}">{{ $trip->creator->fullName() }}</option>
                    @foreach ($trip->participants as $participant)
                        <option value="{{ $participant->id }}">{{ $participant->fullName() }}</option>
                    @endforeach
                </flux:select>
                @if (($editingExpense['user_id'] ?? null) && $editingExpense['user_id'] !== Auth::id())
                    <flux:text class="text-sm text-neutral-400 mt-1">
                        {{ __("You're editing this expense on behalf of :name.", ['name' => $trip->members()->firstWhere('id', $editingExpense['user_id'])?->fullName()]) }}
                    </flux:text>
                @endif
            </flux:field>

            <flux:separator />

            <flux:checkbox.group wire:model.live="editingExpense.participant_ids" :label="__('Split between')">
                @foreach ($trip->members() as $member)
                    <flux:checkbox wire:key="split-participant-{{ $member->id }}" value="{{ $member->id }}" :label="$member->fullName()" />
                @endforeach
            </flux:checkbox.group>
            @error('editingExpense.participant_ids')
                <flux:error>{{ $message }}</flux:error>
            @enderror
            @error('editingExpense.participant_ids.*')
                <flux:error>{{ $message }}</flux:error>
            @enderror

            <flux:field>
                <flux:select
                    wire:model.live="editingExpense.split_type"
                    :label="__('Split type')"
                >
                    <option value="equal">{{ __('Equal') }}</option>
                    <option value="percentage">{{ __('Percentage') }}</option>
                    <option value="fixed">{{ __('Fixed amount') }}</option>
                </flux:select>
            </flux:field>

            @php
                $editSelectedMembers = $trip->members()->whereIn('id', $editingExpense['participant_ids'] ?? []);
            @endphp

            @if (($editingExpense['split_type'] ?? 'equal') === 'percentage')
                <div class="space-y-2">
                    @foreach ($editSelectedMembers as $member)
                        <div wire:key="split-percentage-{{ $member->id }}" class="flex items-center gap-3">
                            <flux:text class="flex-1 text-sm">{{ $member->fullName() }}</flux:text>
                            <flux:input
                                wire:model.live="editingExpense.percentages.{{ $member->id }}"
                                type="number"
                                step="0.01"
                                class="w-28"
                                suffix="%"
                            />
                        </div>
                    @endforeach
                    @php
                        $editPercentageSum = array_sum(array_map('floatval', $editingExpense['percentages'] ?? []));
                    @endphp
                    <flux:text class="text-sm {{ abs($editPercentageSum - 100) <= 0.5 ? 'text-green-500' : 'text-red-500' }}">
                        {{ number_format($editPercentageSum, 2) }}% / 100%
                    </flux:text>
                    @error('percentages')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @elseif (($editingExpense['split_type'] ?? 'equal') === 'fixed')
                <div class="space-y-2">
                    @foreach ($editSelectedMembers as $member)
                        <div wire:key="split-fixed-{{ $member->id }}" class="flex items-center gap-3">
                            <flux:text class="flex-1 text-sm">{{ $member->fullName() }}</flux:text>
                            <flux:input
                                wire:model.live="editingExpense.fixed_amounts.{{ $member->id }}"
                                type="number"
                                step="0.01"
                                class="w-28"
                                :prefix="$editCurrencySymbol"
                            />
                        </div>
                    @endforeach
                    @php
                        $editFixedSum = array_sum(array_map('floatval', $editingExpense['fixed_amounts'] ?? []));
                        $editFixedTotal = (is_numeric($editingExpense['unit_price'] ?? null) ? (float) $editingExpense['unit_price'] : 0) * (int) ($editingExpense['quantity'] ?? 0);
                    @endphp
                    <flux:text class="text-sm {{ abs($editFixedSum - $editFixedTotal) < 0.005 ? 'text-green-500' : 'text-red-500' }}">
                        {{ $editCurrencySymbol }}{{ number_format($editFixedSum, 2) }} / {{ $editCurrencySymbol }}{{ number_format($editFixedTotal, 2) }}
                    </flux:text>
                    @error('fixed_amounts')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>
            @else
                <flux:text class="text-sm text-neutral-400">
                    {{ __('Each of the :count selected members pays an equal share.', ['count' => count($editingExpense['participant_ids'] ?? [])]) }}
                </flux:text>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="closeEditExpenseModal">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="saveExpense({{ $editingExpenseId }})"
                    wire:loading.attr="disabled"
                    wire:target="saveExpense"
                >
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="add-document-modal"
        :show="$showAddDocumentModal"
        wire:model="showAddDocumentModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Document') }}</flux:heading>
                <flux:subheading class="mt-1">
                    {{ __('Attach a ticket, reservation, or other file to this trip.') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:input wire:model="newDocumentTitle" :label="__('Title')" autofocus />
            </flux:field>

            <flux:field>
                <flux:textarea wire:model="newDocumentDescription" :label="__('Description')" rows="3" />
            </flux:field>

            <flux:field>
                <flux:input type="file" wire:model="newDocument" :label="__('File')" />
                <div wire:loading wire:target="newDocument">
                    <flux:text class="text-sm text-neutral-400 mt-1">{{ __('Uploading...') }}</flux:text>
                </div>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button
                    variant="ghost"
                    wire:click="closeAddDocumentModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="addDocument"
                    wire:loading.attr="disabled"
                    wire:target="addDocument,newDocument"
                >
                    <span wire:loading.remove wire:target="addDocument">{{ __('Add Document') }}</span>
                    <span wire:loading wire:target="addDocument">{{ __('Saving...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="edit-document-modal"
        :show="$showEditDocumentModal"
        wire:model="showEditDocumentModal"
        focusable
        class="max-w-lg"
    >
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Edit Document') }}</flux:heading>

            <flux:field>
                <flux:input wire:model="editingDocument.title" :label="__('Title')" autofocus />
            </flux:field>

            <flux:field>
                <flux:textarea wire:model="editingDocument.description" :label="__('Description')" rows="3" />
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:button
                    variant="ghost"
                    wire:click="closeEditDocumentModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="updateDocument({{ $editingDocumentId }})"
                    wire:loading.attr="disabled"
                    wire:target="updateDocument"
                >
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <x-confirm-modal wire-target="delete,deleteLocation,deleteComment,deleteExpense,removeParticipant,markTransferSettled,deleteDocument" />
</div>
