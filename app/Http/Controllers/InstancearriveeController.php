<?php

namespace App\Http\Controllers;

use App\Models\Arrivee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstancearriveeController extends Controller
{
    private const COLONNES = [
        'id',
        'txt_numdordre',
        'txt_numbordereau',
        'txt_numdossier',
        'txt_caractere',
        'txt_typecourrier',
        'dt_datearrivee',
        'txt_numcourier',
        'dt_datecourier',
        'txt_reference',
        'txt_categorie',
        'txt_designation',
        'txt_prenomNom',
        'txt_numLot',
        'txt_superficie',
        'txt_numTitreMere',
        'txt_numTitreFoncier',
        'txt_NICAD',
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
        return redirect()->route('instancearrivee.create');
    }

    public function create(Request $request)
    {
        $maintenant = now();

        return Inertia::render('instancearrivee/create', [
            'arrivees' => function () use ($request, $maintenant) {
                $arrivees = $this->requeteFiltree($request)
                    ->orderByRaw("CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(txt_numdordre, '/', 1), '_', 1) AS UNSIGNED)")
                    ->orderByRaw("CAST(IF(LOCATE('_', SUBSTRING_INDEX(txt_numdordre, '/', 1)) > 0, SUBSTRING_INDEX(SUBSTRING_INDEX(txt_numdordre, '/', 1), '_', -1), 0) AS UNSIGNED)")
                    ->paginate(15)
                    ->withQueryString();

                $limite72h = $maintenant->copy()->addHours(72);

                $arrivees->through(function (Arrivee $arrivee) use ($maintenant, $limite72h) {
                    $date = $arrivee->dt_date ? Carbon::parse($arrivee->dt_date) : null;
                    $arrivee->setAttribute(
                        'isLateConvocation',
                        $arrivee->txt_categorie === 'Convocation - Invitation'
                            && $date
                            && $date->greaterThan($maintenant)
                            && $date->lessThanOrEqualTo($limite72h)
                    );

                    return $arrivee;
                });

                return $arrivees;
            },
            'designations' => fn () => Arrivee::query()
                ->whereNotNull('txt_designation')
                ->where('txt_designation', '!=', '')
                ->distinct()
                ->orderBy('txt_designation')
                ->pluck('txt_designation'),
            'stats' => function () use ($maintenant) {
                $limite72h = $maintenant->copy()->addHours(72);
                $limite48h = $maintenant->copy()->addHours(48);

                $proches = Arrivee::query()
                    ->select(['txt_numdordre', 'dt_date', 'txt_heure'])
                    ->where('txt_categorie', 'Convocation - Invitation')
                    ->where('dt_date', '>', $maintenant)
                    ->where('dt_date', '<=', $limite48h)
                    ->orderBy('dt_date')
                    ->get();

                $dateLaPlusProche = $proches->min('dt_date');

                return [
                    'total' => Arrivee::query()->count(),
                    'totalCourrier' => Arrivee::query()
                        ->whereNotNull('txt_numdordre')
                        ->where('txt_numdordre', '!=', '')
                        ->count(),
                    'convocations72h' => Arrivee::query()
                        ->where('txt_categorie', 'Convocation - Invitation')
                        ->where('dt_date', '>', $maintenant)
                        ->where('dt_date', '<=', $limite72h)
                        ->count(),
                    'convocationsProches' => $dateLaPlusProche
                        ? $proches->where('dt_date', $dateLaPlusProche)->values()
                        : [],
                ];
            },
            'filters' => $request->only(['txt_numdossier', 'mois', 'annee', 'numeroTF', 'numLot', 'numNICAD', 'prenom', 'reference', 'designation', 'numero']),
        ]);
    }

    public function destroy($id)
    {
        $arrivee = Arrivee::findOrFail($id);
        $arrivee->delete();

        return redirect()->back()->with('success', 'Courrier Arrivée N° ' . $arrivee->txt_numdordre . ' supprimé avec succès.');
    }

    private function requeteFiltree(Request $request): Builder
    {
        $query = Arrivee::query()->select(self::COLONNES);

        $this->filtrerContient($query, 'txt_numdossier', $request->input('txt_numdossier'));
        $this->filtrerMois($query, $request->input('mois'));
        $this->filtrerAnnee($query, $request->input('annee'));
        $this->filtrerContient($query, 'txt_numTitreFoncier', $request->input('numeroTF'));
        $this->filtrerContient($query, 'txt_NICAD', $request->input('numNICAD'));
        $this->filtrerContient($query, 'txt_numLot', $request->input('numLot'));
        $this->filtrerContient($query, 'txt_prenomNom', $request->input('prenom'));
        $this->filtrerContient($query, 'txt_reference', $request->input('reference'));
        $this->filtrerContient($query, 'txt_numdordre', $request->input('numero'));

        $designation = trim((string) $request->input('designation', ''));
        if ($designation !== '') {
            $query->where('txt_designation', $designation);
        }

        return $query;
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

        $query->where($colonne, 'like', '%' . addcslashes($valeur, '%_\\') . '%');
    }
}
