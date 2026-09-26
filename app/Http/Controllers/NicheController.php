<?php

namespace App\Http\Controllers;

use App\Http\Requests\Niches\StoreNicheRequest;
use App\Http\Requests\Niches\UpdateNicheRequest;
use App\Models\Niche;
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
        $user = request()->user();

        return Inertia::render('niches/index', [
            'niches' => $user->niches()
                ->select(['id', 'name', 'status', 'why', 'findings'])
                ->orderBy('name')
                ->get(),
            'leads' => $user->leads()
                ->select(['id', 'company', 'email', 'niche_id', 'offer_id', 'status'])
                ->get(),
            'messages' => $user->messages()
                ->select(['id', 'lead_id', 'sent_at'])
                ->get(),
            'offers' => $user->offers()
                ->select(['id', 'name', 'niche_id', 'description', 'status'])
                ->get(),
        ]);
    }

    public function store(StoreNicheRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->niches()->create($request->validated());

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
