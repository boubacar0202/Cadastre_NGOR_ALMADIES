<?php

namespace App\Http\Controllers;

use App\Models\Arrivee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstanceController extends Controller
{
    private const COLONNES = [
        'id',
        'txt_numdordre',
        'txt_typecourrier',
        'txt_numbordereau',
        'txt_numdossier',
        'txt_categorie',
        'dt_datearrivee',
        'txt_numcourier',
        'dt_datecourier',
        'txt_reference',
        'txt_caractere',
        'txt_designation',
        'txt_prenomNom',
        'txt_numLot',
        'txt_superficie',
        'txt_numTitreFoncier',
        'txt_lotissement',
        'dt_date',
        'txt_heure',
        'txt_lieu',
        'txt_nombrepiece',
        'txt_objet',
        'txt_expediteur',
        'txt_agenttraiteur',
        'txt_observation',
        'fichierPDF',
    ];

    public function index()
    {
        return redirect()->route('instance.create');
    }

    public function create(Request $request)
    {
        $maintenant = now();

        return Inertia::render('instance/create', [
            'arrivees' => function () use ($request, $maintenant) {
                $arrivees = $this->requete($request)
                    ->select(self::COLONNES)
                    ->orderByDesc('id')
                    ->paginate(15)
                    ->withQueryString();

                $seuil = $maintenant->copy()->subDays(10)->startOfDay();

                $arrivees->through(function (Arrivee $arrivee) use ($seuil) {
                    $date = $arrivee->dt_datearrivee ? Carbon::parse($arrivee->dt_datearrivee)->startOfDay() : null;
                    $arrivee->setAttribute('isOverdue', $date && $date->lessThanOrEqualTo($seuil));

                    return $arrivee;
                });

                return $arrivees;
            },
            'designations' => fn () => $this->requete(new Request())
                ->whereNotNull('txt_designation')
                ->where('txt_designation', '!=', '')
                ->distinct()
                ->orderBy('txt_designation')
                ->pluck('txt_designation')
                ->values(),
            'stats' => function () use ($request, $maintenant) {
                $seuil = $maintenant->copy()->subDays(10)->toDateString();
                $agreger = function (Builder $query) use ($seuil) {
                    return (clone $query)
                        ->selectRaw('COUNT(*) as total, SUM(dt_datearrivee <= ?) as enRetard', [$seuil])
                        ->first();
                };

                $global = $agreger($this->requete(new Request()));
                $filtre = $this->aUnFiltre($request)
                    ? $agreger($this->requete($request))
                    : $global;

                return [
                    'totalGeneral' => (int) $global->total,
                    'total' => (int) $filtre->total,
                    'enRetard' => (int) $filtre->enRetard,
                ];
            },
            'filters' => $request->only(['mois', 'annee', 'numeroTF', 'numLot', 'prenom', 'reference', 'designation']),
        ]);
    }

    private function requete(Request $request): Builder
    {
        $query = Arrivee::query()
            ->where('txt_categorie', 'Demande SERVICES')
            ->where('txt_typecourrier', '!=', 'BE à réponses Multiples')
            ->where(function (Builder $query) {
                $query->whereNull('txt_reference')
                    ->orWhere('txt_reference', '')
                    ->orWhereNotExists(function ($sousRequete) {
                        $sousRequete->selectRaw('1')
                            ->from('departs')
                            ->whereColumn('departs.txt_referencecourierarriveecd', 'arrivees.txt_reference')
                            ->where('departs.txt_referencecourierarriveecd', '!=', '');
                    });
            });

        $this->filtrerMois($query, $request->input('mois'));
        $this->filtrerAnnee($query, $request->input('annee'));
        $this->filtrerContient($query, 'txt_numTitreFoncier', $request->input('numeroTF'));
        $this->filtrerContient($query, 'txt_numLot', $request->input('numLot'));
        $this->filtrerContient($query, 'txt_prenomNom', $request->input('prenom'));
        $this->filtrerContient($query, 'txt_reference', $request->input('reference'));

        $designation = trim((string) $request->input('designation', ''));
        if ($designation !== '') {
            $query->where('txt_designation', $designation);
        }

        return $query;
    }

    private function aUnFiltre(Request $request): bool
    {
        foreach (['mois', 'annee', 'numeroTF', 'numLot', 'prenom', 'reference', 'designation'] as $cle) {
            if (trim((string) $request->input($cle, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function filtrerMois(Builder $query, mixed $mois): void
    {
        $mois = trim((string) $mois);
        if ($mois === '') {
            return;
        }

        if (! preg_match('/^\d+$/', $mois)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $valeur = (int) $mois;
        if ($valeur < 1 || $valeur > 12) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereMonth('dt_datearrivee', $valeur);
    }

    private function filtrerAnnee(Builder $query, mixed $annee): void
    {
        $annee = trim((string) $annee);
        if ($annee === '') {
            return;
        }

        if (! preg_match('/^\d{4}$/', $annee)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereYear('dt_datearrivee', (int) $annee);
    }

    private function filtrerContient(Builder $query, string $colonne, mixed $valeur): void
    {
        $valeur = trim((string) $valeur);
        if ($valeur === '') {
            return;
        }

        $query->where($colonne, 'like', '%'.addcslashes($valeur, '%_\\').'%');
    }
}
