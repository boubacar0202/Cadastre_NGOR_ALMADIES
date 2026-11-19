<?php 
 
namespace App\Http\Controllers;

use App\Models\Terrain;
use App\Models\Dossier;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DonneeController extends Controller
{
    public function index(Request $request)
    {
        return $this->create($request);
    }

    public function create(Request $request)
    {
        // Relations nécessaires pour l'affichage
        $relations = [
            'region:id,slt_region',
            'departement:id,slt_departement',
            'commune:id,slt_commune',
            'arrondissement:id,slt_arrondissement',
            'dossier:id,txt_num_dossier,slt_service_rendu,txt_etat_cession,txt_cession_definitive,dt_date_creation',
            'titulaire:id,slt_titulaire,txt_nationalite,slt_civilite,txt_prenom,txt_nom,txt_denominationPM,slt_piece,txt_numPiece,dt_date_delivrance,dt_date_naissance,txt_lieu_naissance,txt_adresse,tel_telephone,eml_email,fichierPDF,txt_representant,txt_perssonneRepresentantPM,txt_personneResponsablePA,txt_telephonePM,txt_telephonePA,tel_telRepresentant',
            'references_cadastrales:id,rd_immatriculation_terrain,slt_dependant_domaine,ussu_bornage,slt_lf,txt_num_requisition,txt_surface_bornage,dt_date_bornage,txt_nom_geometre',
            'evaluations_terrains:id,txt_nicad,dt_dateEvaluation',
        ];

        $query = Terrain::with($relations);

        // Filtres
        if ($request->filled('mois')) {
            $query->whereHas('dossier', function($q) use ($request) {
                $q->whereMonth('dt_date_creation', $request->mois);
            });
        }

        if ($request->filled('annee')) {
            $query->whereHas('dossier', function($q) use ($request) {
                $q->whereYear('dt_date_creation', $request->annee);
            });
        }

        if ($request->filled('numero')) {
            $query->whereHas('dossier', function($q) use ($request) {
                $q->where('txt_num_dossier', 'like', '%' . $request->numero . '%');
            });
        }

        // ✅ Recherche améliorée sur prenomNom
        if ($request->filled('prenomNom')) {
            $searchTerm = $request->prenomNom;
            
            $query->whereHas('titulaire', function($q) use ($searchTerm) {
                // Nettoyer et échapper les caractères spéciaux LIKE
                $searchTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);
                
                $terms = array_filter(explode(' ', $searchTerm));
                
                if (count($terms) > 1) {
                    // Recherche multi-mots (plus précise)
                    foreach ($terms as $term) {
                        $term = '%' . $term . '%';
                        $q->where(function($subQ) use ($term) {
                            $subQ->where('txt_prenom', 'like', $term)
                                ->orWhere('txt_nom', 'like', $term)
                                ->orWhere('txt_denominationPM', 'like', $term);
                        });
                    }
                } else {
                    // Recherche simple
                    $term = '%' . $searchTerm . '%';
                    $q->where(function($subQ) use ($term) {
                        $subQ->where('txt_prenom', 'like', $term)
                            ->orWhere('txt_nom', 'like', $term)
                            ->orWhere('txt_denominationPM', 'like', $term)
                            // Recherche sur le nom complet
                            ->orWhere(DB::raw("CONCAT_WS(' ', txt_prenom, txt_nom)"), 'like', $term);
                    });
                }
            });
        }

        if ($request->filled('NumTitre')) {
            $terme = str_replace(['%', '_'], ['\%', '\_'], trim($request->NumTitre));
            if ($terme !== '') {
                $query->where('txt_num_titre', 'like', '%' . $terme . '%');
            }
        }

        if ($request->filled('numNICAD')) {
            $terme = str_replace(['%', '_'], ['\%', '\_'], trim($request->numNICAD));
            if ($terme !== '') {
                $query->where('txt_nicad', 'like', '%' . $terme . '%');
            }
        }

        if ($request->filled('refServiceRendu')) {
            $query->whereHas('dossier', function($q) use ($request) {
                $q->where('slt_service_rendu', $request->refServiceRendu);
            });
        }

        if ($request->boolean('export')) {
            return response()->stream(function () use ($query) {
                echo '[';
                $premier = true;

                $query->orderBy('id', 'desc')->chunk(300, function ($terrains) use (&$premier) {
                    foreach ($terrains as $terrain) {
                        if (! $premier) {
                            echo ',';
                        }
                        $premier = false;
                        echo json_encode($this->terrainPourExport($terrain), JSON_UNESCAPED_UNICODE);
                    }
                });

                echo ']';
            }, 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        // Pagination
        $terrains = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        

        // 🔥 TOTAL GÉNÉRAL DE LA BASE (sans filtres)
        $totalGeneral = Terrain::count();

        // Récupération des Sans Nicad  sans filtres
        $terrainsSansNicad = Terrain::where(function($q) {
            $q->whereNull('txt_nicad')
              ->orWhere('txt_nicad', '')
              ->orWhere('txt_nicad', '');
        })->count();

        // Récupération des services uniques
        $uniqueServices = Cache::remember('unique_services_donnee', 3600, function () {
            return Dossier::whereNotNull('slt_service_rendu')
                ->distinct()
                ->pluck('slt_service_rendu')
                ->toArray();
        });

        return Inertia::render('donnee/create', [
            'terrains' => $terrains,
            'uniqueServices' => $uniqueServices,
            'totalGeneral' => $totalGeneral, 
            'terrainsSansNicad' => $terrainsSansNicad,
            'filters' => $request->only(['mois', 'annee', 'numero', 'prenomNom', 'NumTitre', 'numNICAD', 'refServiceRendu'])
        ]);
    }

    private function terrainPourExport(Terrain $terrain): array
    {
        $dossier = $terrain->dossier;
        $titulaire = $terrain->titulaire;
        $reference = $terrain->references_cadastrales;
        $evaluation = $terrain->evaluations_terrains;

        return [
            'txt_num_parcelle' => $terrain->txt_num_parcelle,
            'txt_lotissement' => $terrain->txt_lotissement,
            'txt_HorsLotissement' => $terrain->txt_HorsLotissement,
            'txt_num_lotissement' => $terrain->txt_num_lotissement,
            'txt_num_section' => $terrain->txt_num_section,
            'txt_appartement' => $terrain->txt_appartement,
            'txt_num_titre' => $terrain->txt_num_titre,
            'nbr_surface' => $terrain->nbr_surface,
            'slt_document_admin' => $terrain->slt_document_admin,
            'txt_num_deliberation' => $terrain->txt_num_deliberation,
            'dt_date_deliberation' => $this->jour($terrain->dt_date_deliberation),
            'txt_nicad' => $terrain->txt_nicad,
            'slt_statut_nicad' => $terrain->slt_statut_nicad,
            'slt_motif_annulation_nicad' => $terrain->slt_motif_annulation_nicad,
            'slt_signalement' => $terrain->slt_signalement,
            'slt_designationSignalement' => $terrain->slt_designationSignalement,
            'txt_titre_mere' => $terrain->txt_titre_mere,
            'region' => ['slt_region' => $terrain->region?->slt_region],
            'departement' => ['slt_departement' => $terrain->departement?->slt_departement],
            'arrondissement' => ['slt_arrondissement' => $terrain->arrondissement?->slt_arrondissement],
            'commune' => ['slt_commune' => $terrain->commune?->slt_commune],
            'dossier' => [
                'txt_num_dossier' => $dossier?->txt_num_dossier,
                'slt_service_rendu' => $dossier?->slt_service_rendu,
                'txt_etat_cession' => $dossier?->txt_etat_cession,
                'txt_cession_definitive' => $dossier?->txt_cession_definitive,
                'dt_date_creation' => $this->jour($dossier?->dt_date_creation),
            ],
            'titulaire' => [
                'slt_titulaire' => $titulaire?->slt_titulaire,
                'txt_nationalite' => $titulaire?->txt_nationalite,
                'slt_civilite' => $titulaire?->slt_civilite,
                'txt_prenom' => $titulaire?->txt_prenom,
                'txt_nom' => $titulaire?->txt_nom,
                'txt_denominationPM' => $titulaire?->txt_denominationPM,
                'slt_piece' => $titulaire?->slt_piece,
                'txt_numPiece' => $titulaire?->txt_numPiece,
                'dt_date_delivrance' => $this->jour($titulaire?->dt_date_delivrance),
                'dt_date_naissance' => $this->jour($titulaire?->dt_date_naissance),
                'txt_lieu_naissance' => $titulaire?->txt_lieu_naissance,
                'txt_adresse' => $titulaire?->txt_adresse,
                'tel_telephone' => $titulaire?->tel_telephone,
                'eml_email' => $titulaire?->eml_email,
                'txt_representant' => $titulaire?->txt_representant,
                'txt_perssonneRepresentantPM' => $titulaire?->txt_perssonneRepresentantPM,
                'txt_personneResponsablePA' => $titulaire?->txt_personneResponsablePA,
                'txt_telephonePM' => $titulaire?->txt_telephonePM,
                'txt_telephonePA' => $titulaire?->txt_telephonePA,
                'tel_telRepresentant' => $titulaire?->tel_telRepresentant,
            ],
            'references_cadastrales' => [
                'rd_immatriculation_terrain' => $reference?->rd_immatriculation_terrain,
                'slt_dependant_domaine' => $reference?->slt_dependant_domaine,
                'ussu_bornage' => $reference?->ussu_bornage,
                'slt_lf' => $reference?->slt_lf,
                'txt_num_requisition' => $reference?->txt_num_requisition,
                'txt_surface_bornage' => $reference?->txt_surface_bornage,
                'dt_date_bornage' => $this->jour($reference?->dt_date_bornage),
                'txt_nom_geometre' => $reference?->txt_nom_geometre,
            ],
            'evaluations_terrains' => [
                'dt_dateEvaluation' => $this->jour($evaluation?->dt_dateEvaluation),
            ],
        ];
    }

    private function jour(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('Y-m-d');
        }

        return substr((string) $valeur, 0, 10);
    }
}