<?php

namespace App\Actions\Trips;

use App\Models\Trip;
use Illuminate\Support\Collection;

class BuildActivityFeed
{
    /**
     * Icon per event type, shared by every view that renders this feed.
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
     * `expenses.shares`, and `settlements` to be eager-loaded on the trip.
     *
     * @return Collection<int, array{type: string, at: \Illuminate\Support\Carbon, user: ?\App\Models\User, text: string}>
     */
    public function build(Trip $trip): Collection
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
            $amount = number_format($expense->total, 2);

            $events->push([
                'type' => 'expense',
                'at' => $expense->created_at,
                'user' => $expense->owner,
                'text' => $expense->owner
                    ? __(':user added expense :expense ($:amount)', ['user' => $expense->owner->fullName(), 'expense' => $expense->name, 'amount' => $amount])
                    : __('Expense added: :expense ($:amount)', ['expense' => $expense->name, 'amount' => $amount]),
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
                        ? __(':user edited expense :expense ($:amount)', ['user' => $editor->fullName(), 'expense' => $expense->name, 'amount' => $amount])
                        : __('Expense edited: :expense ($:amount)', ['expense' => $expense->name, 'amount' => $amount]),
                ]);
            }
        }

        foreach ($trip->expenses()->onlyTrashed()->get() as $expense) {
            $amount = number_format($expense->total, 2);
            $deleter = $members->get($expense->deleted_by);

            $events->push([
                'type' => 'expense_deleted',
                'at' => $expense->deleted_at,
                'user' => $deleter,
                'text' => $deleter
                    ? __(':user deleted expense :expense ($:amount)', ['user' => $deleter->fullName(), 'expense' => $expense->name, 'amount' => $amount])
                    : __('Expense deleted: :expense ($:amount)', ['expense' => $expense->name, 'amount' => $amount]),
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
                    'text' => __(':from settled $:amount with :to', [
                        'from' => $from->fullName(),
                        'amount' => number_format($settlement->amount_cents / 100, 2),
                        'to' => $to->fullName(),
                    ]),
                ]);
            }
        }

        return $events->sortByDesc('at')->values();
    }
}
