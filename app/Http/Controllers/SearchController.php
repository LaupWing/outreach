<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Results per group; enough to find it, not enough to drown in. */
    private const int LIMIT = 5;

    /**
     * The ⌘K search: every word of the query has to appear in one of the columns.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $words = collect(explode(' ', $request->string('q')->trim()->lower()->toString()))
            ->filter()
            ->take(5)
            ->all();

        if ($words === []) {
            return response()->json([
                'leads' => [], 'niches' => [], 'offers' => [], 'mailboxes' => [], 'messages' => [], 'runs' => [],
            ]);
        }

        $matching = fn (array $columns) => fn (Builder $query) => $query->where(function (Builder $query) use ($columns, $words) {
            foreach ($words as $word) {
                $query->where(function (Builder $query) use ($columns, $word) {
                    foreach ($columns as $column) {
                        $query->orWhere($column, 'like', '%'.addcslashes($word, '%_').'%');
                    }
                });
            }
        });

        return response()->json([
            'leads' => $user->leads()
                ->with('niche:id,name')
                ->tap($matching(['company', 'email', 'city', 'website']))
                ->limit(self::LIMIT)
                ->get(['id', 'company', 'city', 'status', 'niche_id']),
            'niches' => $user->niches()
                ->tap($matching(['name', 'status']))
                ->limit(self::LIMIT)
                ->get(['id', 'name']),
            'offers' => $user->offers()
                ->with('niche:id,name')
                ->tap($matching(['name']))
                ->limit(self::LIMIT)
                ->get(['id', 'name', 'niche_id']),
            'mailboxes' => $user->mailboxes()
                ->tap($matching(['address', 'type']))
                ->limit(self::LIMIT)
                ->get(['id', 'address']),
            'messages' => $user->messages()
                ->with('lead:id,company')
                ->tap($matching(['subject', 'reply_body']))
                ->limit(self::LIMIT)
                ->get(['id', 'subject', 'lead_id']),
            'runs' => $user->scrapeRuns()
                ->tap($matching(['query', 'place']))
                ->limit(self::LIMIT)
                ->get(['id', 'query', 'place']),
        ]);
    }
}
