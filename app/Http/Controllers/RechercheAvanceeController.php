<?php

namespace App\Http\Controllers;

use App\Models\Arrivee;
use App\Models\Depart;
use App\Models\Terrain;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RechercheAvanceeController extends Controller
{
    public function create(Request $request): Response
    {
        $validated = $request->validate([
            'terme' => ['nullable', 'string', 'max:120'],
            'termeOption' => ['nullable', 'in:nom,lot,titre,NICAD'],
            'annee' => ['nullable', 'digits:4'],
            'origine' => ['nullable', 'in:tous,arrivee,depart,parcelle'],
            'traitement' => ['nullable', 'in:tous,traites'],
        ]);

        $terme = trim((string) ($validated['terme'] ?? ''));
        $champ = $validated['termeOption'] ?? '';
        $annee = trim((string) ($validated['annee'] ?? ''));
        $origine = $validated['origine'] ?? 'tous';
        $traitement = $validated['traitement'] ?? 'tous';
        $mots = $this->mots($terme);
        $lancee = $mots !== [] || $annee !== '' || $traitement === 'traites';

        $arrivees = $this->vide('page_arrivee');
        $departs = $this->vide('page_depart');
        $parcelles = $this->vide('page_parcelle');

        $courriersTraites = $lancee && $traitement === 'traites' && $origine !== 'parcelle';

        if ($lancee && ($courriersTraites || in_array($origine, ['tous', 'arrivee'], true))) {
            $arrivees = $this->arrivees($mots, $annee, $traitement, $champ)
                ->paginate(15, ['*'], 'page_arrivee')
                ->withQueryString()
                ->through(fn (Arrivee $arrivee) => [
                    'id' => $arrivee->id,
                    'date' => $this->date($arrivee->dt_datearrivee),
                    'numero' => $arrivee->txt_numdordre,
                    'dossier' => $arrivee->txt_numdossier,
                    'designation' => $arrivee->txt_designation,
                    'objet' => $arrivee->txt_objet,
                    'personne' => $arrivee->txt_prenomNom,
                    'lot' => $arrivee->txt_numLot,
                    'titre' => $arrivee->txt_numTitreFoncier,
                    'nicad' => $arrivee->txt_NICAD,
                    'lotissement' => $arrivee->txt_lotissement,
                    'reference' => $arrivee->txt_reference,
                ]);
        }

        if ($lancee && ($courriersTraites || in_array($origine, ['tous', 'depart'], true))) {
            $departs = $this->departs($mots, $annee, $traitement, $champ)
                ->paginate(15, ['*'], 'page_depart')
                ->withQueryString()
                ->through(fn (Depart $depart) => [
                    'id' => $depart->id,
                    'date' => $this->date($depart->dt_datecouriercd),
                    'numero' => $depart->txt_numdordrecd,
                    'dossier' => $depart->txt_numdossiercd,
                    'designation' => $depart->txt_categoriecd,
                    'objet' => $depart->txt_objetcd,
                    'personne' => $depart->txt_prenomNomcd,
                    'lot' => $depart->txt_numLotcd,
                    'titre' => $depart->txt_numTitreFonciercd,
                    'nicad' => $depart->txt_NICADcd,
                    'lotissement' => $depart->txt_lotissementcd,
                    'reference' => $depart->txt_referencecourierarriveecd,
                ]);
        }

        if ($lancee && $traitement !== 'traites' && in_array($origine, ['tous', 'parcelle'], true)) {
            $parcelles = $this->parcelles($mots, $annee, $champ)
                ->paginate(15, ['*'], 'page_parcelle')
                ->withQueryString()
                ->through(function (Terrain $terrain) {
                    $titulaire = $terrain->titulaire;
                    $personne = trim((string) ($titulaire?->txt_prenom.' '.$titulaire?->txt_nom));
                    if ($personne === '') {
                        $personne = $titulaire?->txt_denominationPM;
                    }

                    return [
                        'id' => $terrain->id,
                        'date' => $this->date($terrain->dossier?->dt_date_creation),
                        'dossier' => $terrain->dossier?->txt_num_dossier,
                        'personne' => $personne,
                        'lot' => $terrain->txt_num_lotissement,
                        'section' => $terrain->txt_num_section,
                        'parcelle' => $terrain->txt_num_parcelle,
                        'titre' => $terrain->txt_num_titre,
                        'nicad' => $terrain->txt_nicad,
                        'lotissement' => $terrain->txt_lotissement ?: $terrain->txt_HorsLotissement,
                    ];
                });
        }

        return Inertia::render('parametre/recherche/create', [
            'lancee' => $lancee,
            'filters' => [
                'terme' => $terme,
                'termeOption' => $champ,
                'annee' => $annee,
                'origine' => $origine,
                'traitement' => $traitement,
            ],
            'totaux' => [
                'arrivees' => $arrivees->total(),
                'departs' => $departs->total(),
                'parcelles' => $parcelles->total(),
                'total' => $traitement === 'traites'
                    ? $arrivees->total()
                    : $arrivees->total() + $departs->total() + $parcelles->total(),
            ],
            'arrivees' => $arrivees,
            'departs' => $departs,
            'parcelles' => $parcelles,
        ]);
    }

    private function arrivees(array $mots, string $annee, string $traitement, string $champ): Builder
    {
        $query = Arrivee::query()->select([
            'id', 'dt_datearrivee', 'txt_numdordre', 'txt_numdossier', 'txt_designation',
            'txt_objet', 'txt_prenomNom', 'txt_numLot', 'txt_numTitreFoncier', 'txt_NICAD',
            'txt_lotissement', 'txt_reference',
        ]);

        if ($traitement === 'traites') {
            $ids = $this->jointureTraitee($mots, $annee, $champ)
                ->selectRaw('MIN(arrivees.id) as id')
                ->groupBy('arrivees.txt_reference');

            return Arrivee::query()
                ->select([
                    'arrivees.id', 'arrivees.dt_datearrivee', 'arrivees.txt_numdordre', 'arrivees.txt_numdossier',
                    'arrivees.txt_designation', 'arrivees.txt_objet', 'arrivees.txt_prenomNom', 'arrivees.txt_numLot',
                    'arrivees.txt_numTitreFoncier', 'arrivees.txt_NICAD', 'arrivees.txt_lotissement', 'arrivees.txt_reference',
                ])
                ->joinSub($ids, 'fichiers_traites', 'fichiers_traites.id', '=', 'arrivees.id')
                ->orderBy('arrivees.txt_reference');
        }

        $this->filtrerMots($query, $mots, $this->colonnesArrivee($champ));

        if ($annee !== '') {
            $query->whereYear('dt_datearrivee', (int) $annee);
        }

        return $query->orderByDesc('dt_datearrivee');
    }

    private function departs(array $mots, string $annee, string $traitement, string $champ): Builder
    {
        $query = Depart::query()->select([
            'id', 'dt_datecouriercd', 'txt_numdordrecd', 'txt_numdossiercd', 'txt_categoriecd',
            'txt_objetcd', 'txt_prenomNomcd', 'txt_numLotcd', 'txt_numTitreFonciercd', 'txt_NICADcd',
            'txt_lotissementcd', 'txt_referencecourierarriveecd',
        ]);

        if ($traitement === 'traites') {
            $ids = $this->jointureTraitee($mots, $annee, $champ)
                ->selectRaw('MIN(departs.id) as id')
                ->groupBy('departs.txt_referencecourierarriveecd');

            return Depart::query()
                ->select([
                    'departs.id', 'departs.dt_datecouriercd', 'departs.txt_numdordrecd', 'departs.txt_numdossiercd',
                    'departs.txt_categoriecd', 'departs.txt_objetcd', 'departs.txt_prenomNomcd', 'departs.txt_numLotcd',
                    'departs.txt_numTitreFonciercd', 'departs.txt_NICADcd', 'departs.txt_lotissementcd',
                    'departs.txt_referencecourierarriveecd',
                ])
                ->joinSub($ids, 'fichiers_traites', 'fichiers_traites.id', '=', 'departs.id')
                ->orderBy('departs.txt_referencecourierarriveecd');
        }

        $this->filtrerMots($query, $mots, $this->colonnesDepart($champ));

        if ($annee !== '') {
            $query->whereYear('dt_datecouriercd', (int) $annee);
        }

        return $query->orderByDesc('dt_datecouriercd');
    }

    private function parcelles(array $mots, string $annee, string $champ): Builder
    {
        $query = Terrain::query()
            ->with([
                'titulaire:id,txt_prenom,txt_nom,txt_denominationPM',
                'dossier:id,txt_num_dossier,dt_date_creation',
            ])
            ->select([
                'id', 'titulaire_id', 'txt_num_dossier', 'txt_num_lotissement', 'txt_num_section',
                'txt_num_parcelle', 'txt_num_titre', 'txt_nicad', 'txt_lotissement', 'txt_HorsLotissement',
            ]);

        foreach ($mots as $mot) {
            $like = '%'.$this->echapper($mot).'%';
            $query->where(function (Builder $groupe) use ($like, $champ) {
                if ($champ === '' || $champ === 'lot') {
                    $groupe->orWhere('txt_num_lotissement', 'like', $like);
                }
                if ($champ === '') {
                    $groupe->orWhere('txt_num_section', 'like', $like);
                    $groupe->orWhere('txt_num_parcelle', 'like', $like);
                    $groupe->orWhere('txt_lotissement', 'like', $like);
                }
                if ($champ === '' || $champ === 'titre') {
                    $groupe->orWhere('txt_num_titre', 'like', $like);
                }
                if ($champ === '' || $champ === 'NICAD') {
                    $groupe->orWhere('txt_nicad', 'like', $like);
                }
                if ($champ === '' || $champ === 'nom') {
                    $groupe->orWhereHas('titulaire', function (Builder $titulaire) use ($like) {
                        $titulaire->where('txt_prenom', 'like', $like)
                            ->orWhere('txt_nom', 'like', $like)
                            ->orWhere('txt_denominationPM', 'like', $like)
                            ->orWhereRaw("CONCAT(COALESCE(txt_prenom, ''), ' ', COALESCE(txt_nom, '')) like ?", [$like]);
                    });
                }
                if ($champ === '') {
                    $groupe->orWhereHas('dossier', function (Builder $dossier) use ($like) {
                        $dossier->where('txt_num_dossier', 'like', $like);
                    });
                }
            });
        }

        if ($annee !== '') {
            $query->whereHas('dossier', fn (Builder $dossier) => $dossier->whereYear('dt_date_creation', (int) $annee));
        }

        return $query->orderByDesc('id');
    }

    private function jointureTraitee(array $mots, string $annee, string $champ): QueryBuilder
    {
        $query = DB::table('arrivees')
            ->join('departs', 'departs.txt_referencecourierarriveecd', '=', 'arrivees.txt_reference')
            ->whereNull('arrivees.deleted_at')
            ->where('arrivees.txt_reference', '!=', '')
            ->where('departs.txt_referencecourierarriveecd', '!=', '');

        foreach ($mots as $mot) {
            $like = '%'.$this->echapper($mot).'%';
            $colonnes = array_merge(
                array_map(fn (string $colonne) => 'arrivees.'.$colonne, $this->colonnesArrivee($champ)),
                array_map(fn (string $colonne) => 'departs.'.$colonne, $this->colonnesDepart($champ)),
            );
            $query->where(function (QueryBuilder $groupe) use ($like, $colonnes) {
                foreach ($colonnes as $colonne) {
                    $groupe->orWhere($colonne, 'like', $like);
                }
            });
        }

        if ($annee !== '') {
            $query->where(function (QueryBuilder $groupe) use ($annee) {
                $groupe->whereYear('arrivees.dt_datearrivee', (int) $annee)
                    ->orWhereYear('departs.dt_datecouriercd', (int) $annee);
            });
        }

        return $query;
    }

    private function colonnesArrivee(string $champ): array
    {
        return match ($champ) {
            'nom' => ['txt_prenomNom'],
            'lot' => ['txt_numLot'],
            'titre' => ['txt_numTitreFoncier'],
            'NICAD' => ['txt_NICAD'],
            default => [
                'txt_designation', 'txt_objet', 'txt_categorie', 'txt_prenomNom',
                'txt_numLot', 'txt_numTitreFoncier', 'txt_NICAD', 'txt_numdossier', 'txt_lotissement',
            ],
        };
    }

    private function colonnesDepart(string $champ): array
    {
        return match ($champ) {
            'nom' => ['txt_prenomNomcd'],
            'lot' => ['txt_numLotcd'],
            'titre' => ['txt_numTitreFonciercd'],
            'NICAD' => ['txt_NICADcd'],
            default => [
                'txt_objetcd', 'txt_categoriecd', 'txt_prenomNomcd', 'txt_numLotcd',
                'txt_numTitreFonciercd', 'txt_NICADcd', 'txt_numdossiercd', 'txt_lotissementcd',
            ],
        };
    }

    private function filtrerMots(Builder $query, array $mots, array $colonnes): void
    {
        foreach ($mots as $mot) {
            $like = '%'.$this->echapper($mot).'%';
            $query->where(function (Builder $groupe) use ($colonnes, $like) {
                foreach ($colonnes as $colonne) {
                    $groupe->orWhere($colonne, 'like', $like);
                }
            });
        }
    }

    private function mots(string $terme): array
    {
        if ($terme === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/u', $terme) ?: [],
            fn (string $mot) => mb_strlen($mot) >= 2
        ));
    }

    private function date(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return Carbon::parse($valeur)->format('d/m/Y');
    }

    private function echapper(string $valeur): string
    {
        return addcslashes($valeur, '%_\\');
    }

    private function vide(string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 15, 1, [
            'pageName' => $pageName,
            'path' => request()->url(),
        ]);
    }
}
