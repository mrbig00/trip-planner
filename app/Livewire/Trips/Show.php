<?php

declare(strict_types=1);

namespace App\Livewire\Trips;

use App\Models\Trip;
use App\Models\User;
use App\Enums\Currency;
use App\Models\Expense;
use Livewire\Component;
use Illuminate\View\View;
use App\Models\Settlement;
use App\Models\TripDocument;
use Livewire\WithFileUploads;
use App\Enums\ExpenseSplitType;
use App\Models\LocationComment;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Actions\Trips\BuildActivityFeed;
use App\Actions\Expenses\FetchExchangeRate;
use App\Actions\Expenses\BuildExpenseShares;
use App\Actions\Expenses\BuildBalanceSummary;
use App\Actions\Expenses\ValidateExpenseSplit;
use App\Livewire\Concerns\TracksAnalyticsEvents;

class Show extends Component
{
    use TracksAnalyticsEvents, WithFileUploads;

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
        'currency' => '',
        'exchange_rate' => '',
        'participant_ids' => [],
        'percentages' => [],
        'fixed_amounts' => [],
    ];

    public array $commentTexts = [];

    public ?int $expandedLocationId = null;

    public bool $showAllLocations = false;

    public bool $showAddCommentModal = false;

    public ?int $selectedLocationIdForComment = null;

    public bool $showAddDocumentModal = false;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $newDocument = null;

    public string $newDocumentTitle = '';

    public ?string $newDocumentDescription = null;

    public bool $showEditDocumentModal = false;

    public ?int $editingDocumentId = null;

    public array $editingDocument = [
        'title' => '',
        'description' => '',
    ];

    /**
     * Every relation the view needs eager-loaded, including nested paths
     * (e.g. documents.uploader). Shared by mount() and refreshTrip() — a
     * plain Model::refresh() only reloads relations by their top-level key,
     * silently dropping nested paths back to lazy-loaded, so every reload
     * after an action goes through refreshTrip() instead, never a bare
     * $this->trip->refresh().
     */
    private const TRIP_RELATIONS = [
        'creator', 'participants',
        'locations.votes', 'locations.comments.user',
        'expenses.owner', 'expenses.createdBy', 'expenses.shares',
        'settlements',
        'documents.uploader',
    ];

    /**
     * Mount the component.
     */
    public function mount(Trip $trip): void
    {
        $this->trip = $trip->load(self::TRIP_RELATIONS);
    }

    /**
     * Reload the trip with everything the view needs eager-loaded — see
     * TRIP_RELATIONS.
     */
    private function refreshTrip(): void
    {
        $this->trip = $this->trip->fresh(self::TRIP_RELATIONS);
    }

    public function updatedExpandedLocationId(): void
    {
        $this->refreshTrip();
    }

    /**
     * Delete the trip.
     */
    public function delete(): void
    {
        $this->ensureIsCreator();

        $this->trip->delete();

        $this->trackEvent('trip_deleted', ['trip_id' => $this->trip->id]);

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

        $this->trackEvent('location_accepted', ['trip_id' => $this->trip->id, 'location_id' => $location->id]);

        $this->refreshTrip();
    }

    /**
     * Delete a location.
     */
    public function deleteLocation(int $locationId): void
    {
        $this->ensureIsCreator();

        $location = $this->trip->locations()->findOrFail($locationId);
        $location->delete();

        $this->trackEvent('location_deleted', ['trip_id' => $this->trip->id, 'location_id' => $locationId]);

        $this->refreshTrip();
    }

    /**
     * Toggle vote for a location.
     */
    public function toggleVote(int $locationId): void
    {
        $this->ensureIsCreatorOrParticipant();

        $location = $this->trip->locations()->findOrFail($locationId);
        $location->toggleVote(Auth::user());

        $this->refreshTrip();
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

        $this->trackEvent('trip_participant_added', ['trip_id' => $this->trip->id]);

        $this->participantSearch = '';
        $this->refreshTrip();
    }

    /**
     * Remove a participant from the trip.
     */
    public function removeParticipant(int $userId): void
    {
        $this->ensureIsCreator();

        $this->trip->participants()->detach($userId);

        $this->trackEvent('trip_participant_removed', ['trip_id' => $this->trip->id]);

        $this->refreshTrip();
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

        $this->trackEvent('location_comment_added', ['trip_id' => $this->trip->id, 'location_id' => $locationId]);

        $this->commentTexts[$locationId] = '';
        $this->refreshTrip();
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

        $this->refreshTrip();
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
     * Toggle whether pending (not-yet-accepted) locations are shown. Once a
     * location is accepted, it's the only one shown by default — the rest
     * stay collapsed behind this toggle until asked for.
     */
    public function toggleShowAllLocations(): void
    {
        $this->showAllLocations = ! $this->showAllLocations;
    }

    /**
     * Open the add document modal.
     */
    public function openAddDocumentModal(): void
    {
        $this->ensureIsCreatorOrParticipant();

        $this->resetValidation();
        $this->newDocument = null;
        $this->newDocumentTitle = '';
        $this->newDocumentDescription = null;
        $this->showAddDocumentModal = true;
    }

    /**
     * Close the add document modal.
     */
    public function closeAddDocumentModal(): void
    {
        $this->resetValidation();
        $this->showAddDocumentModal = false;
        $this->newDocument = null;
        $this->newDocumentTitle = '';
        $this->newDocumentDescription = null;
    }

    /**
     * Upload and attach a document to the trip.
     */
    public function addDocument(): void
    {
        $this->ensureIsCreatorOrParticipant();

        // documents.max_upload_kb and the infra-level upload ceiling
        // (Dockerfile's upload_max_filesize/post_max_size,
        // docker/nginx/default.conf's client_max_body_size) are configured
        // independently — see config/documents.php. If they've drifted, a
        // file that passes validation below would still get silently
        // rejected/truncated by nginx or PHP before this action ever runs
        // again. Fail loudly here instead, scoped to this one action rather
        // than every route in the app.
        if (config('documents.max_upload_kb') > config('documents.infra_max_upload_kb')) {
            throw new \RuntimeException(
                'config(documents.max_upload_kb) exceeds config(documents.infra_max_upload_kb) — raise the '
                .'Dockerfile/nginx upload limits together with TRIP_DOCUMENTS_MAX_UPLOAD_KB.'
            );
        }

        $validated = $this->validate([
            'newDocument' => [
                'required',
                'file',
                'max:'.config('documents.max_upload_kb'),
                'mimes:'.implode(',', config('documents.allowed_mimes')),
            ],
            'newDocumentTitle' => ['required', 'string', 'max:255'],
            'newDocumentDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $disk = config('documents.disk');
        $path = $this->newDocument->store('trip-'.$this->trip->id, $disk);

        // The documents disk sets 'throw' => false (config/filesystems.php),
        // so a storage failure (permissions, full disk) surfaces as store()
        // returning false rather than an exception — without this check
        // we'd persist a document row pointing at a file that was never
        // actually written.
        if ($path === false) {
            $this->addError('newDocument', __('The file could not be uploaded. Please try again.'));

            return;
        }

        $document = $this->trip->documents()->create([
            'user_id' => Auth::id(),
            'title' => $validated['newDocumentTitle'],
            'description' => $validated['newDocumentDescription'] ?? null,
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $this->newDocument->getClientOriginalName(),
            'mime_type' => $this->newDocument->getMimeType(),
            'size' => $this->newDocument->getSize(),
        ]);

        $this->trackEvent('document_uploaded', ['trip_id' => $this->trip->id, 'document_id' => $document->id]);

        $this->closeAddDocumentModal();
        $this->refreshTrip();
    }

    /**
     * Stream a document's file to the browser as a download. Any trip
     * creator or participant can download; ensureIsCreatorOrParticipant()
     * also covers the case where the trip itself was loaded without a
     * membership check anywhere upstream.
     */
    public function downloadDocument(int $documentId)
    {
        $this->ensureIsCreatorOrParticipant();

        $document = $this->trip->documents()->findOrFail($documentId);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    /**
     * Open the edit document modal (title/description only — the file
     * itself isn't replaceable, only re-uploaded as a new document).
     */
    public function openEditDocumentModal(int $documentId): void
    {
        $document = $this->trip->documents()->findOrFail($documentId);

        $this->ensureCanManageDocument($document);

        $this->resetValidation();
        $this->editingDocumentId = $documentId;
        $this->editingDocument = [
            'title' => $document->title,
            'description' => $document->description ?? '',
        ];
        $this->showEditDocumentModal = true;
    }

    /**
     * Close the edit document modal.
     */
    public function closeEditDocumentModal(): void
    {
        $this->resetValidation();
        $this->showEditDocumentModal = false;
        $this->editingDocumentId = null;
        $this->editingDocument = ['title' => '', 'description' => ''];
    }

    /**
     * Save the edited document's title/description.
     */
    public function updateDocument(int $documentId): void
    {
        $document = $this->trip->documents()->findOrFail($documentId);

        $this->ensureCanManageDocument($document);

        $validated = $this->validate([
            'editingDocument.title' => ['required', 'string', 'max:255'],
            'editingDocument.description' => ['nullable', 'string', 'max:1000'],
        ]);

        $document->update([
            'title' => $validated['editingDocument']['title'],
            'description' => $validated['editingDocument']['description'] ?? null,
        ]);

        $this->trackEvent('document_updated', ['trip_id' => $this->trip->id, 'document_id' => $documentId]);

        $this->closeEditDocumentModal();
        $this->refreshTrip();
    }

    /**
     * Delete a document (and its underlying file — see TripDocument::booted()).
     */
    public function deleteDocument(int $documentId): void
    {
        $document = $this->trip->documents()->findOrFail($documentId);

        $this->ensureCanManageDocument($document);

        $document->delete();

        $this->trackEvent('document_deleted', ['trip_id' => $this->trip->id, 'document_id' => $documentId]);

        $this->refreshTrip();
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(int $expenseId): void
    {
        $expense = $this->trip->expenses()->findOrFail($expenseId);

        $this->ensureCanManageExpense($expense);

        $expense->update(['deleted_by' => Auth::id()]);
        $expense->delete();

        $this->trackEvent('expense_deleted', ['trip_id' => $this->trip->id, 'expense_id' => $expenseId]);

        $this->refreshTrip();
    }

    /**
     * Open the edit expense modal.
     */
    public function openEditExpenseModal(int $expenseId): void
    {
        $expense = $this->trip->expenses()->with('shares')->findOrFail($expenseId);

        $this->ensureCanManageExpense($expense);

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
            'currency' => ($expense->currency ?? $this->trip->currency ?? Currency::default())->value,
            'exchange_rate' => $expense->exchange_rate !== null ? (string) $expense->exchange_rate : '',
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
        if ($name === 'editingExpense.currency') {
            // Best-effort convenience prefill, never trusted for the actual
            // conversion — the user can always override it, and it's still
            // validated as required at submit time (see saveExpense()). Left
            // null (for the user to fill in by hand) whenever the currency
            // matches the trip's own, is invalid (wire:model.live is a
            // client-mutable property), the lookup fails, or the pair isn't
            // available.
            $selected = Currency::tryFrom($this->editingExpense['currency'] ?? '');
            $tripCurrency = $this->trip->currency ?? Currency::default();

            $this->editingExpense['exchange_rate'] = ($selected === null || $selected === $tripCurrency)
                ? null
                : app(FetchExchangeRate::class)->fetch($selected, $tripCurrency);
        }

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

        $this->ensureCanManageExpense($expense);

        $eligibleUserIds = $this->trip->members()->pluck('id')->all();

        $validated = $this->validate([
            'editingExpense.name' => ['required', 'string', 'max:255'],
            'editingExpense.description' => ['nullable', 'string', 'max:1000'],
            'editingExpense.link' => ['nullable', 'url', 'max:255'],
            'editingExpense.unit_price' => ['required', 'numeric', 'min:0'],
            'editingExpense.quantity' => ['required', 'integer', 'min:1'],
            'editingExpense.user_id' => ['required', 'exists:users,id', 'in:'.implode(',', $eligibleUserIds)],
            'editingExpense.split_type' => ['required', Rule::enum(ExpenseSplitType::class)],
            'editingExpense.currency' => ['required', Rule::enum(Currency::class)],
            'editingExpense.exchange_rate' => [
                'nullable', 'numeric', 'min:0.000001',
                'required_unless:editingExpense.currency,'.$this->trip->currency?->value,
            ],
            'editingExpense.participant_ids' => ['required', 'array', 'min:1'],
            'editingExpense.participant_ids.*' => ['integer', 'in:'.implode(',', $eligibleUserIds), 'distinct'],
        ]);

        $splitType = ExpenseSplitType::from($validated['editingExpense']['split_type']);
        $totalCents = (int) bcmul(bcmul((string) $validated['editingExpense']['unit_price'], '100', 0), (string) $validated['editingExpense']['quantity'], 0);

        // A same-currency expense never carries a rate, regardless of what a
        // stale/hidden field might have posted — null is the one meaning
        // "same as the trip's currency" everywhere else reads it.
        $exchangeRate = $validated['editingExpense']['currency'] === $this->trip->currency?->value
            ? null
            : $validated['editingExpense']['exchange_rate'];

        app(ValidateExpenseSplit::class)->validate(
            $splitType,
            $this->editingExpense['participant_ids'],
            $this->editingExpense['percentages'],
            $this->editingExpense['fixed_amounts'],
            $totalCents
        );

        DB::transaction(function () use ($expense, $validated, $splitType, $totalCents, $exchangeRate) {
            $expense->update([
                'name' => $validated['editingExpense']['name'],
                'description' => $validated['editingExpense']['description'] ?? null,
                'link' => $validated['editingExpense']['link'] ?? null,
                'unit_price' => $validated['editingExpense']['unit_price'],
                'quantity' => $validated['editingExpense']['quantity'],
                'user_id' => $validated['editingExpense']['user_id'],
                'updated_by' => Auth::id(),
                'split_type' => $splitType->value,
                'currency' => $validated['editingExpense']['currency'],
                'exchange_rate' => $exchangeRate,
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

        $this->trackEvent('expense_updated', ['trip_id' => $this->trip->id, 'expense_id' => $expenseId]);

        $this->closeEditExpenseModal();
        $this->refreshTrip();
    }

    /**
     * Get the trip's total spend across all expenses.
     */
    public function getTotalExpensesProperty(): float
    {
        return $this->trip->total_spent;
    }

    /**
     * Get the trip's most recent activity, capped at 5. See
     * App\Actions\Trips\BuildActivityFeed for what counts as an event.
     */
    public function getRecentActivityProperty(): Collection
    {
        return app(BuildActivityFeed::class)->build($this->trip)->take(5);
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
            ->flatMap(fn (Expense $expense) => collect($expense->convertedShareCentsByUserId())
                ->map(fn ($cents, $userId) => ['user_id' => $userId, 'cents' => $cents]))
            ->groupBy('user_id')
            ->map(function ($rows, $userId) use ($members) {
                $user = $members->get((int) $userId);

                return [
                    'user' => $user,
                    'amountCents' => $rows->sum('cents'),
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
        return app(BuildBalanceSummary::class)
            ->build($this->trip->balances(), $this->trip->members()->keyBy('id'))['balances'];
    }

    /**
     * Get the minimal transfer plan that settles all balances.
     */
    public function getSettlementTransfersProperty(): array
    {
        return app(BuildBalanceSummary::class)
            ->build($this->trip->balances(), $this->trip->members()->keyBy('id'))['transfers'];
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

        DB::transaction(function () use ($fromUserId, $toUserId, $amountCents): void {
            Trip::query()->whereKey($this->trip->getKey())->lockForUpdate()->first();

            $trip = $this->trip->fresh(['creator', 'participants', 'expenses.shares', 'settlements']);

            $balances = $trip->balances();
            $maxSettleable = min(
                max(0, -$balances->get($fromUserId, 0)),
                max(0, $balances->get($toUserId, 0)),
            );

            if ($amountCents > $maxSettleable) {
                abort(422);
            }

            $trip->settlements()->create([
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'amount_cents' => $amountCents,
                'recorded_by_user_id' => Auth::id(),
            ]);
        });

        $this->trackEvent('settlement_recorded', ['trip_id' => $this->trip->id, 'amount_cents' => $amountCents]);

        $this->refreshTrip();
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
     * Whether the current user is the trip creator or a participant. The
     * single source of truth behind ensureIsCreatorOrParticipant() (for
     * actions) and the Documents section in the view (for what's rendered)
     * — see resources/views/livewire/trips/show.blade.php.
     */
    public function getIsTripMemberProperty(): bool
    {
        return $this->trip->user_id === Auth::id() || $this->trip->participants->contains(Auth::id());
    }

    /**
     * Abort with a 403 unless the current user is the trip creator or a participant.
     */
    private function ensureIsCreatorOrParticipant(): void
    {
        if (! $this->isTripMember) {
            abort(403);
        }
    }

    /**
     * Whether the current user can edit/delete the given document: the trip
     * creator always can; otherwise only the uploader, and only while
     * they're still a trip member — someone removed as a participant (see
     * removeParticipant()) loses management rights over documents they
     * uploaded, the same way they lose every other trip-member action.
     * Single source of truth behind ensureCanManageDocument() (for actions)
     * and the per-document edit/delete buttons in the view.
     */
    public function canManageDocument(TripDocument $document): bool
    {
        if ($this->trip->user_id === Auth::id()) {
            return true;
        }

        return $document->user_id === Auth::id() && $this->isTripMember;
    }

    /**
     * Whether the current user can edit/delete the given expense: the trip
     * creator always can; otherwise its owner (who it was recorded for) or
     * whoever actually submitted it (created_by — set when a member adds an
     * expense on behalf of someone else). created_by is null on expenses
     * created before it was tracked, so it never grants access on its own
     * there. Single source of truth behind ensureCanManageExpense() (for
     * actions) and the per-expense edit/delete buttons in the view.
     */
    public function canManageExpense(Expense $expense): bool
    {
        return $this->trip->user_id === Auth::id()
            || $expense->user_id === Auth::id()
            || $expense->created_by === Auth::id();
    }

    /**
     * Abort with a 403 unless the current user can manage the given expense.
     */
    private function ensureCanManageExpense(Expense $expense): void
    {
        if (! $this->canManageExpense($expense)) {
            abort(403);
        }
    }

    /**
     * Abort with a 403 unless the current user can manage the given document.
     */
    private function ensureCanManageDocument(TripDocument $document): void
    {
        if (! $this->canManageDocument($document)) {
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
