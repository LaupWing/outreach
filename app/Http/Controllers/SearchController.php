<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Mailbox;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use App\Models\ScrapeRun;
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
            'leads' => Lead::query()
                ->with('niche:id,name')
                ->tap($matching(['company', 'email', 'city', 'website']))
                ->limit(self::LIMIT)
                ->get(['id', 'company', 'city', 'status', 'niche_id']),
            'niches' => Niche::query()
                ->tap($matching(['name', 'status']))
                ->limit(self::LIMIT)
                ->get(['id', 'name']),
            'offers' => Offer::query()
                ->with('niche:id,name')
                ->tap($matching(['name']))
                ->limit(self::LIMIT)
                ->get(['id', 'name', 'niche_id']),
            'mailboxes' => Mailbox::query()
                ->tap($matching(['address', 'type']))
                ->limit(self::LIMIT)
                ->get(['id', 'address']),
            'messages' => Message::query()
                ->with('lead:id,company')
                ->tap($matching(['subject', 'reply_body']))
                ->limit(self::LIMIT)
                ->get(['id', 'subject', 'lead_id']),
            'runs' => ScrapeRun::query()
                ->tap($matching(['query', 'place']))
                ->limit(self::LIMIT)
                ->get(['id', 'query', 'place']),
        ]);
    }
}
