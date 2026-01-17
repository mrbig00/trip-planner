<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\LocationComment;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
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

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        $this->trip = $trip->load(['creator', 'participants', 'locations.votes', 'locations.comments.user', 'expenses.owner']);
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
    public function getSelectedLocationVotersProperty(): Collection
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
        if ($this->trip->user_id !== Auth::id()) {
            abort(403);
        }

        $user = User::findOrFail($userId);

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
