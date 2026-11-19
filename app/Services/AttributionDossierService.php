<?php

namespace App\Services;

use App\Models\Arrivee;
use App\Models\AttributionDossier;
use App\Models\Depart;
use App\Models\Dossier;
use App\Models\Message;
use App\Models\Terrain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttributionDossierService
{
    public function trouver(string $origine, string $numero): Model
    {
        $numero = trim($numero);

        return match ($origine) {
            'arrivee' => $this->trouverDans(
                Arrivee::query(),
                ['txt_numdordre', 'txt_numdossier'],
                $numero,
                'Aucun courrier d\'arrivée ne porte ce numéro.',
                'Plusieurs courriers d\'arrivée portent ce numéro. Saisissez le N° d\'ordre.'
            ),
            'depart' => $this->trouverDans(
                Depart::query(),
                ['txt_numdordrecd', 'txt_numdossiercd'],
                $numero,
                'Aucun courrier de départ ne porte ce numéro.',
                'Plusieurs courriers de départ portent ce numéro. Saisissez le N° d\'ordre.'
            ),
            default => $this->trouverDans(
                Dossier::query(),
                ['txt_num_dossier'],
                $numero,
                'Aucun dossier du secrétariat ne porte ce numéro. Pour un courrier, choisissez Arrivée ou Départ.',
                'Plusieurs dossiers portent ce numéro.'
            ),
        };
    }

    public function libelle(Model $cible): string
    {
        return match (true) {
            $cible instanceof Arrivee => 'Courrier arrivée '.$cible->txt_numdordre,
            $cible instanceof Depart => 'Courrier départ '.$cible->txt_numdordrecd,
            default => 'Dossier '.($cible->txt_num_dossier ?? ''),
        };
    }

    public function attribuer(Model $cible, User $agent, User $admin): AttributionDossier
    {
        return DB::transaction(function () use ($cible, $agent, $admin) {
            $origine = $this->origineDe($cible);
            $cible = $cible->newQuery()->lockForUpdate()->findOrFail($cible->getKey());
            $this->empecherDoublon($origine, (int) $cible->getKey());

            $attribution = new AttributionDossier([
                'origine' => $origine,
                'dossier_id' => $origine === 'dossier' ? $cible->getKey() : null,
                'arrivee_id' => $origine === 'arrivee' ? $cible->getKey() : null,
                'depart_id' => $origine === 'depart' ? $cible->getKey() : null,
                'user_id' => $agent->id,
                'attribue_par' => $admin->id,
                'statut' => AttributionDossier::STATUT_ATTRIBUE,
                'date_attribution' => now(),
                'date_echeance' => now()->addDays(AttributionDossier::DELAI_JOURS),
            ]);
            $attribution->noter($this->libelle($cible).' attribué à '.$agent->name.' par '.$admin->name.'.');
            $attribution->save();

            $this->memoriserAgent($cible, $agent->name);

            return $attribution;
        });
    }

    public function imputerArrivee(Arrivee $arrivee, string $nom, User $auteur): void
    {
        $agent = $this->agentParNom($nom);
        if (! $agent) {
            return;
        }

        $ouverte = AttributionDossier::query()
            ->where('origine', 'arrivee')
            ->where('arrivee_id', $arrivee->id)
            ->where('statut', AttributionDossier::STATUT_ATTRIBUE)
            ->first();

        if ($ouverte) {
            if ((int) $ouverte->user_id !== (int) $agent->id) {
                $this->reattribuer($ouverte, $agent, $auteur);
            }

            return;
        }

        $fermee = AttributionDossier::query()
            ->where('origine', 'arrivee')
            ->where('arrivee_id', $arrivee->id)
            ->whereIn('statut', [
                AttributionDossier::STATUT_TRAITE,
                AttributionDossier::STATUT_SIGNE,
            ])
            ->exists();

        if ($fermee) {
            return;
        }

        $this->attribuer($arrivee, $agent, $auteur);
    }

    public function reattribuer(AttributionDossier $attribution, User $agent, User $admin): AttributionDossier
    {
        return DB::transaction(function () use ($attribution, $agent, $admin) {
            $attribution = AttributionDossier::query()->lockForUpdate()->findOrFail($attribution->id);

            if ($attribution->statut !== AttributionDossier::STATUT_ATTRIBUE) {
                throw ValidationException::withMessages([
                    'user_id' => 'Seuls les dossiers encore en cours peuvent être réattribués.',
                ]);
            }

            $precedent = $attribution->agent?->name ?? 'un agent';
            $attribution->user_id = $agent->id;
            $attribution->attribue_par = $admin->id;
            $attribution->date_attribution = now();
            $attribution->date_echeance = now()->addDays(AttributionDossier::DELAI_JOURS);
            $attribution->rappel_envoye_at = null;
            $attribution->tentatives_nicad = 0;
            $attribution->verrouille_jusqua = null;
            $attribution->noter('Réattribué de '.$precedent.' à '.$agent->name.' par '.$admin->name.'. Le délai de 10 jours repart.');
            $attribution->save();

            $this->memoriserAgentAttribue($attribution, $agent->name);

            return $attribution;
        });
    }

    public function valider(AttributionDossier $attribution, User $agent, array $saisie): void
    {
        DB::transaction(function () use ($attribution, $agent, $saisie) {
            $attribution = AttributionDossier::query()->lockForUpdate()->findOrFail($attribution->id);

            if ((int) $attribution->user_id !== (int) $agent->id) {
                abort(404);
            }

            if ($attribution->estVerrouille()) {
                throw ValidationException::withMessages([
                    'txt_nicad' => 'Trop de tentatives. Réessayez après '.$attribution->verrouille_jusqua->format('H:i').'.',
                ]);
            }

            if ($attribution->statut !== AttributionDossier::STATUT_ATTRIBUE) {
                throw ValidationException::withMessages([
                    'txt_nicad' => 'Ce dossier n’est plus à valider.',
                ]);
            }

            if (($attribution->origine ?: 'dossier') === 'dossier') {
                $this->enregistrerNicad($attribution, $saisie);
            } elseif (! $this->preuveCorrespond($attribution, (string) ($saisie['txt_nicad'] ?? ''))) {
                $attribution->tentatives_nicad++;
                if ($attribution->tentatives_nicad >= AttributionDossier::TENTATIVES_MAX) {
                    $attribution->tentatives_nicad = 0;
                    $attribution->verrouille_jusqua = now()->addMinutes(30);
                    $attribution->noter('Validation bloquée 30 minutes après plusieurs NICAD incorrects.');
                }
                $attribution->save();

                throw ValidationException::withMessages([
                    'txt_nicad' => $this->messagePreuve($attribution->origine),
                ]);
            }

            $attribution->statut = AttributionDossier::STATUT_TRAITE;
            $attribution->date_traitement = now();
            $attribution->tentatives_nicad = 0;
            $attribution->verrouille_jusqua = null;
            $attribution->noter('Validé par '.$agent->name.' après contrôle de la pièce du dossier.');
            $attribution->save();
        });
    }

    public function signer(AttributionDossier $attribution, User $chef): void
    {
        DB::transaction(function () use ($attribution, $chef) {
            $attribution = AttributionDossier::query()->lockForUpdate()->findOrFail($attribution->id);

            if ($attribution->statut !== AttributionDossier::STATUT_TRAITE) {
                throw ValidationException::withMessages([
                    'signature' => 'Seuls les dossiers validés par l’agent peuvent être signés.',
                ]);
            }

            $attribution->statut = AttributionDossier::STATUT_SIGNE;
            $attribution->signe_par = $chef->id;
            $attribution->date_signature = now();
            $attribution->noter('Signé par le chef de bureau '.$chef->name.'.');
            $attribution->save();
        });
    }

    public function annulerSignature(AttributionDossier $attribution, User $chef): void
    {
        DB::transaction(function () use ($attribution, $chef) {
            $attribution = AttributionDossier::query()->lockForUpdate()->findOrFail($attribution->id);

            if ($attribution->statut !== AttributionDossier::STATUT_SIGNE) {
                throw ValidationException::withMessages([
                    'signature' => 'Seule une signature enregistrée peut être annulée.',
                ]);
            }

            $attribution->statut = AttributionDossier::STATUT_TRAITE;
            $attribution->signe_par = null;
            $attribution->date_signature = null;
            $attribution->noter('Signature annulée par '.$chef->name.'. Le dossier retourne en attente de signature.');
            $attribution->save();
        });
    }

    public function rappelerEchus(): int
    {
        $ids = AttributionDossier::query()
            ->where('statut', AttributionDossier::STATUT_ATTRIBUE)
            ->where('date_echeance', '<', now())
            ->whereNull('rappel_envoye_at')
            ->pluck('id');

        $envoyes = 0;

        foreach ($ids as $id) {
            $envoye = DB::transaction(function () use ($id) {
                $attribution = AttributionDossier::query()
                    ->with([
                        'dossier:id,txt_num_dossier',
                        'arrivee:id,txt_numdordre',
                        'depart:id,txt_numdordrecd',
                        'agent:id,name',
                    ])
                    ->lockForUpdate()
                    ->find($id);

                if (
                    ! $attribution
                    || $attribution->statut !== AttributionDossier::STATUT_ATTRIBUE
                    || $attribution->rappel_envoye_at !== null
                    || $attribution->date_echeance === null
                    || $attribution->date_echeance->gte(now())
                ) {
                    return false;
                }

                $this->envoyerMessageRappel($attribution);
                $attribution->rappel_envoye_at = now();
                $attribution->noter('Rappel envoyé : le délai de 10 jours est dépassé.');
                $attribution->save();

                return true;
            });

            if ($envoye) {
                $envoyes++;
            }
        }

        return $envoyes;
    }

    public function donneesAgent(User $agent): array
    {
        $attributions = AttributionDossier::query()
            ->with([
                'dossier:id,txt_num_dossier,txt_num_dordre,slt_service_rendu',
                'arrivee:id,txt_numdordre,txt_numdossier,txt_typecourrier,txt_numcourier',
                'depart:id,txt_numdordrecd,txt_numdossiercd,txt_typecourriercd,txt_referencecd',
            ])
            ->where('user_id', $agent->id)
            ->where('statut', AttributionDossier::STATUT_ATTRIBUE)
            ->orderByDesc('date_attribution')
            ->get();

        $preuves = $this->preuvesPresentes($attributions);
        $communes = $this->communesDossiers($attributions);

        return $attributions
            ->map(function (AttributionDossier $attribution) use ($preuves, $communes) {
                $estDossier = ($attribution->origine ?: 'dossier') === 'dossier';

                return $this->ligneAgent(
                    $attribution,
                    $estDossier ? true : ($preuves[$attribution->id] ?? false),
                    $estDossier ? ($communes[(int) $attribution->dossier_id] ?? null) : null
                );
            })
            ->values()
            ->all();
    }

    public function dossiersTraitesAgent(User $agent): array
    {
        return AttributionDossier::query()
            ->with([
                'dossier:id,txt_num_dossier,txt_num_dordre,slt_service_rendu',
                'arrivee:id,txt_numdordre,txt_numdossier,txt_typecourrier',
                'depart:id,txt_numdordrecd,txt_numdossiercd,txt_typecourriercd',
            ])
            ->where('user_id', $agent->id)
            ->whereIn('statut', [
                AttributionDossier::STATUT_TRAITE,
                AttributionDossier::STATUT_SIGNE,
            ])
            ->orderByDesc('date_traitement')
            ->get()
            ->map(function (AttributionDossier $attribution) {
                $presentation = $this->presentation($attribution);

                return [
                    'id' => $attribution->id,
                    'origine_libelle' => $presentation['origine_libelle'],
                    'numero' => $presentation['numero'],
                    'detail' => $presentation['detail'],
                    'service' => $presentation['service'],
                    'traite_le' => $attribution->date_traitement?->format('d/m/Y'),
                    'signe_le' => $attribution->date_signature?->format('d/m/Y'),
                    'statut' => $attribution->statut,
                    'statut_libelle' => $attribution->statut === AttributionDossier::STATUT_SIGNE
                        ? 'Signé'
                        : 'En attente de signature',
                ];
            })
            ->values()
            ->all();
    }

    public function donneesAdmin(): array
    {
        $attributions = AttributionDossier::query()
            ->with([
                'dossier' => function ($query) {
                    $query->select('id', 'txt_num_dossier', 'txt_num_dordre', 'slt_service_rendu')
                        ->with([
                            'terrains' => function ($terrains) {
                                $terrains->select(
                                    'id',
                                    'txt_num_dossier',
                                    'txt_nicad',
                                    'txt_num_titre',
                                    'txt_lotissement',
                                    'txt_HorsLotissement',
                                    'slt_commune',
                                    'titulaire_id',
                                )->orderBy('id')->with([
                                    'commune:id,slt_commune',
                                    'titulaire:id,slt_titulaire,txt_prenom,txt_nom,txt_denominationPM',
                                ]);
                            },
                        ]);
                },
                'arrivee:id,txt_numdordre,txt_numdossier,txt_typecourrier,txt_numcourier,txt_NICAD,txt_numTitreFoncier,txt_prenomNom,txt_objet,txt_lotissement',
                'depart:id,txt_numdordrecd,txt_numdossiercd,txt_typecourriercd,txt_referencecd,txt_NICADcd,txt_numTitreFonciercd,txt_prenomNomcd,txt_objetcd,txt_lotissementcd',
                'agent:id,name',
                'signataire:id,name',
            ])
            ->orderByDesc('date_attribution')
            ->get();

        $preuves = $this->preuvesPresentes($attributions);
        $adminId = (int) auth()->id();

        $lignes = $attributions->map(function (AttributionDossier $attribution) use ($preuves, $adminId) {
            return $this->ligneAdmin($attribution, $preuves[$attribution->id] ?? false, $adminId);
        });

        $ouverts = function ($query) {
            $query->whereIn('statut', [
                AttributionDossier::STATUT_ATTRIBUE,
                AttributionDossier::STATUT_TRAITE,
                AttributionDossier::STATUT_SIGNE,
            ]);
        };

        $disponibles = Dossier::query()->whereDoesntHave('attributions', $ouverts)->count()
            + Arrivee::query()->whereDoesntHave('attributions', $ouverts)->count()
            + Depart::query()->whereDoesntHave('attributions', $ouverts)->count();

        $agents = User::query()
            ->where('is_validated', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'disponibles' => $disponibles,
            'agents' => $agents->map(fn (User $user) => [
                'value' => $user->id,
                'label' => $user->name,
            ])->values()->all(),
            'enCours' => $lignes->where('statut', AttributionDossier::STATUT_ATTRIBUE)->values()->all(),
            'traites' => $lignes->where('statut', AttributionDossier::STATUT_TRAITE)->values()->all(),
            'signes' => $lignes->where('statut', AttributionDossier::STATUT_SIGNE)->values()->all(),
            'mesDossiers' => $lignes
                ->where('a_moi', true)
                ->where('statut', AttributionDossier::STATUT_ATTRIBUE)
                ->map(fn (array $ligne) => [
                    'id' => $ligne['id'],
                    'origine_libelle' => $ligne['origine_libelle'],
                    'numero' => $ligne['numero'],
                    'en_retard' => $ligne['en_retard'],
                ])
                ->values()
                ->all(),
            'stats' => [
                'en_cours' => $lignes->where('statut', AttributionDossier::STATUT_ATTRIBUE)->count(),
                'en_retard' => $lignes->where('en_retard', true)->count(),
                'traites' => $lignes->where('statut', AttributionDossier::STATUT_TRAITE)->count(),
                'signes' => $lignes->where('statut', AttributionDossier::STATUT_SIGNE)->count(),
            ],
            'courriersArrivee' => $this->registreArrivees(),
        ];
    }

    public function registreArrivees(): array
    {
        $page = Arrivee::query()
            ->orderByDesc('dt_datearrivee')
            ->orderByDesc('id')
            ->paginate(15, ['id', 'txt_numdordre', 'txt_numcourier', 'dt_datearrivee', 'txt_agenttraiteur', 'txt_objet'], 'page_courriers')
            ->withQueryString();

        return [
            'data' => $page->getCollection()->map(function (Arrivee $arrivee) {
                $date = $arrivee->dt_datearrivee;

                return [
                    'id' => $arrivee->id,
                    'numero' => $arrivee->txt_numdordre,
                    'courrier' => $arrivee->txt_numcourier,
                    'date' => $date ? Carbon::parse($date)->format('d/m/Y') : null,
                    'agent' => $arrivee->txt_agenttraiteur,
                    'objet' => $arrivee->txt_objet,
                ];
            })->values()->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'prev_page_url' => $page->previousPageUrl(),
            'next_page_url' => $page->nextPageUrl(),
        ];
    }

    public function evaluation(): array
    {
        $attributions = AttributionDossier::query()
            ->with('agent:id,name')
            ->get();

        $mois = collect(range(5, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));
        $labels = $mois->map(fn ($date) => $date->locale('fr')->isoFormat('MMM YYYY'))->values()->all();

        $parAgent = $attributions->groupBy('user_id');
        $utilisateurs = [];
        $evolution = [];

        foreach ($parAgent as $lignes) {
            $agent = $lignes->first()->agent;
            if (! $agent) {
                continue;
            }

            $termines = $lignes->whereIn('statut', [
                AttributionDossier::STATUT_TRAITE,
                AttributionDossier::STATUT_SIGNE,
            ]);
            $enRetard = $lignes->filter(fn (AttributionDossier $ligne) => $ligne->estEnRetard())->count();
            $enCours = $lignes->filter(
                fn (AttributionDossier $ligne) => $ligne->statut === AttributionDossier::STATUT_ATTRIBUE && ! $ligne->estEnRetard()
            )->count();
            $delais = $termines
                ->filter(fn (AttributionDossier $ligne) => $ligne->date_traitement && $ligne->date_attribution)
                ->map(fn (AttributionDossier $ligne) => $ligne->date_attribution->diffInDays($ligne->date_traitement));

            $utilisateurs[] = [
                'id' => $agent->id,
                'nom' => $agent->name,
                'en_cours' => $enCours,
                'en_retard' => $enRetard,
                'traites' => $termines->count(),
                'signes' => $lignes->where('statut', AttributionDossier::STATUT_SIGNE)->count(),
                'delai_moyen' => $delais->isEmpty() ? null : round($delais->avg(), 1),
            ];

            $evolution[] = [
                'nom' => $agent->name,
                'data' => $mois->map(function ($debut) use ($termines) {
                    $fin = $debut->copy()->endOfMonth();

                    return $termines->filter(function (AttributionDossier $ligne) use ($debut, $fin) {
                        return $ligne->date_traitement
                            && $ligne->date_traitement->between($debut, $fin);
                    })->count();
                })->values()->all(),
            ];
        }

        usort($utilisateurs, fn (array $a, array $b) => $b['traites'] <=> $a['traites']);

        return [
            'labels' => $labels,
            'utilisateurs' => $utilisateurs,
            'evolution' => $evolution,
            'synthese' => [
                'en_cours' => $attributions->filter(
                    fn (AttributionDossier $ligne) => $ligne->statut === AttributionDossier::STATUT_ATTRIBUE && ! $ligne->estEnRetard()
                )->count(),
                'en_retard' => $attributions->filter(fn (AttributionDossier $ligne) => $ligne->estEnRetard())->count(),
                'traites' => $attributions->where('statut', AttributionDossier::STATUT_TRAITE)->count(),
                'signes' => $attributions->where('statut', AttributionDossier::STATUT_SIGNE)->count(),
            ],
        ];
    }

    public function normaliserNicad(?string $nicad): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $nicad));
    }

    private function trouverDans(Builder $query, array $colonnes, string $numero, string $absent, string $ambigu): Model
    {
        $cherche = preg_replace('/\s+/', '', $numero) ?? '';

        foreach ($colonnes as $colonne) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $colonne)) {
                continue;
            }

            $trouves = (clone $query)
                ->whereRaw("REPLACE(REPLACE({$colonne}, ' ', ''), '\t', '') = ?", [$cherche])
                ->limit(2)
                ->get();

            if ($trouves->count() === 1) {
                return $trouves->first();
            }

            if ($trouves->count() > 1) {
                throw ValidationException::withMessages([
                    'txt_num_dossier' => $ambigu,
                ]);
            }
        }

        throw ValidationException::withMessages([
            'txt_num_dossier' => $absent,
        ]);
    }

    private function empecherDoublon(string $origine, int $id): void
    {
        $colonne = match ($origine) {
            'arrivee' => 'arrivee_id',
            'depart' => 'depart_id',
            default => 'dossier_id',
        };

        $existe = AttributionDossier::query()
            ->where('origine', $origine)
            ->where($colonne, $id)
            ->whereIn('statut', [
                AttributionDossier::STATUT_ATTRIBUE,
                AttributionDossier::STATUT_TRAITE,
                AttributionDossier::STATUT_SIGNE,
            ])
            ->lockForUpdate()
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'txt_num_dossier' => 'Ce dossier est déjà attribué.',
            ]);
        }
    }

    private function enregistrerNicad(AttributionDossier $attribution, array $saisie): void
    {
        $section = preg_replace('/\D/', '', (string) ($saisie['txt_num_section'] ?? '')) ?? '';
        $parcelle = preg_replace('/\D/', '', (string) ($saisie['txt_num_parcelle'] ?? '')) ?? '';
        $appartement = preg_replace('/\D/', '', (string) ($saisie['txt_appartement'] ?? '')) ?? '';

        if (strlen($section) !== 3) {
            throw ValidationException::withMessages([
                'txt_num_section' => 'Le N° de section doit contenir exactement 3 chiffres.',
            ]);
        }

        if (strlen($parcelle) !== 5) {
            throw ValidationException::withMessages([
                'txt_num_parcelle' => 'Le N° de parcelle doit contenir exactement 5 chiffres.',
            ]);
        }

        if ($appartement !== '' && strlen($appartement) !== 3) {
            throw ValidationException::withMessages([
                'txt_appartement' => 'Le N° d\'appartement doit contenir exactement 3 chiffres.',
            ]);
        }

        $terrains = Terrain::query()
            ->where('txt_num_dossier', $attribution->dossier_id)
            ->lockForUpdate()
            ->get();

        if ($terrains->isEmpty()) {
            throw ValidationException::withMessages([
                'txt_nicad' => 'Ce dossier n\'a pas de terrain. Le NICAD ne peut pas être enregistré.',
            ]);
        }

        $terrains->load('commune:id,txt_codeCommune');
        $cible = $terrains->first(fn (Terrain $terrain) => trim((string) $terrain->txt_nicad) === '')
            ?? $terrains->first();
        $prefixe = preg_replace('/\s+/', '', (string) ($cible?->commune?->txt_codeCommune ?? '')) ?? '';

        if ($prefixe === '') {
            throw ValidationException::withMessages([
                'txt_nicad' => 'La commune du dossier n\'a pas de code. Le NICAD ne peut pas être constitué.',
            ]);
        }

        $nicad = $prefixe.$section.$parcelle.$appartement;

        if (strlen($nicad) < 9) {
            throw ValidationException::withMessages([
                'txt_nicad' => 'Le NICAD est incomplet.',
            ]);
        }

        $autre = Terrain::query()
            ->with('dossier:id,txt_num_dossier')
            ->whereRaw("REPLACE(REPLACE(REPLACE(txt_nicad,' ',''),'\n',''),'\r','') = ?", [$nicad])
            ->whereNotNull('txt_num_dossier')
            ->where('txt_num_dossier', '!=', $attribution->dossier_id)
            ->first();

        if ($autre) {
            throw ValidationException::withMessages([
                'txt_nicad' => 'Ce NICAD existe et est déjà associé au dossier N° '.($autre->dossier->txt_num_dossier ?? 'inconnu').'.',
            ]);
        }

        $dejaPresent = $terrains->first(
            fn (Terrain $terrain) => $this->normaliserNicad($terrain->txt_nicad) === $nicad
        );

        if ($dejaPresent) {
            return;
        }

        $vide = $terrains->first(fn (Terrain $terrain) => trim((string) $terrain->txt_nicad) === '');

        if (! $vide) {
            throw ValidationException::withMessages([
                'txt_nicad' => 'Ce dossier possède déjà un autre NICAD.',
            ]);
        }

        $vide->txt_nicad = $nicad;
        $vide->txt_num_section = $section;
        $vide->txt_num_parcelle = $parcelle;
        if ($appartement !== '') {
            $vide->txt_appartement = $appartement;
        }
        $vide->save();
    }

    private function preuveCorrespond(AttributionDossier $attribution, string $saisi): bool
    {
        $saisi = $this->normaliserNicad($saisi);
        if ($saisi === '') {
            return false;
        }

        foreach ($this->preuvesAttendues($attribution) as $attendu) {
            $normalise = $this->normaliserNicad($attendu);
            if ($normalise !== '' && strlen($normalise) === strlen($saisi) && hash_equals($normalise, $saisi)) {
                return true;
            }
        }

        return false;
    }

    private function preuvesAttendues(AttributionDossier $attribution): array
    {
        return match ($attribution->origine) {
            'arrivee' => Arrivee::query()->whereKey($attribution->arrivee_id)->pluck('txt_numcourier')->all(),
            'depart' => Depart::query()->whereKey($attribution->depart_id)->pluck('txt_referencecd')->all(),
            default => Terrain::query()->where('txt_num_dossier', $attribution->dossier_id)->pluck('txt_nicad')->all(),
        };
    }

    private function preuvesPresentes(Collection $attributions): array
    {
        $dossierIds = $attributions
            ->filter(fn (AttributionDossier $attribution) => ($attribution->origine ?: 'dossier') === 'dossier')
            ->pluck('dossier_id')
            ->filter();

        $avecNicad = Terrain::query()
            ->whereIn('txt_num_dossier', $dossierIds)
            ->whereNotNull('txt_nicad')
            ->where('txt_nicad', '!=', '')
            ->pluck('txt_num_dossier')
            ->map(fn ($id) => (int) $id);

        $presence = [];
        foreach ($attributions as $attribution) {
            $presence[$attribution->id] = match ($attribution->origine) {
                'arrivee' => trim((string) $attribution->arrivee?->txt_numcourier) !== '',
                'depart' => trim((string) $attribution->depart?->txt_referencecd) !== '',
                default => $avecNicad->contains((int) $attribution->dossier_id),
            };
        }

        return $presence;
    }

    private function memoriserAgent(Model $cible, string $nom): void
    {
        if ($cible instanceof Dossier) {
            $cible->txt_agentTraiteur = $nom;
            $cible->save();
        }

        if ($cible instanceof Arrivee) {
            $cible->txt_agenttraiteur = $nom;
            $cible->save();
        }
    }

    private function memoriserAgentAttribue(AttributionDossier $attribution, string $nom): void
    {
        if ($attribution->origine === 'arrivee' && $attribution->arrivee_id) {
            Arrivee::query()->whereKey($attribution->arrivee_id)->update(['txt_agenttraiteur' => $nom]);

            return;
        }

        if (($attribution->origine ?: 'dossier') === 'dossier' && $attribution->dossier_id) {
            Dossier::query()->whereKey($attribution->dossier_id)->update(['txt_agentTraiteur' => $nom]);
        }
    }

    private function agentParNom(string $nom): ?User
    {
        $nom = trim($nom);
        if ($nom === '') {
            return null;
        }

        $agents = User::query()
            ->where('is_validated', true)
            ->where('name', '!=', '')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nom)])
            ->limit(2)
            ->get();

        return $agents->count() === 1 ? $agents->first() : null;
    }

    private function origineDe(Model $cible): string
    {
        return match (true) {
            $cible instanceof Arrivee => 'arrivee',
            $cible instanceof Depart => 'depart',
            default => 'dossier',
        };
    }

    private function origineLibelle(?string $origine): string
    {
        return match ($origine) {
            'arrivee' => 'Courrier arrivée',
            'depart' => 'Courrier départ',
            default => 'Dossier',
        };
    }

    private function numeroDe(AttributionDossier $attribution): string
    {
        return match ($attribution->origine) {
            'arrivee' => (string) ($attribution->arrivee?->txt_numdordre ?? $attribution->arrivee_id),
            'depart' => (string) ($attribution->depart?->txt_numdordrecd ?? $attribution->depart_id),
            default => (string) ($attribution->dossier?->txt_num_dossier ?? $attribution->dossier_id),
        };
    }

    private function messagePreuve(?string $origine): string
    {
        return match ($origine) {
            'arrivee' => 'Le numéro de courrier ne correspond pas à ce courrier.',
            'depart' => 'La référence ne correspond pas à ce courrier.',
            default => 'Le NICAD saisi ne correspond pas à ce dossier.',
        };
    }

    private function envoyerMessageRappel(AttributionDossier $attribution): void
    {
        $senderId = (int) $attribution->attribue_par;
        if ($senderId === (int) $attribution->user_id) {
            $senderId = (int) User::query()
                ->where('role', 'admin')
                ->where('id', '!=', $attribution->user_id)
                ->value('id');
        }

        if ($senderId === 0) {
            return;
        }

        $numero = $this->numeroDe($attribution);
        $depuis = $attribution->date_attribution?->format('d/m/Y') ?? '';

        Message::query()->create([
            'sender_id' => $senderId,
            'receiver_id' => $attribution->user_id,
            'content' => "Rappel : {$this->origineLibelle($attribution->origine)} n° {$numero}, qui vous est attribué depuis le {$depuis}, a dépassé le délai de 10 jours. Merci de le traiter et de le valider.",
            'is_read' => false,
            'is_received' => false,
        ]);
    }

    private function communesDossiers(Collection $attributions): array
    {
        $ids = $attributions
            ->filter(fn (AttributionDossier $attribution) => ($attribution->origine ?: 'dossier') === 'dossier')
            ->pluck('dossier_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        $terrains = Terrain::query()
            ->whereIn('txt_num_dossier', $ids)
            ->get(['id', 'txt_num_dossier', 'slt_commune', 'txt_nicad']);

        $communes = [];
        foreach ($ids as $id) {
            $duDossier = $terrains->filter(fn (Terrain $terrain) => (int) $terrain->txt_num_dossier === $id);
            $cible = $duDossier->first(fn (Terrain $terrain) => trim((string) $terrain->txt_nicad) === '')
                ?? $duDossier->first();
            $communes[$id] = $cible?->slt_commune;
        }

        return $communes;
    }

    private function ligneAgent(AttributionDossier $attribution, bool $preuvePresente, mixed $sltCommune = null): array
    {
        $presentation = $this->presentation($attribution);

        return [
            'id' => $attribution->id,
            'origine' => $presentation['origine'],
            'origine_libelle' => $presentation['origine_libelle'],
            'numero' => $presentation['numero'],
            'detail' => $presentation['detail'],
            'service' => $presentation['service'],
            'preuve' => $presentation['preuve'],
            'preuve_libelle' => $presentation['preuve_libelle'],
            'preuve_presente' => $preuvePresente,
            'slt_commune' => $sltCommune,
            'attribue_le' => $attribution->date_attribution?->format('d/m/Y'),
            'echeance' => $attribution->date_echeance?->format('d/m/Y'),
            'jours_restants' => $attribution->joursRestants(),
            'en_retard' => $attribution->estEnRetard(),
            'verrouille' => $attribution->estVerrouille(),
            'verrouille_jusqua' => $attribution->estVerrouille()
                ? $attribution->verrouille_jusqua->format('H:i')
                : null,
        ];
    }

    private function ligneAdmin(AttributionDossier $attribution, bool $preuvePresente, int $adminId): array
    {
        $presentation = $this->presentation($attribution);

        return [
            'id' => $attribution->id,
            'origine' => $presentation['origine'],
            'origine_libelle' => $presentation['origine_libelle'],
            'numero' => $presentation['numero'],
            'detail' => $presentation['detail'],
            'service' => $presentation['service'],
            'identite' => $this->identiteListe($attribution),
            'preuve_absente' => $presentation['preuve_absente'],
            'agent' => $attribution->agent?->name,
            'agent_id' => $attribution->user_id,
            'a_moi' => (int) $attribution->user_id === $adminId,
            'signataire' => $attribution->signataire?->name,
            'statut' => $attribution->statut,
            'attribue_le' => $attribution->date_attribution?->format('d/m/Y'),
            'echeance' => $attribution->date_echeance?->format('d/m/Y'),
            'traite_le' => $attribution->date_traitement?->format('d/m/Y'),
            'signe_le' => $attribution->date_signature?->format('d/m/Y'),
            'jours_restants' => $attribution->joursRestants(),
            'en_retard' => $attribution->estEnRetard(),
            'nicad_present' => $preuvePresente,
            'rappel_envoye' => $attribution->rappel_envoye_at !== null,
        ];
    }

    public function fiche(AttributionDossier $attribution): array
    {
        $attribution->loadMissing([
            'agent:id,name',
            'dossier',
            'arrivee',
            'depart',
        ]);

        $presentation = $this->presentation($attribution);
        $statut = $attribution->statut;

        return [
            'id' => $attribution->id,
            'origine_libelle' => $presentation['origine_libelle'],
            'numero' => $presentation['numero'],
            'detail' => $presentation['detail'],
            'service' => $presentation['service'],
            'statut' => $statut,
            'statut_libelle' => match ($statut) {
                AttributionDossier::STATUT_TRAITE => 'En attente de signature',
                AttributionDossier::STATUT_SIGNE => 'Signé',
                default => $attribution->estEnRetard() ? 'En retard' : 'À traiter',
            },
            'en_retard' => $attribution->estEnRetard(),
            'agent' => $attribution->agent?->name,
            'attribue_le' => $attribution->date_attribution?->format('d/m/Y'),
            'echeance' => $attribution->date_echeance?->format('d/m/Y'),
            'traite_le' => $attribution->date_traitement?->format('d/m/Y'),
            'signe_le' => $attribution->date_signature?->format('d/m/Y'),
            'sections' => match ($attribution->origine) {
                'arrivee' => $this->sectionsArrivee($attribution->arrivee),
                'depart' => $this->sectionsDepart($attribution->depart),
                default => $this->sectionsDossier($attribution->dossier),
            },
        ];
    }

    private function sectionsDossier(?Dossier $dossier): array
    {
        if (! $dossier) {
            return [];
        }

        $terrains = $dossier->terrains()->with([
            'region:id,slt_region',
            'departement:id,slt_departement',
            'arrondissement:id,slt_arrondissement',
            'commune:id,slt_commune',
            'titulaire:id,slt_titulaire,txt_prenom,txt_nom,txt_denominationPM',
        ])->get();

        $sections = [[
            'titre' => 'Dossier',
            'lignes' => $this->lignes([
                'N° dossier' => $dossier->txt_num_dossier,
                'N° d\'ordre' => $dossier->txt_num_dordre,
                'Service rendu' => $dossier->slt_service_rendu,
                'Date de création' => $this->dateLisible($dossier->dt_date_creation),
                'État de cession' => $dossier->txt_etat_cession,
                'Cession définitive' => $dossier->txt_cession_definitive,
                'Agent traiteur' => $dossier->txt_agentTraiteur,
            ]),
        ]];

        if ($terrains->isEmpty()) {
            $sections[] = [
                'titre' => 'Terrain',
                'lignes' => $this->lignes(['Terrain' => null]),
            ];

            return $sections;
        }

        foreach ($terrains as $index => $terrain) {
            $titulaire = $terrain->titulaire;
            $nom = trim(implode(' ', array_filter([
                $titulaire?->txt_prenom,
                $titulaire?->txt_nom,
                $titulaire?->txt_denominationPM,
            ])));

            $sections[] = [
                'titre' => $terrains->count() > 1 ? 'Terrain '.($index + 1) : 'Terrain',
                'lignes' => $this->lignes([
                    'Région' => $terrain->region?->slt_region,
                    'Département' => $terrain->departement?->slt_departement,
                    'Arrondissement' => $terrain->arrondissement?->slt_arrondissement,
                    'Commune' => $terrain->commune?->slt_commune,
                    'Lotissement' => $terrain->txt_lotissement ?: $terrain->txt_HorsLotissement,
                    'N° lot' => $terrain->txt_num_lotissement,
                    'N° section' => $terrain->txt_num_section,
                    'N° parcelle' => $terrain->txt_num_parcelle,
                    'Appartement' => $terrain->txt_appartement,
                    'Surface' => $terrain->nbr_surface,
                    'NICAD' => $terrain->txt_nicad,
                    'N° titre' => $terrain->txt_num_titre,
                    'Titulaire' => trim(($titulaire?->slt_titulaire ? $titulaire->slt_titulaire.' — ' : '').$nom),
                ]),
            ];
        }

        return $sections;
    }

    private function sectionsArrivee(?Arrivee $arrivee): array
    {
        if (! $arrivee) {
            return [];
        }

        return [
            [
                'titre' => 'Courrier',
                'lignes' => $this->lignes([
                    'N° d\'ordre' => $arrivee->txt_numdordre,
                    'N° bordereau' => $arrivee->txt_numbordereau,
                    'N° dossier foncier' => $arrivee->txt_numdossier,
                    'N° courrier' => $arrivee->txt_numcourier,
                    'Date d\'arrivée' => $this->dateLisible($arrivee->dt_datearrivee),
                    'Date du courrier' => $this->dateLisible($arrivee->dt_datecourier),
                    'Type' => $arrivee->txt_typecourrier,
                    'Caractère' => $arrivee->txt_caractere,
                    'Catégorie' => $arrivee->txt_categorie,
                    'Désignation' => $arrivee->txt_designation,
                    'Référence' => $arrivee->txt_reference,
                    'Nombre de pièces' => $arrivee->txt_nombrepiece,
                    'Objet' => $arrivee->txt_objet,
                    'Expéditeur' => $arrivee->txt_expediteur,
                    'Requérant' => $arrivee->txt_prenomNom,
                ]),
            ],
            [
                'titre' => 'Bien et rendez-vous',
                'lignes' => $this->lignes([
                    'Lotissement' => $arrivee->txt_lotissement,
                    'N° lot' => $arrivee->txt_numLot,
                    'Superficie' => $arrivee->txt_superficie,
                    'NICAD' => $arrivee->txt_NICAD,
                    'N° TF' => $arrivee->txt_numTitreFoncier,
                    'Date' => $this->dateLisible($arrivee->dt_date),
                    'Heure' => $arrivee->txt_heure,
                    'Lieu' => $arrivee->txt_lieu,
                    'Observation' => $arrivee->txt_observation,
                ]),
            ],
        ];
    }

    private function sectionsDepart(?Depart $depart): array
    {
        if (! $depart) {
            return [];
        }

        return [
            [
                'titre' => 'Courrier',
                'lignes' => $this->lignes([
                    'N° d\'ordre' => $depart->txt_numdordrecd,
                    'N° dossier foncier' => $depart->txt_numdossiercd,
                    'Date du courrier' => $this->dateLisible($depart->dt_datecouriercd),
                    'Date d\'envoi' => $this->dateLisible($depart->dt_dateenvoicd),
                    'Type' => $depart->txt_typecourriercd,
                    'Caractère' => $depart->txt_caracterecd,
                    'Catégorie' => $depart->txt_categoriecd,
                    'Référence' => $depart->txt_referencecd,
                    'Réf. courrier arrivée' => $depart->txt_referencecourierarriveecd,
                    'Réf. courrier départ' => $depart->txt_referencecourierdepartcd,
                    'Avis de réponse' => $depart->txt_avisReponsecd,
                    'Nombre de pièces' => $depart->txt_nombrepiececd,
                    'Objet' => $depart->txt_objetcd,
                    'Destinataire' => $depart->txt_destinatairecd,
                    'Concerné' => $depart->txt_prenomNomcd,
                ]),
            ],
            [
                'titre' => 'Bien',
                'lignes' => $this->lignes([
                    'Lotissement' => $depart->txt_lotissementcd,
                    'N° lot' => $depart->txt_numLotcd,
                    'Superficie' => $depart->txt_superficiecd,
                    'N° TF' => $depart->txt_numTitreFonciercd,
                    'Durée de traitement' => $depart->txt_dureetraitementcd,
                    'Réf. réception' => $depart->txt_referencereceptioncd,
                    'Observation' => $depart->txt_observationcd,
                ]),
            ],
        ];
    }

    private function lignes(array $champs): array
    {
        $lignes = [];
        foreach ($champs as $label => $valeur) {
            $texte = trim((string) $valeur);
            $lignes[] = [
                'label' => $label,
                'valeur' => $texte !== '' ? $texte : '—',
            ];
        }

        return $lignes;
    }

    private function dateLisible(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        try {
            return Carbon::parse($valeur)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $valeur;
        }
    }

    private function identiteListe(AttributionDossier $attribution): array
    {
        $champs = match ($attribution->origine ?: 'dossier') {
            'arrivee' => [
                'NICAD' => $attribution->arrivee?->txt_NICAD,
                'Requérant' => $attribution->arrivee?->txt_prenomNom,
            ],
            'depart' => [
                'NICAD' => $attribution->depart?->txt_NICADcd,
                'Concerné' => $attribution->depart?->txt_prenomNomcd,
            ],
            default => $this->identiteDossier($attribution->dossier),
        };

        $lignes = [];
        foreach ($champs as $label => $valeur) {
            $texte = trim((string) $valeur);
            if ($texte === '') {
                continue;
            }
            $lignes[] = ['label' => $label, 'valeur' => $texte];
        }

        return $lignes;
    }

    private function identiteDossier(?Dossier $dossier): array
    {
        $vide = [
            'NICAD' => null,
            'Titulaire' => null,
        ];

        $terrain = $dossier?->terrains?->first();
        if (! $terrain) {
            return $vide;
        }

        $titulaire = $terrain->titulaire;
        $nom = trim(implode(' ', array_filter([
            $titulaire?->txt_prenom,
            $titulaire?->txt_nom,
            $titulaire?->txt_denominationPM,
        ])));

        return [
            'NICAD' => $terrain->txt_nicad,
            'Titulaire' => trim(($titulaire?->slt_titulaire ? $titulaire->slt_titulaire.' — ' : '').$nom),
        ];
    }

    private function presentation(AttributionDossier $attribution): array
    {
        $origine = $attribution->origine ?: 'dossier';

        return match ($origine) {
            'arrivee' => [
                'origine' => 'arrivee',
                'origine_libelle' => 'Courrier arrivée',
                'numero' => $attribution->arrivee?->txt_numdordre,
                'detail' => $attribution->arrivee?->txt_numdossier ? 'Dossier foncier '.$attribution->arrivee->txt_numdossier : null,
                'service' => $attribution->arrivee?->txt_typecourrier,
                'preuve' => 'courrier',
                'preuve_libelle' => 'N° du courrier',
                'preuve_absente' => 'N° courrier absent',
            ],
            'depart' => [
                'origine' => 'depart',
                'origine_libelle' => 'Courrier départ',
                'numero' => $attribution->depart?->txt_numdordrecd,
                'detail' => $attribution->depart?->txt_numdossiercd ? 'Dossier foncier '.$attribution->depart->txt_numdossiercd : null,
                'service' => $attribution->depart?->txt_typecourriercd,
                'preuve' => 'reference',
                'preuve_libelle' => 'Référence du courrier',
                'preuve_absente' => 'Référence absente',
            ],
            default => [
                'origine' => 'dossier',
                'origine_libelle' => 'Dossier',
                'numero' => $attribution->dossier?->txt_num_dossier,
                'detail' => $attribution->dossier?->txt_num_dordre ? 'Ordre '.$attribution->dossier->txt_num_dordre : null,
                'service' => $attribution->dossier?->slt_service_rendu,
                'preuve' => 'nicad',
                'preuve_libelle' => 'NICAD du dossier',
                'preuve_absente' => 'NICAD absent du dossier',
            ],
        };
    }
}
