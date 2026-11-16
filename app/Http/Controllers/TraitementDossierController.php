<?php

namespace App\Http\Controllers;

use App\Models\AttributionDossier;
use App\Models\Commune;
use App\Models\User;
use App\Services\AttributionDossierService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TraitementDossierController extends Controller
{
    public function __construct(private AttributionDossierService $attributions)
    {
    }

    public function index(): Response
    {
        $this->attributions->rappelerEchus();

        return Inertia::render('traitementDissier/index', $this->attributions->donneesAdmin());
    }

    public function create(): Response
    {
        $this->attributions->rappelerEchus();

        $agent = auth()->user();

        return Inertia::render('traitementDissier/create', [
            'attributions' => $this->attributions->donneesAgent($agent),
            'traites' => $this->attributions->dossiersTraitesAgent($agent),
            'communes' => Commune::query()->get(['id', 'txt_codeCommune']),
        ]);
    }

    public function show(AttributionDossier $attribution): Response
    {
        $user = auth()->user();
        if ($user->role !== 'admin' && (int) $attribution->user_id !== (int) $user->id) {
            abort(404);
        }

        return Inertia::render('traitementDissier/show', [
            'fiche' => $this->attributions->fiche($attribution),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'origine' => ['required', 'in:dossier,arrivee,depart'],
            'txt_num_dossier' => ['required', 'string', 'max:100'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $agent = $this->agentValide((int) $data['user_id']);
        $cible = $this->attributions->trouver($data['origine'], $data['txt_num_dossier']);
        $this->attributions->attribuer($cible, $agent, $request->user());

        return back()->with('success', $this->attributions->libelle($cible).' attribué à '.$agent->name.'.');
    }

    public function reattribuer(Request $request, AttributionDossier $attribution)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $agent = $this->agentValide((int) $data['user_id']);

        try {
            $this->attributions->reattribuer($attribution, $agent, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', 'Dossier réattribué à '.$agent->name.'.');
    }

    public function valider(Request $request, AttributionDossier $attribution)
    {
        $data = $request->validate([
            'txt_nicad' => ['nullable', 'string', 'max:100'],
            'txt_num_section' => ['nullable', 'string', 'max:3'],
            'txt_num_parcelle' => ['nullable', 'string', 'max:5'],
            'txt_appartement' => ['nullable', 'string', 'max:3'],
        ]);

        $this->attributions->valider($attribution, $request->user(), $data);

        return redirect()
            ->route('traitementDossier.create')
            ->with('success', 'Dossier validé. Il est transmis au chef de bureau pour signature.');
    }

    public function signer(Request $request, AttributionDossier $attribution)
    {
        try {
            $this->attributions->signer($attribution, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', 'Dossier signé par le chef de bureau.');
    }

    public function annulerSignature(Request $request, AttributionDossier $attribution)
    {
        try {
            $this->attributions->annulerSignature($attribution, $request->user());
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        }

        return back()->with('success', 'Signature annulée. Le dossier est de nouveau en attente de signature.');
    }

    public function evaluation(): Response
    {
        return Inertia::render('parametre/evaluationUser', $this->attributions->evaluation());
    }

    private function agentValide(int $userId): User
    {
        $agent = User::query()
            ->where('is_validated', true)
            ->where('name', '!=', '')
            ->find($userId);

        if (! $agent) {
            throw ValidationException::withMessages([
                'user_id' => 'Cet utilisateur ne peut pas recevoir de dossier.',
            ]);
        }

        return $agent;
    }
}
