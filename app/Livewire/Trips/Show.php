<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use App\Models\User;
use App\Support\Money;
use Livewire\Component;
use Illuminate\View\View;
use App\Models\Settlement;
use App\Enums\ExpenseSplitType;
use App\Models\LocationComment;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Actions\Expenses\BuildExpenseShares;
use App\Actions\Expenses\ValidateExpenseSplit;
use App\Actions\Expenses\CalculateSettlementPlan;

class Show extends Component
{
    public Trip $trip;

    public ?int $selectedLocationId = null;

    public bool $showVotersModal = false;

    public bool $showAddParticipantModal = false;

    public string $participantSearch = '';

    public bool $showEditExpenseModal = false;

    public ?int $editingExpenseId = null;

    public array $editingExpense = [
        'name' => '',
        'description' => '',
        'link' => '',
        'unit_price' => '',
        'quantity' => 1,
        'user_id' => null,
        'split_type' => 'equal',
        'participant_ids' => [],
        'percentages' => [],
        'fixed_amounts' => [],
    ];

    public array $commentTexts = [];

    public ?int $expandedLocationId = null;

    public bool $showAddCommentModal = false;

    public ?int $selectedLocationIdForComment = null;

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        $this->trip = $trip->load(['creator', 'participants', 'locations.votes', 'locations.comments.user', 'expenses.owner', 'expenses.shares', 'settlements']);
    }

    public function updatedExpandedLocationId(): void
    {
        $this->trip->refresh();
    }

    /**
     * Delete the trip.
     */
    public function delete(): void
    {
        $this->ensureIsCreator();

        $this->trip->delete();

        $this->redirect(route('trips.index'), navigate: true);
    }

    /**
     * Accept a location and unaccept all others.
     */
    public function acceptLocation(int $locationId): void
    {
        $this->ensureIsCreatorOrParticipant();

        $location = $this->trip->locations()->findOrFail($locationId);

        $location->accept();

        $this->trip->refresh();
    }

    /**
     * Delete a location.
     */
    public function deleteLocation(int $locationId): void
    {
        $this->ensureIsCreator();

        $location = $this->trip->locations()->findOrFail($locationId);
        $location->delete();

        $this->trip->refresh();
    }

    /**
     * Toggle vote for a location.
     */
    public function toggleVote(int $locationId): void
    {
        $this->ensureIsCreatorOrParticipant();

        $location = $this->trip->locations()->findOrFail($locationId);
        $location->toggleVote(Auth::user());

        $this->trip->refresh();
    }

    /**
     * Show voters for a location.
     */
    public function showVoters(int $locationId): void
    {
        $this->ensureIsCreatorOrParticipant();

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
    public function getSelectedLocationVotersProperty(): Collection
    {
        if (! $this->selectedLocationId) {
            return collect();
        }

        $location = $this->trip->locations()->find($this->selectedLocationId);

        return $location?->votes ?? collect();
    }

    /**
     * Get users that can be added as participants.
     */
    public function getSearchableUsersProperty(): Collection
    {
        if (empty($this->participantSearch)) {
            return collect();
        }

        $participantIds = $this->trip->participants->pluck('id')->push($this->trip->user_id);

        return User::query()
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
        $this->ensureIsCreator();

        $user = User::findOrFail($userId);

        // Don't add the trip creator or if already a participant
        if ($user->id === $this->trip->user_id || $this->trip->participants->contains($user->id)) {
            return;
        }

        // Fixed color-slot cycle; slot 1 is reserved for the creator (see
        // Trip::colorSlotFor()) so participants start from slot 2 and wrap
        // back to slot 1 once 5 colors are already in use.
        $slotCycle = [2, 3, 5, 7, 1];
        $slot = $slotCycle[$this->trip->participants()->count() % 5];

        $this->trip->participants()->attach($userId, ['color_slot' => $slot]);

        $this->participantSearch = '';
        $this->trip->refresh();
    }

    /**
     * Remove a participant from the trip.
     */
    public function removeParticipant(int $userId): void
    {
        $this->ensureIsCreator();

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
        $this->ensureIsCreatorOrParticipant();

        if (! $this->selectedLocationIdForComment) {
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
        $comment = LocationComment::findOrFail($commentId);

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
     * Open the edit expense modal.
     */
    public function openEditExpenseModal(int $expenseId): void
    {
        $expense = $this->trip->expenses()->with('shares')->findOrFail($expenseId);

        // Only expense owner or trip creator can edit
        if ($expense->user_id !== Auth::id() && $this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $this->resetValidation();
        $this->editingExpenseId = $expenseId;
        $this->editingExpense = [
            'name' => $expense->name,
            'description' => $expense->description ?? '',
            'link' => $expense->link ?? '',
            'unit_price' => (string) $expense->unit_price,
            'quantity' => $expense->quantity,
            'user_id' => $expense->user_id ?? $this->trip->user_id,
            'split_type' => $expense->split_type->value,
            'participant_ids' => $expense->shares->isNotEmpty()
                ? $expense->shares->pluck('user_id')->all()
                : $this->trip->members()->pluck('id')->all(),
            'percentages' => $expense->shares->pluck('percentage', 'user_id')->filter(fn ($percentage) => $percentage !== null)->map(fn ($percentage) => (string) $percentage)->all(),
            'fixed_amounts' => $expense->shares->pluck('amount', 'user_id')->map(fn ($amount) => (string) $amount)->all(),
        ];
        $this->showEditExpenseModal = true;
    }

    /**
     * Prune stale split inputs whenever the selected participants or split type change.
     */
    public function updated(string $name): void
    {
        if ($name === 'editingExpense.participant_ids' || $name === 'editingExpense.split_type') {
            $selected = $this->editingExpense['participant_ids'] ?? [];
            $this->editingExpense['percentages'] = collect($this->editingExpense['percentages'] ?? [])->only($selected)->all();
            $this->editingExpense['fixed_amounts'] = collect($this->editingExpense['fixed_amounts'] ?? [])->only($selected)->all();
        }
    }

    /**
     * Close the edit expense modal.
     */
    public function closeEditExpenseModal(): void
    {
        $this->resetValidation();
        $this->showEditExpenseModal = false;
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

        $eligibleUserIds = $this->trip->members()->pluck('id')->all();

        $validated = $this->validate([
            'editingExpense.name' => ['required', 'string', 'max:255'],
            'editingExpense.description' => ['nullable', 'string', 'max:1000'],
            'editingExpense.link' => ['nullable', 'url', 'max:255'],
            'editingExpense.unit_price' => ['required', 'numeric', 'min:0'],
            'editingExpense.quantity' => ['required', 'integer', 'min:1'],
            'editingExpense.user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
            'editingExpense.split_type' => ['required', Rule::enum(ExpenseSplitType::class)],
            'editingExpense.participant_ids' => ['required', 'array', 'min:1'],
            'editingExpense.participant_ids.*' => ['integer', 'in:'.implode(',', $eligibleUserIds), 'distinct'],
        ]);

        $splitType = ExpenseSplitType::from($validated['editingExpense']['split_type']);
        $totalCents = (int) bcmul(bcmul((string) $validated['editingExpense']['unit_price'], '100', 0), (string) $validated['editingExpense']['quantity'], 0);

        app(ValidateExpenseSplit::class)->validate(
            $splitType,
            $this->editingExpense['participant_ids'],
            $this->editingExpense['percentages'],
            $this->editingExpense['fixed_amounts'],
            $totalCents
        );

        DB::transaction(function () use ($expense, $validated, $splitType, $totalCents) {
            $expense->update([
                'name' => $validated['editingExpense']['name'],
                'description' => $validated['editingExpense']['description'] ?? null,
                'link' => $validated['editingExpense']['link'] ?? null,
                'unit_price' => $validated['editingExpense']['unit_price'],
                'quantity' => $validated['editingExpense']['quantity'],
                'user_id' => $validated['editingExpense']['user_id'],
                'split_type' => $splitType->value,
            ]);

            $expense->shares()->delete();
            $expense->shares()->createMany(
                app(BuildExpenseShares::class)->build(
                    $totalCents,
                    $splitType,
                    $validated['editingExpense']['participant_ids'],
                    $this->editingExpense['percentages'],
                    $this->editingExpense['fixed_amounts']
                )
            );
        });

        $this->closeEditExpenseModal();
        $this->trip->refresh();
    }

    /**
     * Get the trip's total spend across all expenses.
     */
    public function getTotalExpensesProperty(): float
    {
        return (float) $this->trip->expenses->sum('total');
    }

    /**
     * Get each trip member's share of total spending, for the Cost Breakdown
     * chart. Uses each expense's shares (who's responsible for how much),
     * not who fronted the cash — the shares always sum exactly to each
     * expense's total (see App\Actions\Expenses\BuildExpenseShares), so this
     * always reconciles with getTotalExpensesProperty().
     */
    public function getCostBreakdownProperty(): Collection
    {
        $members = $this->trip->members()->keyBy('id');

        return $this->trip->expenses
            ->flatMap->shares
            ->groupBy('user_id')
            ->map(function ($shares, $userId) use ($members) {
                $user = $members->get((int) $userId);

                return [
                    'user' => $user,
                    'amountCents' => $shares->sum(fn ($share) => Money::toCents((string) $share->amount)),
                    'slot' => $user ? $this->trip->colorSlotFor($user) : null,
                ];
            })
            ->filter(fn ($row) => $row['user'] !== null)
            ->sortByDesc('amountCents')
            ->values();
    }

    /**
     * Get each trip member's balance, keyed for the Settle Up card.
     */
    public function getBalancesProperty(): Collection
    {
        $members = $this->trip->members()->keyBy('id');

        return $this->trip->balances()->map(fn ($cents, $userId) => [
            'user' => $members->get($userId),
            'balanceCents' => $cents,
        ])->values();
    }

    /**
     * Get the minimal transfer plan that settles all balances.
     */
    public function getSettlementTransfersProperty(): array
    {
        $members = $this->trip->members()->keyBy('id');

        return collect(app(CalculateSettlementPlan::class)->calculate($this->trip->balances()))
            ->map(fn ($transfer) => [
                'from' => $members->get($transfer['from']),
                'to' => $members->get($transfer['to']),
                'amountCents' => $transfer['amount'],
            ])->all();
    }

    /**
     * Record a suggested transfer as settled.
     *
     * The amount is re-derived from the live server-side balances rather than
     * trusted outright: `wire:click` arguments are rendered into the DOM, so a
     * tampered value must never be allowed to exceed what's actually owed.
     */
    public function markTransferSettled(int $fromUserId, int $toUserId, int $amountCents): void
    {
        $memberIds = $this->trip->members()->pluck('id');

        if ($fromUserId === $toUserId || ! $memberIds->contains($fromUserId) || ! $memberIds->contains($toUserId)) {
            abort(403);
        }

        $this->ensureCanRecordSettlement($fromUserId, $toUserId);

        if ($amountCents <= 0) {
            abort(422);
        }

        $balances = $this->trip->balances();
        $maxSettleable = min(
            max(0, -$balances->get($fromUserId, 0)),
            max(0, $balances->get($toUserId, 0)),
        );

        if ($amountCents > $maxSettleable) {
            abort(422);
        }

        $this->trip->settlements()->create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'amount_cents' => $amountCents,
            'recorded_by_user_id' => Auth::id(),
        ]);

        $this->trip->refresh();
    }

    /**
     * Get the trip's most recent settlements, for a small read-only history list.
     */
    public function getRecentSettlementsProperty(): Collection
    {
        $members = $this->trip->members()->keyBy('id');

        return $this->trip->settlements
            ->sortByDesc('created_at')
            ->take(5)
            ->map(fn (Settlement $settlement) => [
                'id' => $settlement->id,
                'from' => $members->get($settlement->from_user_id),
                'to' => $members->get($settlement->to_user_id),
                'amountCents' => $settlement->amount_cents,
            ])->values();
    }

    /**
     * Abort with a 403 unless the current user is the trip creator.
     */
    private function ensureIsCreator(): void
    {
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }
    }

    /**
     * Abort with a 403 unless the current user is the trip creator or a participant.
     */
    private function ensureIsCreatorOrParticipant(): void
    {
        if ($this->trip->user_id !== Auth::id() && ! $this->trip->participants->contains(Auth::id())) {
            abort(403);
        }
    }

    /**
     * Abort with a 403 unless the current user is the trip creator or one of the
     * two specific parties on this transfer (deliberately narrower than
     * ensureIsCreatorOrParticipant: an unrelated participant shouldn't be able
     * to fabricate a settlement between two other members).
     */
    private function ensureCanRecordSettlement(int $fromUserId, int $toUserId): void
    {
        $userId = Auth::id();

        if ($userId !== $this->trip->user_id && $userId !== $fromUserId && $userId !== $toUserId) {
            abort(403);
        }
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.trips.show', [
            'title' => $this->trip->name,
        ]);
    }
}
