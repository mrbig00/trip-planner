<?php

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use function Livewire\Volt\layout;

layout('components.layouts.app');

new class extends Component {
    public Trip $trip;
    public ?int $selectedLocationId = null;
    public bool $showVotersModal = false;
    public bool $showAddParticipantModal = false;
    public string $participantSearch = '';

    public ?int $editingExpenseId = null;
    public array $editingExpense = [
        'name' => '',
        'description' => '',
        'link' => '',
        'unit_price' => '',
        'quantity' => 1,
        'user_id' => null,
    ];

    public array $commentTexts = [];
    public ?int $expandedLocationId = null;
    public bool $showAddCommentModal = false;
    public ?int $selectedLocationIdForComment = null;

    public function updatedExpandedLocationId(): void
    {
        $this->trip->refresh();
    }

    public function with(): array
    {
        return ['title' => $this->trip->name];
    }

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        $this->trip = $trip->load(['creator', 'participants', 'locations.votes', 'locations.comments.user', 'expenses.owner']);
    }

    /**
     * Delete the trip.
     */
    public function delete(): void
    {
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->trip->delete();

        $this->redirect(route('trips.index'), navigate: true);
    }

    /**
     * Accept a location and unaccept all others.
     */
    public function acceptLocation(int $locationId): void
    {
        $location = $this->trip->locations()->findOrFail($locationId);

        $location->accept();

        $this->trip->refresh();
    }

    /**
     * Delete a location.
     */
    public function deleteLocation(int $locationId): void
    {
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $location = $this->trip->locations()->findOrFail($locationId);
        $location->delete();

        $this->trip->refresh();
    }

    /**
     * Toggle vote for a location.
     */
    public function toggleVote(int $locationId): void
    {
        $location = $this->trip->locations()->findOrFail($locationId);
        $location->toggleVote(Auth::user());

        $this->trip->refresh();
    }

    /**
     * Show voters for a location.
     */
    public function showVoters(int $locationId): void
    {
        $this->selectedLocationId = $locationId;
        $this->showVotersModal = true;
    }

    /**
     * Close the voters modal.
     */
    public function closeVotersModal(): void
    {
        $this->showVotersModal = false;
        $this->selectedLocationId = null;
    }

    /**
     * Get the selected location's voters.
     */
    public function getSelectedLocationVotersProperty()
    {
        if (!$this->selectedLocationId) {
            return collect();
        }

        $location = $this->trip->locations()->find($this->selectedLocationId);

        return $location?->votes ?? collect();
    }

    /**
     * Get users that can be added as participants.
     */
    public function getSearchableUsersProperty()
    {
        if (empty($this->participantSearch)) {
            return collect();
        }

        $participantIds = $this->trip->participants->pluck('id')->push($this->trip->user_id);

        return \App\Models\User::query()
            ->whereNotIn('id', $participantIds)
            ->where(function ($query) {
                $query->where('email', 'like', "%{$this->participantSearch}%")
                    ->orWhere('first_name', 'like', "%{$this->participantSearch}%")
                    ->orWhere('last_name', 'like', "%{$this->participantSearch}%");
            })
            ->limit(10)
            ->get();
    }

    /**
     * Add a participant to the trip.
     */
    public function addParticipant(int $userId): void
    {
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $user = \App\Models\User::findOrFail($userId);

        // Don't add the trip creator or if already a participant
        if ($user->id === $this->trip->user_id || $this->trip->participants->contains($user->id)) {
            return;
        }

        $this->trip->participants()->attach($userId);

        $this->participantSearch = '';
        $this->trip->refresh();
    }

    /**
     * Remove a participant from the trip.
     */
    public function removeParticipant(int $userId): void
    {
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->trip->participants()->detach($userId);

        $this->trip->refresh();
    }

    /**
     * Open the add participant modal.
     */
    public function openAddParticipantModal(): void
    {
        $this->showAddParticipantModal = true;
        $this->participantSearch = '';
    }

    /**
     * Close the add participant modal.
     */
    public function closeAddParticipantModal(): void
    {
        $this->showAddParticipantModal = false;
        $this->participantSearch = '';
    }

    /**
     * Open the add comment modal for a location.
     */
    public function openAddCommentModal(int $locationId): void
    {
        $this->selectedLocationIdForComment = $locationId;
        $this->commentTexts[$locationId] = '';
        $this->showAddCommentModal = true;
    }

    /**
     * Close the add comment modal.
     */
    public function closeAddCommentModal(): void
    {
        $this->showAddCommentModal = false;
        if ($this->selectedLocationIdForComment) {
            $this->commentTexts[$this->selectedLocationIdForComment] = '';
        }
        $this->selectedLocationIdForComment = null;
    }

    /**
     * Add a comment to a location.
     */
    public function addComment(): void
    {
        if (!$this->selectedLocationIdForComment) {
            return;
        }

        $locationId = $this->selectedLocationIdForComment;

        $this->validate([
            "commentTexts.{$locationId}" => ['required', 'string', 'max:1000'],
        ]);

        $location = $this->trip->locations()->findOrFail($locationId);

        $location->comments()->create([
            'user_id' => Auth::id(),
            'content' => $this->commentTexts[$locationId],
        ]);

        $this->commentTexts[$locationId] = '';
        $this->trip->refresh();
        $this->closeAddCommentModal();
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(int $commentId): void
    {
        $comment = \App\Models\LocationComment::findOrFail($commentId);

        // Only allow deleting own comments or trip creator can delete any
        if ($comment->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $comment->delete();

        $this->trip->refresh();
    }

    /**
     * Toggle expanded state for location comments.
     */
    public function toggleLocationComments(int $locationId): void
    {
        if ($this->expandedLocationId === $locationId) {
            $this->expandedLocationId = null;
        } else {
            $this->expandedLocationId = $locationId;
        }
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(int $expenseId): void
    {
        $expense = $this->trip->expenses()->findOrFail($expenseId);

        // Only expense owner or trip creator can delete
        if ($expense->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $expense->delete();

        $this->trip->refresh();
    }

    /**
     * Start editing an expense inline.
     */
    public function startEditingExpense(int $expenseId): void
    {
        $expense = $this->trip->expenses()->findOrFail($expenseId);

        // Only expense owner or trip creator can edit
        if ($expense->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->editingExpenseId = $expenseId;
        $this->editingExpense = [
            'name' => $expense->name,
            'description' => $expense->description ?? '',
            'link' => $expense->link ?? '',
            'unit_price' => (string) $expense->unit_price,
            'quantity' => $expense->quantity,
            'user_id' => $expense->user_id ?? $this->trip->user_id,
        ];
    }

    /**
     * Cancel editing an expense.
     */
    public function cancelEditingExpense(): void
    {
        $this->editingExpenseId = null;
        $this->editingExpense = [];
    }

    /**
     * Save the edited expense.
     */
    public function saveExpense(int $expenseId): void
    {
        $expense = $this->trip->expenses()->findOrFail($expenseId);

        // Only expense owner or trip creator can update
        if ($expense->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $eligibleUserIds = $this->trip->participants->pluck('id')->push($this->trip->user_id)->unique()->toArray();

        $validated = $this->validate([
            'editingExpense.name' => ['required', 'string', 'max:255'],
            'editingExpense.description' => ['nullable', 'string', 'max:1000'],
            'editingExpense.link' => ['nullable', 'url', 'max:255'],
            'editingExpense.unit_price' => ['required', 'numeric', 'min:0'],
            'editingExpense.quantity' => ['required', 'integer', 'min:1'],
            'editingExpense.user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
        ]);

        $expense->update([
            'name' => $validated['editingExpense']['name'],
            'description' => $validated['editingExpense']['description'] ?? null,
            'link' => $validated['editingExpense']['link'] ?? null,
            'unit_price' => $validated['editingExpense']['unit_price'],
            'quantity' => $validated['editingExpense']['quantity'],
            'user_id' => $validated['editingExpense']['user_id'],
        ]);

        $this->cancelEditingExpense();
        $this->trip->refresh();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
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
        </div>
        @if ($trip->user_id === Auth::id())
            <div class="flex items-center gap-2">
                <flux:button variant="ghost" :href="route('trips.edit', $trip)" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete this trip?') }}">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        @endif
    </div>

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
            @if ($trip->locations->count() > 0)
                <div class="space-y-3">
                    @foreach ($trip->locations as $location)
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
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="showVoters({{ $location->id }})"
                                                class="bg-neutral-700/50 text-neutral-300 hover:bg-neutral-700 hover:text-white h-auto py-1 px-2"
                                            >
                                                <flux:icon.heart class="h-3 w-3" />
                                                {{ $voteCount }}
                                            </flux:button>
                                        @endif
                                    </div>
                                    @if ($location->price)
                                        <flux:text class="text-sm mt-1">
                                            {{ __('Price') }}: ${{ number_format($location->price, 2) }}
                                        </flux:text>
                                    @endif
                                    @if ($location->latitude && $location->longitude)
                                        <flux:text class="text-xs mt-1 opacity-70">
                                            {{ number_format($location->latitude, 6) }}, {{ number_format($location->longitude, 6) }}
                                        </flux:text>
                                    @endif
                                    @if ($location->link)
                                        <flux:link :href="$location->link" target="_blank" class="text-xs mt-1 text-blue-400 hover:text-blue-300">
                                            {{ __('View Link') }}
                                        </flux:link>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                <flux:button
                                    variant="{{ $hasVoted ? 'primary' : 'ghost' }}"
                                    size="sm"
                                    wire:click="toggleVote({{ $location->id }})"
                                    class="{{ $hasVoted ? '' : 'text-neutral-300 hover:text-white' }}"
                                >
                                    <flux:icon.heart class="h-4 w-4" />
                                    {{ $hasVoted ? __('Voted') : __('Vote') }}
                                </flux:button>
                                @if ($location->accepted)
                                    <flux:badge variant="success">{{ __('Accepted') }}</flux:badge>
                                @elseif ($trip->user_id === Auth::id())
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="acceptLocation({{ $location->id }})"
                                        class="text-neutral-300 hover:text-white"
                                    >
                                        {{ __('Accept') }}
                                    </flux:button>
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
                                            <flux:menu.item wire:click="deleteLocation({{ $location->id }})" wire:confirm="{{ __('Are you sure you want to delete this location?') }}">
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
                                                <div class="flex items-start gap-3 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700/20">
                                                    <flux:avatar
                                                        :name="$comment->user->fullName()"
                                                        :initials="$comment->user->initials()"
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
                                                            wire:click="deleteComment({{ $comment->id }})"
                                                            wire:confirm="{{ __('Are you sure you want to delete this comment?') }}"
                                                            class="text-neutral-400 hover:text-red-400"
                                                            title="{{ __('Delete comment') }}"
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
                </div>
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('No locations added yet.') }}</flux:text>
                    </flux:callout>
            @endif
        </div>

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
                                        $canEditExpense = $expense->user_id === Auth::id() || $trip->user_id === Auth::id();
                                    @endphp
                                    @if ($editingExpenseId === $expense->id && $canEditExpense)
                                        {{-- Edit Mode --}}
                                        <td class="px-4 py-3">
                                            <flux:input
                                                wire:model="editingExpense.name"
                                                class="h-8 text-sm"
                                                required
                                                autofocus
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:textarea
                                                wire:model="editingExpense.description"
                                                class="min-h-[60px] text-sm"
                                                :placeholder="__('Description...')"
                                                rows="2"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:input
                                                wire:model="editingExpense.link"
                                                type="url"
                                                class="h-8 text-sm"
                                                :placeholder="__('https://...')"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:select
                                                wire:model="editingExpense.user_id"
                                                class="h-8 text-sm"
                                                required
                                            >
                                                <option value="{{ $trip->creator->id }}">{{ $trip->creator->fullName() }}</option>
                                                @foreach ($trip->participants as $participant)
                                                    <option value="{{ $participant->id }}">{{ $participant->fullName() }}</option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <span class="text-neutral-400">$</span>
                                                <flux:input
                                                    wire:model="editingExpense.unit_price"
                                                    type="number"
                                                    step="0.01"
                                                    class="h-8 text-sm text-right"
                                                    required
                                                    min="0"
                                                />
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <flux:input
                                                wire:model="editingExpense.quantity"
                                                type="number"
                                                class="h-8 text-sm text-right"
                                                required
                                                min="1"
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:text class="font-semibold">
                                                ${{ isset($editingExpense['unit_price']) && isset($editingExpense['quantity']) ? number_format((float) $editingExpense['unit_price'] * (int) $editingExpense['quantity'], 2) : '0.00' }}
                                            </flux:text>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon-only
                                                    wire:click="saveExpense({{ $expense->id }})"
                                                    class="text-green-400 hover:text-green-300"
                                                    title="{{ __('Save') }}"
                                                >
                                                    <flux:icon.check class="h-4 w-4" />
                                                </flux:button>
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon-only
                                                    wire:click="cancelEditingExpense"
                                                    class="text-neutral-400 hover:text-neutral-300"
                                                    title="{{ __('Cancel') }}"
                                                >
                                                    <flux:icon.x-mark class="h-4 w-4" />
                                                </flux:button>
                                            </div>
                                        </td>
                                    @else
                                        {{-- View Mode --}}
                                        <td class="px-4 py-3">
                                            <flux:text class="font-medium">{{ $expense->name }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($expense->description)
                                                <flux:text class="text-sm text-neutral-400 line-clamp-1">{{ $expense->description }}</flux:text>
                                            @else
                                                <flux:text class="text-sm text-neutral-500">—</flux:text>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($expense->link)
                                                <flux:link :href="$expense->link" target="_blank" class="text-sm text-blue-400 hover:text-blue-300">
                                                    {{ __('Open') }}
                                                </flux:link>
                                            @else
                                                <flux:text class="text-sm text-neutral-500">—</flux:text>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($expense->owner)
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar
                                                        :name="$expense->owner->fullName()"
                                                        :initials="$expense->owner->initials()"
                                                        size="xs"
                                                    />
                                                    <flux:text class="text-sm">{{ $expense->owner->fullName() }}</flux:text>
                                                </div>
                                            @else
                                                <flux:text class="text-sm text-neutral-500">—</flux:text>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:text>${{ number_format($expense->unit_price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:text>{{ $expense->quantity }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <flux:text class="font-semibold">${{ number_format($expense->total, 2) }}</flux:text>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                @if ($canEditExpense)
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon-only
                                                        wire:click="startEditingExpense({{ $expense->id }})"
                                                        class="text-neutral-400 hover:text-white"
                                                        title="{{ __('Edit') }}"
                                                    >
                                                        <flux:icon.pencil class="h-4 w-4" />
                                                    </flux:button>
                                                @endif
                                                @if ($canEditExpense)
                                                    <flux:dropdown>
                                                        <flux:button variant="ghost" size="sm" icon-only class="text-neutral-400 hover:text-white">
                                                            <flux:icon.ellipsis-vertical />
                                                        </flux:button>
                                                        <flux:menu>
                                                            <flux:menu.item :href="route('expenses.edit', [$trip, $expense])" wire:navigate>
                                                                {{ __('Edit in Page') }}
                                                            </flux:menu.item>
                                                            <flux:menu.item wire:click="deleteExpense({{ $expense->id }})" wire:confirm="{{ __('Are you sure you want to delete this expense?') }}">
                                                                {{ __('Delete') }}
                                                            </flux:menu.item>
                                                        </flux:menu>
                                                    </flux:dropdown>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $totalExpenses = $trip->expenses->sum('total');
                            @endphp
                            <tr class="border-t-2 border-neutral-700 bg-neutral-800/30">
                                <td class="px-4 py-3" colspan="4">
                                    <flux:text class="font-semibold">{{ __('Total') }}</flux:text>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:text class="font-semibold text-lg">${{ number_format($totalExpenses, 2) }}</flux:text>
                                </td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <flux:callout variant="subtle">
                    <flux:text>{{ __('No expenses added yet.') }}</flux:text>
                    </flux:callout>
            @endif
        </div>
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
                    <div class="flex items-center gap-2 p-2 rounded-lg border border-neutral-200 dark:border-neutral-700">
                        <flux:avatar :name="$participant->fullName()" :initials="$participant->initials()" size="sm" />
                        <flux:badge>{{ $participant->fullName() }}</flux:badge>
                        @if ($trip->user_id === Auth::id())
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon-only
                                wire:click="removeParticipant({{ $participant->id }})"
                                wire:confirm="{{ __('Are you sure you want to remove this participant?') }}"
                                class="text-neutral-400 hover:text-red-400"
                                title="{{ __('Remove participant') }}"
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
                                <flux:avatar
                                    :name="$voter->fullName()"
                                    :initials="$voter->initials()"
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
                                    <flux:avatar
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
</div>
