<?php

namespace App\Http\Controllers;

use App\Models\Depart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InstancedepartController extends Controller
{
    private const COLONNES = [
        'id',
        'txt_numdordrecd',
        'txt_avisReponsecd',
        'txt_numdossiercd',
        'txt_typecourriercd',
        'dt_datecouriercd',
        'txt_categoriecd',
        'txt_referencecourierarriveecd',
        'txt_nombrepiececd',
        'txt_referencecd',
        'txt_objetcd',
        'txt_destinatairecd',
        'txt_prenomNomcd',
        'txt_numLotcd',
        'txt_superficiecd',
        'txt_numTitreFonciercd',
        'txt_numTitreMerecd',
        'txt_NICADcd',
        'txt_lotissementcd',
        'dt_dateenvoicd',
        'txt_referencereceptioncd',
        'txt_observationcd',
        'txt_dureetraitementcd',
        'txt_caracterecd',
        'fichierPDFcd',
    ];

    public function index()
    {
        return redirect()->route('instancedepart.create');
    }

    public function create(Request $request)
    {
        return Inertia::render('instancedepart/create', [
            'departs' => fn () => $this->requete($request)
                ->select(self::COLONNES)
                ->orderByRaw('CAST(SUBSTRING_INDEX(txt_numdordrecd, "/", 1) AS UNSIGNED)')
                ->paginate(15)
                ->withQueryString(),
            'objetcourrier' => fn () => Depart::query()
                ->whereNotNull('txt_objetcd')
                ->where('txt_objetcd', '!=', '')
                ->distinct()
                ->orderBy('txt_objetcd')
                ->pluck('txt_objetcd')
                ->values(),
            'stats' => fn () => [
                'total' => Depart::query()
                    ->whereNotNull('txt_numdordrecd')
                    ->where('txt_numdordrecd', '!=', '')
                    ->count(),
            ],
            'filters' => $request->only(['numeroTF', 'numLot', 'prenom', 'annee', 'numero', 'reference', 'objetcourrier']),
        ]);
    }

    public function destroyDepart($id)
    {
        $depart = Depart::findOrFail($id);
        $depart->delete();

        return redirect()->back()->with('success', 'Courrier Départ N° '.$depart->txt_numdordrecd.' supprimé avec succès.');
    }

    private function requete(Request $request): Builder
    {
        $query = Depart::query();

        $annee = trim((string) $request->input('annee', ''));
        if ($annee !== '') {
            if (! preg_match('/^\d{4}$/', $annee)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereYear('dt_datecouriercd', (int) $annee);
            }
        }

        foreach ([
            'numeroTF' => 'txt_numTitreFonciercd',
            'numLot' => 'txt_numLotcd',
            'prenom' => 'txt_prenomNomcd',
            'numero' => 'txt_numdordrecd',
            'reference' => 'txt_referencecourierarriveecd',
        ] as $parametre => $colonne) {
            $valeur = trim((string) $request->input($parametre, ''));
            if ($valeur !== '') {
                $query->where($colonne, 'like', '%'.addcslashes($valeur, '%_\\').'%');
            }
        }

        $objetcourrier = trim((string) $request->input('objetcourrier', ''));
        if ($objetcourrier !== '') {
            $query->where('txt_objetcd', $objetcourrier);
        }

        return $query;
    }
}
