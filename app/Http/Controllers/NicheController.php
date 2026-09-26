<?php

namespace App\Http\Controllers;

use App\Http\Requests\Niches\StoreNicheRequest;
use App\Http\Requests\Niches\UpdateNicheRequest;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Niche;
use App\Models\Offer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NicheController extends Controller
{
    /**
     * The niches with what the table and panel derive their counts from.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Niche::class);

        return Inertia::render('niches/index', [
            'niches' => Niche::query()
                ->select(['id', 'name', 'status', 'why', 'findings'])
                ->orderBy('name')
                ->get(),
            'leads' => Lead::query()
                ->select(['id', 'company', 'email', 'niche_id', 'offer_id', 'status'])
                ->get(),
            'messages' => Message::query()
                ->select(['id', 'lead_id', 'sent_at'])
                ->get(),
            'offers' => Offer::query()
                ->select(['id', 'name', 'niche_id', 'description', 'status'])
                ->get(),
        ]);
    }

    public function store(StoreNicheRequest $request): RedirectResponse
    {
        Niche::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Niche added.']);

        return back();
    }

    public function update(UpdateNicheRequest $request, Niche $niche): RedirectResponse
    {
        $niche->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Niche saved.']);

        return back();
    }

    public function destroy(Niche $niche): RedirectResponse
    {
        Gate::authorize('delete', $niche);

        $niche->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Niche deleted.']);

        return back();
    }
}
