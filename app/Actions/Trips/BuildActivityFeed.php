<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use App\Support\Money;
use App\Enums\Currency;
use Illuminate\Support\Collection;

class BuildActivityFeed
{
    /**
     * Icon per event type, shared by every view that renders this feed.
     * Look up via iconFor(), not directly — a raw array access silently
     * returns null for an unmapped type instead of failing loudly.
     */
    public const ICONS = [
        'comment' => 'chat-bubble-left',
        'vote' => 'heart',
        'accepted' => 'check-circle',
        'expense' => 'currency-dollar',
        'expense_edited' => 'pencil',
        'expense_deleted' => 'trash',
        'settlement' => 'check-badge',
    ];

    /**
     * Get the icon for an event type, or fail loudly if a type is ever added
     * to build() without a matching entry here — a missing icon should be
     * caught before merge, not ship as a silently blank one.
     */
    public static function iconFor(string $type): string
    {
        return self::ICONS[$type] ?? throw new \ValueError("No icon mapped for activity event type [{$type}].");
    }

    /**
     * Build a trip's full activity feed, merged from every already-timestamped
     * source: location comments, votes (pivot timestamps), location
     * acceptance, expenses (added/edited/deleted), and settlements. Newest
     * first — callers cap it to however many events they want to show.
     *
     * Location proposals have no attributable "who added this" column in the
     * data model (locations.user_id doesn't exist), so unlike the other event
     * types, "accepted" events render without an actor — an honest reflection
     * of what's actually recorded, not a guess. Expense edits/deletes are the
     * same way: they render actor-less unless updated_by/deleted_by was
     * actually recorded (it always is going forward, via saveExpense/deleteExpense).
     *
     * Requires `locations.votes`, `locations.comments.user`, `expenses.owner`,
     * `expenses.createdBy`, and `settlements` to be eager-loaded on the trip. Deleted expenses are
     * excluded from the eager-loaded `expenses` relation by default (soft
     * deletes), so they're queried separately — pass $trashedExpenses when
     * building feeds for several trips at once (e.g. the global Activity
     * page) to fetch them all in one query up front instead of one query per
     * trip; omitted, it falls back to a live per-trip query, fine for the
     * single-trip case.
     *
     * @param  ?Collection<int, \App\Models\Expense>  $trashedExpenses
     * @return Collection<int, array{type: string, at: \Illuminate\Support\Carbon, user: ?\App\Models\User, text: string}>
     */
    public function build(Trip $trip, ?Collection $trashedExpenses = null): Collection
    {
        $events = collect();
        $members = $trip->members()->keyBy('id');

        foreach ($trip->locations as $location) {
            foreach ($location->comments as $comment) {
                $events->push([
                    'type' => 'comment',
                    'at' => $comment->created_at,
                    'user' => $comment->user,
                    'text' => __(':user commented on :location', ['user' => $comment->user->fullName(), 'location' => $location->name]),
                ]);
            }

            foreach ($location->votes as $voter) {
                $events->push([
                    'type' => 'vote',
                    'at' => $voter->pivot->created_at,
                    'user' => $voter,
                    'text' => __(':user voted for :location', ['user' => $voter->fullName(), 'location' => $location->name]),
                ]);
            }

            if ($location->accepted_at) {
                $events->push([
                    'type' => 'accepted',
                    'at' => $location->accepted_at,
                    'user' => null,
                    'text' => __(':location was accepted', ['location' => $location->name]),
                ]);
            }
        }

        foreach ($trip->expenses as $expense) {
            $amount = Money::formatDecimal((string) $expense->total, $expense->currency ?? $trip->currency ?? Currency::default());

            // created_by is only set going forward (see expenses.create); a
            // null value means either it predates tracking or the submitter
            // was also the owner, so both fall back to the owner-only text.
            // Either side can also be null on its own if that user's account
            // was since deleted (user_id/created_by both nullOnDelete).
            $submitter = $expense->createdBy;
            $owner = $expense->owner;
            $events->push([
                'type' => 'expense',
                'at' => $expense->created_at,
                'user' => $submitter ?? $owner,
                'text' => match (true) {
                    $submitter && $owner && $submitter->isNot($owner) => __(':user added expense :expense for :owner (:amount)', ['user' => $submitter->fullName(), 'owner' => $owner->fullName(), 'expense' => $expense->name, 'amount' => $amount]),
                    (bool) $submitter => __(':user added expense :expense (:amount)', ['user' => $submitter->fullName(), 'expense' => $expense->name, 'amount' => $amount]),
                    (bool) $owner => __(':user added expense :expense (:amount)', ['user' => $owner->fullName(), 'expense' => $expense->name, 'amount' => $amount]),
                    default => __('Expense added: :expense (:amount)', ['expense' => $expense->name, 'amount' => $amount]),
                },
            ]);

            // updated_by is only ever set by saveExpense(), never on creation,
            // so its presence alone tells us the expense has been edited since.
            if ($expense->updated_by) {
                $editor = $members->get($expense->updated_by);
                $events->push([
                    'type' => 'expense_edited',
                    'at' => $expense->updated_at,
                    'user' => $editor,
                    'text' => $editor
                        ? __(':user edited expense :expense (:amount)', ['user' => $editor->fullName(), 'expense' => $expense->name, 'amount' => $amount])
                        : __('Expense edited: :expense (:amount)', ['expense' => $expense->name, 'amount' => $amount]),
                ]);
            }
        }

        foreach ($trashedExpenses ?? $trip->expenses()->onlyTrashed()->get() as $expense) {
            $amount = Money::formatDecimal((string) $expense->total, $expense->currency ?? $trip->currency ?? Currency::default());
            $deleter = $members->get($expense->deleted_by);

            $events->push([
                'type' => 'expense_deleted',
                'at' => $expense->deleted_at,
                'user' => $deleter,
                'text' => $deleter
                    ? __(':user deleted expense :expense (:amount)', ['user' => $deleter->fullName(), 'expense' => $expense->name, 'amount' => $amount])
                    : __('Expense deleted: :expense (:amount)', ['expense' => $expense->name, 'amount' => $amount]),
            ]);
        }

        foreach ($trip->settlements as $settlement) {
            $from = $members->get($settlement->from_user_id);
            $to = $members->get($settlement->to_user_id);

            if ($from && $to) {
                $events->push([
                    'type' => 'settlement',
                    'at' => $settlement->created_at,
                    'user' => $from,
                    'text' => __(':from settled :amount with :to', [
                        'from' => $from->fullName(),
                        'amount' => Money::format($settlement->amount_cents, $trip->currency ?? Currency::default()),
                        'to' => $to->fullName(),
                    ]),
                ]);
            }
        }

        return $events->sortByDesc('at')->values();
    }
}
