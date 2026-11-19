<script setup>
import BoutonSupprimer from "@/Components/BoutonSupprimer.vue";
import BoutonModifier from "@/Components/BoutonModifier.vue";
// ============================================================
// 1. IMPORTS
// ============================================================
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import DeleteModal from '@/Components/DeleteModal.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import proj4 from 'proj4';
import { toast } from "@/Components/AppToast";
import * as turf from '@turf/turf';
import ExtraitPlanApercu from '@/Components/ExtraitPlanApercu.vue';
import { anneauPrincipal, blocageExtrait, commandesCarte, commandesExtrait, nomFichierExtrait, porteeCarte, porteeSituation } from '@/utils/extraitPlan';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
axios.defaults.headers.common['X-CSRF-TOKEN'] = page.props.csrf_token;

// ============================================================
// 2. PROPS
// ============================================================
const props = defineProps({
    point_densifiers: { type: Array, default: () => [] },
    shapefiles: { type: Array, default: () => [] },
    signataireExtrait: { type: String, default: '' } });

// ============================================================
// 3. CONSTANTES
// ============================================================
const ZOOM_THRESHOLD_POINTS = 16;
const ZOOM_THRESHOLD_HISTORIC = 11;
const SHAPEFILE_RELOAD_DELAY = 500;

const DEFAULT_PARCEL_STYLE = {
    color: '#052e16',
    weight: 1.6,
    fillColor: '#16a34a',
    fillOpacity: 0.28,
    lineCap: 'butt',
    lineJoin: 'miter'
};
const HOVER_PARCEL_STYLE = {
    color: '#14532d',
    weight: 2.2,
    fillColor: '#22c55e',
    fillOpacity: 0.42,
    lineCap: 'butt',
    lineJoin: 'miter'
};
const SELECTED_PARCEL_STYLE = {
    color: '#7f1d1d',
    weight: 2.4,
    fillColor: '#dc2626',
    fillOpacity: 0.38,
    opacity: 1,
    lineCap: 'butt',
    lineJoin: 'miter'
};

// ============================================================
// 4. RÉFÉRENCES
// ============================================================
const map = ref(null);
const parcelsLayer = ref(null);
const selectionHighlightLayer = ref(null);
const selectedLayer = ref(null);
const selectedParcelleFeature = ref(null);

const convertToUTM = ref(false);
const geojsonData = ref(null);
const sections = ref([]);
const sectionPoints = ref([]);
const parcellePoints = ref([]);
const selectedInfo = ref(null);
const erreurExtrait = ref('');
const extraitEnCours = ref(false);
const extraitOuvert = ref(null);
const choixEchelleOuvert = ref(false);
const planChoisi = ref('extrait');
const echelleSituationChoisie = ref(2000);
const echelleParcelleChoisie = ref(500);
const echelleUniqueChoisie = ref(1000);
const coordonneesChoisies = ref(false);
const plansDisponibles = [
    { id: 'extrait', label: 'Extrait de plan' },
    { id: 'cic', label: 'Plan pour CIC' },
    { id: 'situation', label: 'Plan de situation' },
    { id: 'morcellement', label: 'Plan de morcellement' },
    { id: 'autorisation', label: 'Plan pour autorisation de construire' },
];
const bureauExtrait = { nom: 'Ngor Almadies', ville: 'Ngor Almadies' };
const showInfo = ref(false);

const pointsDensifies = ref([]);
const markersLayer = ref(null);
const selectedPointId = ref(null);
const showList = ref(true);
const searchQueryList = ref('');
const searchQuery = ref('');
const searchResults = ref([]);
const userMarker = ref(null);
const locationLoading = ref(false);
const scaleDenominator = ref(1);
const currentZoom = ref(12);

// Shapefiles
const shapefiles = ref([]);
const shapefilesLayerGroup = ref(null);
const showShapefileModal = ref(false);
const activeTab = ref('list');
const uploading = ref(false);
const analyseEnCours = ref(false);
const analyseParcelle = ref(null);
const analyseProjet = ref([]);
const projetAnalyse = ref(false);
const form = ref({ nom: '', type_entite: '', shp: null, shx: null, dbf: null, prj: null, srid: '4326' });
const editingShapefile = ref(null);
const savingShapefile = ref(false);
const shapefileCache = new Map();
const shapefileEchecs = new Set();
const couchesShapefile = new Map();
let chargementShapefileToken = 0;
let chargementParcelles = false;
let dernierPlanEnAvant = null;
let jetonSelection = 0;
const featuredShapefileId = ref(null);
const tableAttributs = ref(null);
const listeEntites = ref(null);
const rechercheEntites = ref('');
const rechercheAttributs = ref('');
const chargementTableAttributs = ref(false);
const surlignageShapefile = ref(null);
let coucheParcelleRouge = null;
const STYLE_PARCELLE_ROUGE = {
    color: '#b91c1c',
    weight: 3,
    fillColor: '#ef4444',
    fillOpacity: 0.55,
    opacity: 1 };
let shapefileReloadTimer = null;
const showDeleteModal = ref(false);
const loadingDelete = ref(false);
const elementASupprimer = ref(null);

const ORDRE_CHAMPS_QGIS = ['TEXT', 'LAYER', 'ENTITYHAND', 'SUBCLASSES', 'LINETYPE', 'PAPERSPACE'];
const COULEURS_FORTES = {
    limite_region: '#1e3a8a',
    limite_departement: '#115e59',
    limite_arrondissement: '#5b21b6',
    limite_commune: '#9a3412',
    limite_section: '#9a3412',
    limite_parcelle: '#052e16',
    numero_section: '#7c2d12',
    numero_parcelle: '#052e16',
    numero_lot: '#052e16',
};
const TYPES_ENTITE = [
    { value: 'limite_region', label: 'Limite Région', ordre: 1, fill: 0.16, weight: 2 },
    { value: 'limite_departement', label: 'Limite Département', ordre: 2, fill: 0.16, weight: 1.8 },
    { value: 'limite_arrondissement', label: 'Limite Arrondissement', ordre: 3, fill: 0.16, weight: 1.6 },
    { value: 'limite_commune', label: 'Limite Commune', ordre: 4, fill: 0.18, weight: 1.6 },
    { value: 'limite_section', label: 'Limite Section', ordre: 5, fill: 0.2, weight: 1.8 },
    { value: 'limite_parcelle', label: 'Limite Parcelle', ordre: 6, fill: 0.32, weight: 1.5 },
    { value: 'numero_section', label: 'Numéro Section', ordre: 7, fill: 0, weight: 1.2 },
    { value: 'numero_parcelle', label: 'Numéro Parcelle', ordre: 8, fill: 0, weight: 1.2 },
    { value: 'numero_lot', label: 'Numéro Lot', ordre: 9, fill: 0, weight: 1.2 },
];
const optionsTypeEntite = TYPES_ENTITE.map(({ value, label }) => ({ value, label }));
const LIBELLES_CHAMPS_QGIS = {
    TEXT: 'Texte',
    LAYER: 'Couche',
    ENTITYHAND: 'Identifiant',
    SUBCLASSES: 'Type d’entité',
    LINETYPE: 'Type de ligne',
    PAPERSPACE: 'Espace papier' };
let currentZoomAnimation = null;

// Milieu entre la pointe des Almadies et le village de Ngor.
const CENTRE_NGOR_ALMADIES = [14.7466, -17.5215];
const ZOOM_NGOR_ALMADIES = 15;

// Sites de repère du bureau
const historicalSites = ref([
    { name: "Pointe des Almadies", lat: 14.7447, lng: -17.5292, description: "Pointe des Almadies, commune de Ngor.", type: "historique" },
    { name: "Village de Ngor", lat: 14.7484, lng: -17.5138, description: "Village traditionnel de Ngor.", type: "historique" },
    { name: "Phare des Mamelles", lat: 14.7244, lng: -17.5016, description: "Phare des Mamelles, commune d'Ouakam.", type: "historique" },
]);
const historicLayerGroup = ref(null);

// ============================================================
// 5. UTILITAIRES
// ============================================================
const isDev = import.meta.env.DEV;
const log = (...args) => isDev && console.log(...args);

function safeFlyTo(lat, lng, zoom = 20, options = {}) {
    if (!map.value) return;
    try {
        map.value.flyTo([lat, lng], zoom, options);
    } catch (e) {
        console.warn('flyTo error:', e.message);
    }
}

function recentrerSurNgorAlmadies() {
    safeFlyTo(CENTRE_NGOR_ALMADIES[0], CENTRE_NGOR_ALMADIES[1], ZOOM_NGOR_ALMADIES, { duration: 0.8 });
}

function safeFitBounds(bounds, options = {}) {
    if (!map.value || !bounds) return;
    try {
        if (!bounds.isValid || !bounds.isValid()) return;
        map.value.fitBounds(bounds, options);
    } catch (e) {
        console.warn('fitBounds error:', e.message);
    }
}

// 🎯 Zoom intelligent avec anti-rebond
function zoomToParcelle(layer) {
    if (!map.value || !layer) return;

    if (currentZoomAnimation) {
        clearTimeout(currentZoomAnimation);
        currentZoomAnimation = null;
    }

    try {
        const bounds = layer.getBounds();
        if (!bounds.isValid()) return;

        currentZoomAnimation = setTimeout(() => {
            try {
                const size = bounds.getNorthEast().distanceTo(bounds.getSouthWest());
                let maxZoom = 19;
                if (size < 20) maxZoom = 21;
                else if (size < 50) maxZoom = 20;
                else if (size < 200) maxZoom = 19;
                else if (size < 1000) maxZoom = 18;
                else maxZoom = 17;

                map.value.fitBounds(bounds, {
                    padding: [80, 80],
                    maxZoom,
                    animate: true,
                    duration: 0.5
                });
            } catch (e) {
                console.warn('⚠️ Zoom error:', e.message);
            }
            currentZoomAnimation = null;
        }, 50);
    } catch (e) {
        console.warn('⚠️ Erreur zoomToParcelle:', e);
    }
}

const UTM28N = '+proj=utm +zone=28 +datum=WGS84 +units=m +no_defs';
const WGS84 = '+proj=longlat +datum=WGS84 +no_defs';
const CODE_COMMUNE_NGOR = '01130132';
const LIEU_NGOR = {
    region: 'Dakar',
    departement: 'Dakar',
    arrondissement: 'Almadies',
    commune: 'Ngor',
};

function lieuDuPlan(terrain) {
    return {
        region: terrain?.region || LIEU_NGOR.region,
        departement: terrain?.departement || LIEU_NGOR.departement,
        arrondissement: terrain?.arrondissement || LIEU_NGOR.arrondissement,
        commune: terrain?.commune || LIEU_NGOR.commune,
    };
}

function gpsToUtm(latitude, longitude) {
    const [x, y] = proj4(WGS84, UTM28N, [longitude, latitude]);
    return { x, y };
}

function utmToGps(est, nord) {
    const e = parseFloat(est);
    const n = parseFloat(nord);
    if (isNaN(e) || isNaN(n)) return null;
    try {
        const [longitude, latitude] = proj4(UTM28N, WGS84, [e, n]);
        return { latitude, longitude };
    } catch (error) {
        console.error('❌ Erreur proj4 :', error);
        return null;
    }
}

const TYPES_LOURDS = new Set(['limite_parcelle', 'numero_parcelle', 'numero_lot']);

function estCoucheDoublon(shp) {
    return shp?.verification?.type === 'doublon';
}

function isHeavyShapefile(shp) {
    if (estCoucheDoublon(shp)) return false;
    if (TYPES_LOURDS.has(shp?.type_entite)) return true;
    const nb = shp.nb_entites || 0;
    if (nb > 1500) return true;
    const name = (shp.nom || '').toLowerCase();
    const table = (shp.nom_table || '').toLowerCase();
    return name.includes('parcelle') || table.includes('parcelle');
}

// ============================================================
// 6. RECHERCHE LOCALITÉ
// ============================================================
let searchTimeout = null;
function searchLocation() {
    clearTimeout(searchTimeout);
    if (!searchQuery.value.trim()) {
        searchResults.value = [];
        return;
    }
    searchTimeout = setTimeout(async () => {
        try {
            const response = await axios.get('https://nominatim.openstreetmap.org/search', {
                params: { q: searchQuery.value, format: 'json', countrycodes: 'sn', limit: 6, addressdetails: 1 },
                headers: { 'User-Agent': 'Cadastre_Ngor_Almadies/1.0' }
            });
            searchResults.value = response.data;
        } catch (error) {
            console.error('Erreur de recherche:', error);
        }
    }, 500);
}

function selectLocation(result) {
    const lat = parseFloat(result.lat);
    const lon = parseFloat(result.lon);
    safeFlyTo(lat, lon, 14);
    searchResults.value = [];
    searchQuery.value = result.display_name.split(',').slice(0, 3).join(',');
    const { x, y } = gpsToUtm(lat, lon);
    const infoHtml = `
        <div style="min-width: 200px; padding: 5px;">
            <h4 style="margin: 0 0 8px 0; color: #613104;">📍 Localisation</h4>
            <hr>
            <p><b>Latitude :</b> ${lat.toFixed(6)}</p>
            <p><b>Longitude :</b> ${lon.toFixed(6)}</p>
            <p><b>UTM X (28N) :</b> ${Math.round(x)}</p>
            <p><b>UTM Y (28N) :</b> ${Math.round(y)}</p>
        </div>
    `;
    if (map.value) {
        L.popup().setLatLng([lat, lon]).setContent(infoHtml).openOn(map.value);
    }
    toast.info('📍 Localisation trouvée');
}

// ============================================================
// 7. POINTS DENSIFIÉS
// ============================================================
function addPointsToMap({ ajusterVue = true } = {}) {
    if (!map.value) return;

    if (markersLayer.value) {
        try { map.value.removeLayer(markersLayer.value); } catch (e) {}
        markersLayer.value = null;
    }
    markersLayer.value = L.layerGroup().addTo(map.value);

    const latLngs = [];
    let pointsAdded = 0;

    pointsDensifies.value.forEach((point) => {
        const coords = utmToGps(point.txt_estPoint, point.txt_nordPoint);
        if (!coords) return;
        const { latitude, longitude } = coords;
        latLngs.push([latitude, longitude]);
        pointsAdded++;

        const marker = L.circleMarker([latitude, longitude], {
            radius: 6,
            fillColor: '#2563eb',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 1
        }).addTo(markersLayer.value);

        const popupContent = `
            <div style="min-width: 220px; padding: 5px;">
                <h3 style="margin: 0 0 8px 0; color: #613104; font-weight: bold;">📍 ${point.txt_nomPoint || 'Point'}</h3>
                <hr>
                <p><b>Est (X) :</b> ${point.txt_estPoint || '-'}</p>
                <p><b>Nord (Y) :</b> ${point.txt_nordPoint || '-'}</p>
                <p><b>Hauteur (Z) :</b> ${point.txt_hauteurPoint || '-'}</p>
                ${point.txt_documentsAfferants ? `<p><a href="/storage/${point.txt_documentsAfferants}" target="_blank" style="color: #613104;">📄 Voir PDF</a></p>` : ''}
            </div>
        `;
        marker.bindPopup(popupContent);

        marker.on('click', function () {
            resetParcelleSelection();
            showInfo.value = true;
            selectedInfo.value = { type: 'point', point: point, loading: false, data: point };
            selectedPointId.value = point.id;
            document.querySelectorAll('.point-list-item').forEach(el => el.classList.remove('active'));
            const listItem = document.querySelector(`[data-point-id="${point.id}"]`);
            if (listItem) listItem.classList.add('active');
        });
    });

    if (ajusterVue && latLngs.length > 0) {
        const bounds = L.latLngBounds(latLngs);
        safeFitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
        toast.success(`✅ ${pointsAdded} points densifiés chargés`);
    }
}

// ============================================================
// 8. LOCALISER UN POINT
// ============================================================
function localiserPoint(point) {
    if (!point) return;
    const coords = utmToGps(point.txt_estPoint, point.txt_nordPoint);
    if (!coords) {
        toast.error('Coordonnées invalides');
        return;
    }
    const { latitude, longitude } = coords;
    safeFlyTo(latitude, longitude, 20, { duration: 1.2 });

    const popupContent = `
        <div style="min-width: 200px; padding: 5px;">
            <h4 style="margin: 0 0 8px 0; color: #613104;">📍 ${point.txt_nomPoint || 'Point'}</h4>
            <hr>
            <p><b>Est (X) :</b> ${point.txt_estPoint || '-'}</p>
            <p><b>Nord (Y) :</b> ${point.txt_nordPoint || '-'}</p>
            <p><b>Hauteur (Z) :</b> ${point.txt_hauteurPoint || '-'}</p>
            ${point.txt_documentsAfferants ? `<p><a href="/storage/${point.txt_documentsAfferants}" target="_blank" style="color: #613104;">📄 Voir PDF</a></p>` : ''}
        </div>
    `;
    if (map.value) {
        try {
            L.popup().setLatLng([latitude, longitude]).setContent(popupContent).openOn(map.value);
        } catch (e) {}
    }

    resetParcelleSelection();
    showInfo.value = true;
    selectedInfo.value = { type: 'point', point: point, loading: false, data: point };
    selectedPointId.value = point.id;
    document.querySelectorAll('.point-list-item').forEach(el => el.classList.remove('active'));
    const listItem = document.querySelector(`[data-point-id="${point.id}"]`);
    if (listItem) listItem.classList.add('active');
}

// ============================================================
// 9. GÉOLOCALISATION
// ============================================================
function getUserLocation() {
    if (!navigator.geolocation) {
        toast.error('La géolocalisation n\'est pas supportée');
        return;
    }
    locationLoading.value = true;
    navigator.geolocation.getCurrentPosition(
        (position) => {
            const { latitude, longitude } = position.coords;
            if (userMarker.value && map.value) {
                try { map.value.removeLayer(userMarker.value); } catch (e) {}
            }
            const userIcon = L.divIcon({
                className: 'user-marker',
                html: `<div style="background:#2563eb; width:20px; height:20px; border-radius:50%; border:3px solid white; box-shadow:0 0 20px rgba(37,99,235,0.6);"><div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:8px; height:8px; background:white; border-radius:50%;"></div></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });
            userMarker.value = L.marker([latitude, longitude], { icon: userIcon })
                .addTo(map.value)
                .bindPopup('📍 Vous êtes ici');
            safeFlyTo(latitude, longitude, 15);
            locationLoading.value = false;
            toast.success('📍 Votre position a été localisée');
        },
        () => {
            locationLoading.value = false;
            toast.error('Impossible d\'obtenir votre position');
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// ============================================================
// 10. ÉCHELLE
// ============================================================
function updateScale() {
    if (!map.value) return;
    try {
        const center = map.value.getCenter();
        const zoom = map.value.getZoom();
        currentZoom.value = zoom;
        const latRad = center.lat * Math.PI / 180;
        let denominator = 591657550.5 * Math.cos(latRad) / Math.pow(2, zoom);
        let rounded = Math.round(denominator);
        if (rounded > 1000) {
            const magnitude = Math.pow(10, Math.floor(Math.log10(rounded)));
            rounded = Math.round(rounded / magnitude) * magnitude;
        } else if (rounded > 100) {
            rounded = Math.round(rounded / 10) * 10;
        }
        scaleDenominator.value = rounded;
    } catch (e) {}
}

// ============================================================
// 11. FIND SECTION / PARCELLE
// ============================================================
function findSection(feature) {
    try {
        const pt = turf.center(feature);

        for (const section of sections.value) {
            if (!section?.geometry?.coordinates) continue;

            let polygon;
            try {
                if (section.geometry.type === 'MultiPolygon') {
                    polygon = turf.multiPolygon(section.geometry.coordinates);
                } else if (section.geometry.type === 'Polygon') {
                    polygon = turf.polygon(section.geometry.coordinates);
                } else {
                    continue;
                }
            } catch (e) { continue; }

            if (turf.booleanPointInPolygon(pt, polygon)) {
                for (const secPoint of sectionPoints.value) {
                    if (!secPoint?.geometry?.coordinates) continue;
                    let coords;
                    if (secPoint.geometry.type === 'MultiPoint') coords = secPoint.geometry.coordinates[0];
                    else if (secPoint.geometry.type === 'Point') coords = secPoint.geometry.coordinates;
                    else continue;

                    const point = turf.point(coords);
                    if (turf.booleanPointInPolygon(point, polygon)) {
                        return secPoint.properties?.RefName
                            || secPoint.properties?.refname
                            || secPoint.properties?.REFNAME
                            || secPoint.properties?.nom
                            || secPoint.properties?.NOM
                            || 'N/A';
                    }
                }
                return section.properties?.RefName
                    || section.properties?.refname
                    || section.properties?.nom
                    || 'N/A';
            }
        }
    } catch (e) {
        console.warn('⚠️ findSection:', e);
    }
    return 'N/A';
}

function findParcelle(feature) {
    try {
        const center = turf.center(feature);
        let nearest = null;
        let minDistance = Infinity;

        parcellePoints.value.forEach(p => {
            if (!p?.geometry?.coordinates) return;
            const coords = p.geometry.type === 'MultiPoint' ? p.geometry.coordinates[0] : p.geometry.coordinates;
            const pt = turf.point(coords);
            const distance = turf.distance(center, pt);
            if (distance < minDistance) {
                minDistance = distance;
                nearest = p;
            }
        });

        return nearest?.properties?.RefName
            || nearest?.properties?.refname
            || nearest?.properties?.NUMERO
            || nearest?.properties?.numero
            || nearest?.properties?.NOM
            || 'N/A';
    } catch (e) {
        console.warn('⚠️ findParcelle:', e);
        return 'N/A';
    }
}

// ============================================================
// 12. RESET PARCELLE (sécurisé)
// ============================================================
function resetParcelleSelection() {
    try {
        if (selectionHighlightLayer.value && map.value) {
            if (map.value.hasLayer(selectionHighlightLayer.value)) {
                map.value.removeLayer(selectionHighlightLayer.value);
            }
            selectionHighlightLayer.value = null;
        }
    } catch (e) {
        console.warn('⚠️ Reset selection:', e.message);
        selectionHighlightLayer.value = null;
    }
    selectedLayer.value = null;
    selectedParcelleFeature.value = null;
}

// ============================================================
// 13. SÉLECTION PARCELLE (calque SVG dédié)
// ============================================================
function selectParcelle(feature, layer) {
    resetParcelleSelection();

    try {
        // Calque rouge indépendant — TOUJOURS visible
        selectionHighlightLayer.value = L.geoJSON(feature, {
            style: SELECTED_PARCEL_STYLE,
            interactive: false
        }).addTo(map.value);

        selectedLayer.value = layer;
        selectedParcelleFeature.value = feature;

        zoomToParcelle(layer);
    } catch (e) {
        console.warn('⚠️ selectParcelle:', e);
    }
}

// ============================================================
// 14. CHARGEMENT GEOJSON PRINCIPAL
// ============================================================
function loadGeoJSON() {
    if (!geojsonData.value || !map.value) return;

    if (parcelsLayer.value) {
        try { map.value.removeLayer(parcelsLayer.value); } catch (e) {}
        parcelsLayer.value = null;
    }

    parcelsLayer.value = L.geoJSON(geojsonData.value, {
        smoothFactor: 1.5,
        filter: (feature) =>
            feature.geometry.type === 'MultiPolygon' ||
            feature.geometry.type === 'Polygon',
        style: DEFAULT_PARCEL_STYLE,
        onEachFeature: (feature, layer) => {

            layer.on('mouseover', () => {
                if (layer !== selectedLayer.value) {
                    layer.setStyle(HOVER_PARCEL_STYLE);
                }
            });

            layer.on('mouseout', () => {
                if (layer !== selectedLayer.value) {
                    layer.setStyle(DEFAULT_PARCEL_STYLE);
                }
            });

            layer.on('click', (e) => {
                if (e.originalEvent) L.DomEvent.stopPropagation(e);

                selectedPointId.value = null;
                document.querySelectorAll('.point-list-item').forEach(el => el.classList.remove('active'));

                selectParcelle(feature, layer);

                const parcelleNum = findParcelle(feature);
                const section = findSection(feature);

                showInfo.value = false;
                selectedInfo.value = null;

                nextTick(() => {
                    selectedInfo.value = {
                        type: 'parcelle',
                        parcelle: parcelleNum || '—',
                        section: section || 'Non définie',
                        loading: true,
                        data: null
                    };
                    showInfo.value = true;
                });

                axios.post('/terrain/search', {
                    parcelle: parcelleNum,
                    section: section
                })
                .then(res => {
                    if (selectedInfo.value?.parcelle === parcelleNum) {
                        selectedInfo.value.data = res.data;
                        selectedInfo.value.loading = false;
                    }
                })
                .catch(() => {
                    if (selectedInfo.value) selectedInfo.value.loading = false;
                });
            });
        }
    }).addTo(map.value);
}

// ============================================================
// 15. SITES HISTORIQUES
// ============================================================
function addHistoricalSites() {
    const group = L.layerGroup();
    historicalSites.value.forEach(site => {
        const icon = L.divIcon({
            className: 'historic-marker',
            html: `<div style="background: #f59e0b; width: 32px; height: 32px; border-radius: 50%; border: 3px solid white; box-shadow: 0 3px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; font-size: 16px;">🏛️</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        const marker = L.marker([site.lat, site.lng], { icon })
            .bindPopup(`
                <div style="min-width: 200px;">
                    <h4 style="margin: 0 0 5px 0; color: #b45309;">🏛️ ${site.name}</h4>
                    <p style="margin: 0; font-size: 13px;">${site.description || 'Site historique'}</p>
                </div>
            `);
        group.addLayer(marker);
    });
    historicLayerGroup.value = group;
    group.addTo(map.value);
    updateHistoricVisibility();
}

function updateHistoricVisibility() {
    if (!map.value || !historicLayerGroup.value) return;
    const zoom = map.value.getZoom();
    if (zoom >= ZOOM_THRESHOLD_HISTORIC) {
        if (!map.value.hasLayer(historicLayerGroup.value)) map.value.addLayer(historicLayerGroup.value);
    } else {
        if (map.value.hasLayer(historicLayerGroup.value)) map.value.removeLayer(historicLayerGroup.value);
    }
}

// ============================================================
// 16. SWITCH UTM/GPS
// ============================================================
const convert = () => {
    convertToUTM.value = !convertToUTM.value;
    toast[convertToUTM.value ? 'success' : 'info'](
        convertToUTM.value ? 'Coordonnées UTM activées' : 'Coordonnées GPS activées'
    );
};

// ============================================================
// 17. LISTE / STATS
// ============================================================
function toggleList() {
    showList.value = !showList.value;
}

const stats = computed(() => ({
    total: pointsDensifies.value.length,
    withDescription: pointsDensifies.value.filter(p => p.txt_descriptionPoint).length,
    withPDF: pointsDensifies.value.filter(p => p.txt_documentsAfferants).length
}));

const filteredPoints = computed(() => {
    if (!searchQueryList.value.trim()) return pointsDensifies.value;
    const q = searchQueryList.value.toLowerCase();
    return pointsDensifies.value.filter(p =>
        (p.txt_nomPoint || '').toLowerCase().includes(q) ||
        (p.txt_descriptionPoint || '').toLowerCase().includes(q)
    );
});

// ============================================================
// 18. MODALE SHAPEFILES
// ============================================================
function openShapefileModal(tab = 'list') {
    activeTab.value = tab;
    showShapefileModal.value = true;
}

function closeShapefileModal() {
    showShapefileModal.value = false;
    form.value = { nom: '', type_entite: '', shp: null, shx: null, dbf: null, prj: null, srid: '4326' };
    document.querySelectorAll('input[type="file"]').forEach(i => i.value = '');
}

// ============================================================
// 19. CHARGEMENT SHAPEFILES
// ============================================================
function estNumeroPlan(shp) {
    return shp?.type_entite === 'numero_parcelle' || shp?.type_entite === 'numero_lot';
}

function denominateurCarte(zoom) {
    if (!map.value || !Number.isFinite(zoom)) return Infinity;
    const latRad = map.value.getCenter().lat * Math.PI / 180;
    return (591657550.5 * Math.cos(latRad)) / (2 ** zoom);
}

function libelleNumeroPlan(shp, feature) {
    const brut = String(feature?.properties?.TEXT ?? feature?.properties?.text ?? '').trim();
    if (shp?.type_entite === 'numero_parcelle') return chiffresCadastre(brut, 5);
    const chiffres = brut.replace(/\D/g, '');
    if (!chiffres) return '';
    return String(parseInt(chiffres, 10));
}

function shapefileVisibleAuZoom(shp, zoom) {
    if (!shp.visible) return false;
    if (estNumeroPlan(shp)) return denominateurCarte(zoom) <= 3000;
    if (featuredShapefileId.value === shp.id) return true;
    return !(isHeavyShapefile(shp) && zoom < ZOOM_THRESHOLD_POINTS);
}

function normaliserGeojson(data) {
    if (typeof data === 'string') {
        try {
            data = JSON.parse(data);
        } catch (e) {
            return null;
        }
    }
    if (!data || !Array.isArray(data.features)) return null;
    return data;
}

async function lireGeojsonShapefile(shp) {
    const enCache = shapefileCache.get(shp.id);
    if (enCache) return enCache;

    const response = await axios.get(`/api/shapefiles/${shp.id}/geojson`);
    const data = normaliserGeojson(response.data);
    if (!data) return null;
    shapefileCache.set(shp.id, data);
    return data;
}

function limitesGeometrie(geometry) {
    if (!geometry?.coordinates) return null;
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    let trouve = false;

    const visiter = (coords) => {
        if (!Array.isArray(coords) || !coords.length) return;
        if (typeof coords[0] === 'number') {
            trouve = true;
            const x = coords[0];
            const y = coords[1];
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
            return;
        }
        coords.forEach(visiter);
    };

    visiter(geometry.coordinates);
    return trouve ? [minX, minY, maxX, maxY] : null;
}

function boiteFeature(feature) {
    if (feature._boite) return feature._boite;
    feature._boite = limitesGeometrie(feature.geometry);
    return feature._boite;
}

function geometrieDansLimites(feature, bounds) {
    const boite = boiteFeature(feature);
    if (!boite) return false;
    return boite[2] >= bounds.getWest()
        && boite[0] <= bounds.getEast()
        && boite[3] >= bounds.getSouth()
        && boite[1] <= bounds.getNorth();
}

function preparerAffichage(shp, data) {
    if (!isHeavyShapefile(shp) || !map.value) {
        return { data, textes: estNumeroPlan(shp) || data.features.length <= 800 };
    }

    const bounds = map.value.getBounds().pad(0.35);
    const features = data.features.filter((feature, index) => {
        if (!geometrieDansLimites(feature, bounds)) return false;
        feature._indexAffichage = index;
        return true;
    });

    return {
        data: { type: 'FeatureCollection', features },
        textes: estNumeroPlan(shp) || (features.length > 0 && features.length <= 250) };
}

function signatureCouche(shp) {
    if (!isHeavyShapefile(shp) || !map.value) return 'complet';
    const bounds = map.value.getBounds();
    return [
        map.value.getZoom().toFixed(1),
        bounds.getWest().toFixed(4),
        bounds.getSouth().toFixed(4),
        bounds.getEast().toFixed(4),
        bounds.getNorth().toFixed(4),
    ].join('|');
}

function ajouterCoucheShapefile(shp, data, textes) {
    if (!map.value || !shapefilesLayerGroup.value || couchesShapefile.has(shp.id)) return;

    const isPointLayer = data.features[0]?.geometry?.type === 'Point';
    const enAvant = featuredShapefileId.value === shp.id;
    let featureIndex = 0;
    const layer = L.geoJSON(data, {
        smoothFactor: 1.5,
        pointToLayer: isPointLayer
            ? (feature, latlng) => marqueurEntite(feature, latlng, shp, enAvant, textes)
            : undefined,
        style: (feature) => styleShapefile(shp, enAvant, feature),
        onEachFeature: (feature, lyr) => {
            const index = feature._indexAffichage ?? featureIndex++;
            lyr.on('click', (e) => {
                if (e.originalEvent) L.DomEvent.stopPropagation(e);
                selectionnerShapefile(shp, feature, index, lyr);
            });
        } });

    layer._shpId = shp.id;
    layer._signature = signatureCouche(shp);
    layer.addTo(shapefilesLayerGroup.value);
    couchesShapefile.set(shp.id, layer);
    if (enAvant || estCoucheDoublon(shp)) layer.bringToFront();
}

async function chargerShapefiles() {
    if (!map.value) return;

    if (!shapefilesLayerGroup.value) {
        shapefilesLayerGroup.value = L.layerGroup().addTo(map.value);
    }

    const token = ++chargementShapefileToken;
    const zoom = map.value.getZoom();

    if (dernierPlanEnAvant !== featuredShapefileId.value) {
        [dernierPlanEnAvant, featuredShapefileId.value].forEach((id) => {
            const couche = id ? couchesShapefile.get(id) : null;
            if (!couche) return;
            try { shapefilesLayerGroup.value.removeLayer(couche); } catch (e) {}
            couchesShapefile.delete(id);
        });
        dernierPlanEnAvant = featuredShapefileId.value;
    }

    const visibles = shapefiles.value.filter((shp) => shapefileVisibleAuZoom(shp, zoom));
    const idsVoulus = new Set(visibles.map((shp) => shp.id));

    const aRetirer = [];
    couchesShapefile.forEach((layer, id) => {
        const shp = shapefiles.value.find((item) => item.id === id);
        if (!shp || !idsVoulus.has(id) || layer._signature !== signatureCouche(shp)) {
            aRetirer.push(id);
        }
    });
    aRetirer.forEach((id) => {
        const layer = couchesShapefile.get(id);
        try { shapefilesLayerGroup.value.removeLayer(layer); } catch (e) {}
        couchesShapefile.delete(id);
    });

    visibles.forEach((shp) => {
        const layer = couchesShapefile.get(shp.id);
        if (!layer || typeof layer.setStyle !== 'function') return;
        layer.setStyle((feature) => styleShapefile(shp, featuredShapefileId.value === shp.id, feature));
    });

    const manquants = visibles.filter((shp) => !couchesShapefile.has(shp.id));
    if (manquants.some((shp) => shp.type_entite === 'numero_lot')) {
        const couchesNumero = shapefiles.value.filter((shp) => shp.type_entite === 'numero_parcelle');
        await Promise.all(couchesNumero.map((shp) => lireGeojsonShapefile(shp).catch(() => null)));
        clesNumeroParcelle = null;
    }
    await Promise.all(manquants.map(async (shp) => {
        try {
            const brut = await lireGeojsonShapefile(shp);
            if (token !== chargementShapefileToken || !map.value) return;
            if (!brut) {
                if (!shapefileEchecs.has(shp.id)) {
                    shapefileEchecs.add(shp.id);
                    toast.error(`Le plan « ${shp.nom} » est illisible`);
                }
                return;
            }
            if (!shapefileVisibleAuZoom(shp, map.value.getZoom())) return;
            releverNicadsDoublon(shp, brut.features);
            const affichage = preparerAffichage(shp, brut);
            if (!affichage.data.features.length) return;
            ajouterCoucheShapefile(shp, affichage.data, affichage.textes);
        } catch (e) {
            console.error(`❌ #${shp.id}:`, e.message);
            toast.error(`Impossible de charger "${shp.nom}"`);
        }
    }));
}

let clesNumeroParcelle = null;

function clePointPlan(lon, lat) {
    return `${Number(lon).toFixed(7)}|${Number(lat).toFixed(7)}`;
}

function clesDesNumerosParcelle() {
    if (clesNumeroParcelle) return clesNumeroParcelle;
    const cles = new Set();
    shapefiles.value.forEach((shp) => {
        if (shp.type_entite !== 'numero_parcelle') return;
        const data = shapefileCache.get(shp.id);
        (data?.features || []).forEach((feature) => {
            const coords = feature?.geometry?.coordinates;
            if (!Array.isArray(coords) || coords.length < 2) return;
            cles.add(clePointPlan(coords[0], coords[1]));
        });
    });
    clesNumeroParcelle = cles;
    return cles;
}

function lotPoseSurParcelle(feature) {
    const coords = feature?.geometry?.coordinates;
    if (!Array.isArray(coords) || coords.length < 2) return false;
    return clesDesNumerosParcelle().has(clePointPlan(coords[0], coords[1]));
}

function marqueurEntite(feature, latlng, shp, enAvant, textes) {
    if (estNumeroPlan(shp)) {
        const texte = libelleNumeroPlan(shp, feature);
        if (!texte) return L.circleMarker(latlng, { radius: 1, opacity: 0, fillOpacity: 0 });
        const lotAuDessus = shp.type_entite === 'numero_lot' && lotPoseSurParcelle(feature);
        return L.marker(latlng, {
            icon: L.divIcon({
                className: 'shp-chiffre',
                html: `<span>${escapeHtml(texte)}</span>`,
                iconSize: [52, 14],
                iconAnchor: lotAuDessus ? [26, 23] : [26, 7],
            }),
        });
    }
    const texte = textes ? String(feature?.properties?.TEXT ?? '').trim() : '';
    if (texte) {
        return L.marker(latlng, {
            icon: L.divIcon({
                className: 'shp-numero',
                html: `<span class="${enAvant ? 'is-active' : ''}">${escapeHtml(texte)}</span>`,
                iconSize: [42, 24],
                iconAnchor: [21, 12] }) });
    }
    return L.circleMarker(latlng, {
        radius: enAvant ? 8 : 6,
        fillColor: enAvant ? '#7f1d1d' : couleurForte(shp),
        color: '#ffffff',
        weight: 2,
        fillOpacity: 1 });
}

function escapeHtml(valeur) {
    return String(valeur)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function metaTypeEntite(type) {
    return TYPES_ENTITE.find((item) => item.value === type) || null;
}

function rangTypeEntite(shp) {
    return metaTypeEntite(shp?.type_entite)?.ordre ?? 99;
}

function libelleTypeEntite(type) {
    return metaTypeEntite(type)?.label || '';
}

const shapefilesTries = computed(() => {
    return [...shapefiles.value].sort((a, b) => {
        const rang = rangTypeEntite(a) - rangTypeEntite(b);
        if (rang !== 0) return rang;
        return String(a.nom || '').localeCompare(String(b.nom || ''), 'fr');
    });
});

function couleurForte(shp) {
    if (estCoucheDoublon(shp)) return '#b91c1c';
    return COULEURS_FORTES[shp?.type_entite] || shp?.couleur || '#052e16';
}

const nicadsDoublonParCouche = new Map();

function nicadDe(feature) {
    const proprietes = feature?.properties || {};
    const cle = Object.keys(proprietes).find((nom) => String(nom).toLowerCase() === 'nicad');
    return cle ? String(proprietes[cle] ?? '').trim() : '';
}

function releverNicadsDoublon(shp, features) {
    const comptes = new Map();
    (features || []).forEach((feature) => {
        if (!estPolygone(feature)) return;
        const nicad = nicadDe(feature);
        if (!nicad) return;
        comptes.set(nicad, (comptes.get(nicad) || 0) + 1);
    });
    const doublons = new Set();
    comptes.forEach((nombre, nicad) => {
        if (nombre > 1) doublons.add(nicad);
    });
    nicadsDoublonParCouche.set(shp.id, doublons);
}

function nicadEnDoublon(shp, feature) {
    const nicad = nicadDe(feature);
    return !!nicad && !!nicadsDoublonParCouche.get(shp?.id)?.has(nicad);
}

function styleShapefile(shp, enAvant, feature = null) {
    const meta = metaTypeEntite(shp?.type_entite);
    const doublon = estCoucheDoublon(shp) || (feature ? nicadEnDoublon(shp, feature) : false);
    const couleur = doublon ? '#b91c1c' : couleurForte(shp);
    const remplissage = doublon
        ? '#b91c1c'
        : (shp?.type_entite === 'limite_parcelle' ? '#22c55e' : couleur);
    return {
        color: enAvant ? '#7f1d1d' : couleur,
        weight: enAvant ? 2.6 : (meta?.weight ?? 1.5),
        fillColor: enAvant ? '#dc2626' : remplissage,
        fillOpacity: enAvant ? 0.4 : (meta?.fill ?? 0.22),
        opacity: 1,
        lineCap: 'butt',
        lineJoin: 'miter' };
}

function libelleChamp(cle) {
    return LIBELLES_CHAMPS_QGIS[cle] || cle;
}

function trierClesAttributs(cles) {
    return [...cles].sort((a, b) => {
        const ia = ORDRE_CHAMPS_QGIS.indexOf(a);
        const ib = ORDRE_CHAMPS_QGIS.indexOf(b);
        if (ia === -1 && ib === -1) return String(a).localeCompare(String(b));
        if (ia === -1) return 1;
        if (ib === -1) return -1;
        return ia - ib;
    });
}

function libelleGeometrie(type) {
    return {
        Point: 'Point',
        MultiPoint: 'Multipoint',
        LineString: 'Ligne',
        MultiLineString: 'Multiligne',
        Polygon: 'Polygone',
        MultiPolygon: 'Multipolygone' }[type] || type || '';
}

function attributsShapefile(feature) {
    const proprietes = feature?.properties || {};
    return trierClesAttributs(Object.keys(proprietes).filter((cle) => !String(cle).startsWith('geom')))
        .map((cle) => {
            const brut = proprietes[cle];
            return {
                key: cle,
                label: libelleChamp(cle),
                value: brut === null || brut === undefined || String(brut).trim() === '' ? '' : String(brut) };
        });
}

function colonnesShapefile(features) {
    const vues = new Set();
    features.forEach((feature) => {
        Object.keys(feature?.properties || {}).forEach((cle) => {
            if (!String(cle).startsWith('geom')) vues.add(cle);
        });
    });
    return trierClesAttributs(vues);
}

function valeurAttribut(feature, cle) {
    const brut = feature?.properties?.[cle];
    if (brut === null || brut === undefined || String(brut).trim() === '') return '';
    return String(brut);
}

const lignesTableAttributs = computed(() => {
    const table = tableAttributs.value;
    if (!table) return [];
    const q = rechercheAttributs.value.trim().toLowerCase();
    return table.features
        .map((feature, index) => ({ feature, index }))
        .filter(({ feature }) => {
            if (!q) return true;
            return Object.values(feature.properties || {}).some((valeur) =>
                String(valeur ?? '').toLowerCase().includes(q)
            );
        });
});

const resumeSelectionShapefile = computed(() => {
    if (selectedInfo.value?.type !== 'shapefile') return '';
    const attributs = selectedInfo.value.attributes || [];
    const texte = attributs.find((item) => item.key === 'TEXT' && item.value);
    const couche = attributs.find((item) => item.key === 'LAYER' && item.value);
    const identifiant = attributs.find((item) => item.key === 'ENTITYHAND' && item.value);
    return [texte?.value, couche?.value, identifiant ? `#${identifiant.value}` : '']
        .filter(Boolean)
        .join(' · ');
});

function restaurerParcelleRouge() {
    if (coucheParcelleRouge?.setStyle && coucheParcelleRouge._styleOrigine) {
        coucheParcelleRouge.setStyle(coucheParcelleRouge._styleOrigine);
    }
    coucheParcelleRouge = null;
}

function peindreParcelleRouge(couche) {
    restaurerParcelleRouge();
    if (!couche?.setStyle) return;
    couche._styleOrigine = {
        color: couche.options.color,
        weight: couche.options.weight,
        fillColor: couche.options.fillColor,
        fillOpacity: couche.options.fillOpacity,
        opacity: couche.options.opacity ?? 1 };
    couche.setStyle(STYLE_PARCELLE_ROUGE);
    if (couche.bringToFront) couche.bringToFront();
    coucheParcelleRouge = couche;
}

function retirerSurlignageShapefile() {
    restaurerParcelleRouge();
    if (surlignageShapefile.value && map.value) {
        try { map.value.removeLayer(surlignageShapefile.value); } catch (e) {}
    }
    surlignageShapefile.value = null;
}

function surlignerEntiteShapefile(feature) {
    if (surlignageShapefile.value && map.value) {
        try { map.value.removeLayer(surlignageShapefile.value); } catch (e) {}
        surlignageShapefile.value = null;
    }
    if (!map.value || !feature?.geometry) return;
    surlignageShapefile.value = L.geoJSON(feature, {
        renderer: L.svg(),
        interactive: false,
        pointToLayer: (item, latlng) => L.circleMarker(latlng, {
            radius: 11,
            color: '#ffffff',
            weight: 3,
            fillColor: '#ef4444',
            fillOpacity: 1 }),
        style: STYLE_PARCELLE_ROUGE }).addTo(map.value);
    surlignageShapefile.value.bringToFront();
}

function zoomSurEntite(feature) {
    if (!map.value || !feature?.geometry) return;
    const couche = L.geoJSON(feature);
    const bounds = couche.getBounds();
    if (!bounds.isValid()) return;
    const type = feature.geometry.type;
    if (type === 'Point' || type === 'MultiPoint') {
        const centre = bounds.getCenter();
        safeFlyTo(centre.lat, centre.lng, Math.max(map.value.getZoom(), 18));
        return;
    }
    safeFitBounds(bounds, { padding: [80, 80], maxZoom: 19 });
}

function selectionnerShapefile(shp, feature = null, index = null, couche = null) {
    resetParcelleSelection();
    erreurExtrait.value = '';
    selectedPointId.value = null;

    const sauve = shp?.verification?.type === 'parcelle' ? shp.verification : null;
    const estParcelle = (['limite_parcelle', 'numero_parcelle'].includes(shp?.type_entite) && !!feature) || !!sauve;
    selectedInfo.value = {
        type: estParcelle ? 'parcelle' : 'shapefile',
        shapefile: shp,
        feature,
        featureIndex: index,
        geometrie: libelleGeometrie(feature?.geometry?.type),
        attributes: attributsShapefile(feature),
        parcelle: sauve?.prochain || '',
        section: sauve ? `${sauve.nomSection || ''}${sauve.section ? ' · ' + sauve.section : ''}`.replace(/^ · /, '') : '',
        loading: estParcelle && !!feature,
        data: ficheParcelleEnregistree(sauve) };
    showInfo.value = true;
    if (couche) peindreParcelleRouge(couche);
    if (feature) surlignerEntiteShapefile(feature);
    else retirerSurlignageShapefile();
    if (feature) identifierSelection(shp, feature, ++jetonSelection);
}

function effacerSelectionShapefile() {
    retirerSurlignageShapefile();
    if (selectedInfo.value?.type === 'shapefile') {
        selectedInfo.value = null;
        showInfo.value = false;
    }
}

function fermerPanneauInfo() {
    showInfo.value = false;
    resetParcelleSelection();
    retirerSurlignageShapefile();
    if (selectedInfo.value?.type === 'shapefile') {
        selectedInfo.value = null;
    }
}

function titreEntite(feature, index) {
    const texte = String(feature?.properties?.TEXT ?? '').trim();
    if (texte) return texte;
    const identifiant = String(feature?.properties?.ENTITYHAND ?? '').trim();
    if (identifiant) return `Entité ${identifiant}`;
    return `Entité ${index + 1}`;
}

function detailEntite(feature) {
    const couche = String(feature?.properties?.LAYER ?? '').trim();
    const geometrie = libelleGeometrie(feature?.geometry?.type);
    return [couche, geometrie].filter(Boolean).join(' · ');
}

const entitesFiltrees = computed(() => {
    const liste = listeEntites.value;
    if (!liste) return [];
    const q = rechercheEntites.value.trim().toLowerCase();
    return liste.features
        .map((feature, index) => ({
            feature,
            index,
            titre: titreEntite(feature, index),
            detail: detailEntite(feature) }))
        .filter((item) => !q || `${item.titre} ${item.detail}`.toLowerCase().includes(q))
        .sort((a, b) => a.titre.localeCompare(b.titre, 'fr', { numeric: true }));
});

async function donneesShapefile(shp) {
    return lireGeojsonShapefile(shp);
}

async function ouvrirListeEntites(shp) {
    if (!shp || chargementTableAttributs.value) return;
    chargementTableAttributs.value = true;
    try {
        const data = await donneesShapefile(shp);
        if (!data?.features?.length) {
            toast.error('Aucune entité dans ce plan.');
            return;
        }
        listeEntites.value = { shp, features: data.features };
        rechercheEntites.value = '';
        closeShapefileModal();
        if (!shp.visible) {
            await axios.patch(`/shapefiles/${shp.id}`, { visible: true });
            shp.visible = true;
            await chargerShapefiles();
        }
        await zoomToShapefile(shp);
    } catch (e) {
        toast.error('Impossible de lire les entités de ce plan.');
    } finally {
        chargementTableAttributs.value = false;
    }
}

function fermerListeEntites() {
    listeEntites.value = null;
    rechercheEntites.value = '';
}

function choisirEntite(feature, index) {
    const shp = listeEntites.value?.shp;
    if (!shp) return;
    selectionnerShapefile(shp, feature, index);
    zoomSurEntite(feature);
}

async function ouvrirTableAttributs(shp) {
    if (!shp || chargementTableAttributs.value) return;
    chargementTableAttributs.value = true;
    try {
        const data = await donneesShapefile(shp);
        if (!data?.features?.length) {
            toast.error('Aucune entité dans ce plan.');
            return;
        }
        tableAttributs.value = {
            shp,
            features: data.features || [],
            columns: colonnesShapefile(data.features || []) };
        rechercheAttributs.value = '';
        closeShapefileModal();
    } catch (e) {
        toast.error('Impossible de lire les attributs de ce plan.');
    } finally {
        chargementTableAttributs.value = false;
    }
}

function fermerTableAttributs() {
    tableAttributs.value = null;
    rechercheAttributs.value = '';
}

function choisirLigneAttribut(feature, index) {
    const shp = tableAttributs.value?.shp;
    if (!shp) return;
    selectionnerShapefile(shp, feature, index);
    zoomSurEntite(feature);
}

async function mettreShapefileEnAvant(shp) {
    featuredShapefileId.value = featuredShapefileId.value === shp.id ? null : shp.id;
    selectionnerShapefile(shp);
    closeShapefileModal();
    await chargerShapefiles();
    if (featuredShapefileId.value === shp.id) {
        toast.success(`« ${shp.nom} » est au premier plan`);
    }
}

function scheduleShapefileReload() {
    clearTimeout(shapefileReloadTimer);
    shapefileReloadTimer = setTimeout(() => chargerShapefiles(), SHAPEFILE_RELOAD_DELAY);
}

// ============================================================
// 20. UPLOAD
// ============================================================
function ficheParcelleEnregistree(verification) {
    if (verification?.type !== 'parcelle') return null;
    return {
        txt_num_dossier: '',
        txt_nicad: verification.nicad || '',
        nbr_surface: '',
        txt_num_lotissement: '',
        txt_lotissement: verification.lotissement || '',
        titulaire: '',
        commune: '',
        suffixe: verification.suffixe || '',
        slt_service_rendu: '',
        txt_etat_cession: '' };
}

function memeGeometrie(a, b) {
    if (a === b) return true;
    try {
        return turf.distance(turf.centroid(a), turf.centroid(b), { units: 'meters' }) < 0.4;
    } catch (e) {
        return false;
    }
}

async function limitesSectionAutour(polygone) {
    let zone = null;
    try {
        zone = turf.buffer(polygone, 80, { units: 'meters' });
    } catch (e) {
        zone = null;
    }
    const couches = await lireCouches('limite_section');
    const lignes = [];
    couches.forEach((feature) => {
        let proche = !zone;
        if (zone) {
            try {
                proche = turf.booleanIntersects(zone, feature);
            } catch (e) {
                proche = false;
            }
        }
        if (!proche) return;
        const geometry = feature?.geometry;
        const anneaux = geometry?.type === 'LineString' ? [geometry.coordinates]
            : geometry?.type === 'MultiLineString' ? geometry.coordinates
                : geometry?.type === 'Polygon' ? geometry.coordinates
                    : geometry?.type === 'MultiPolygon' ? geometry.coordinates.flat()
                        : [];
        anneaux.forEach((anneau) => {
            if (anneau.length >= 2) {
                lignes.push(anneau.map(([lng, lat]) => ({ lng: Number(lng), lat: Number(lat) })));
            }
        });
    });
    return lignes;
}

function lotDansParcelle(feature, pointsLot, parcelles) {
    const direct = pointsLot.find((point) => pointDansPolygone(point, feature));
    if (direct) return String(texteAttribut(direct) || '').trim();
    let tampon = null;
    try {
        tampon = turf.buffer(feature, 1.2, { units: 'meters' });
    } catch (e) {
        tampon = null;
    }
    if (!tampon) return '';
    const proche = pointsLot.find((point) => pointDansPolygone(point, tampon)
        && !parcelles.some((autre) => autre !== feature && pointDansPolygone(point, autre)));
    return String(texteAttribut(proche) || '').trim();
}

async function parcellesAutour(polygone, echelleSituation = 2000, porteeMetres = null) {
    const parcelles = await lireCouches('limite_parcelle');
    const pointsLot = await lireCouches('numero_lot');
    const numeros = await lireCouches('numero_parcelle');
    let zone = null;
    try {
        zone = turf.buffer(polygone, porteeMetres || porteeSituation(echelleSituation), { units: 'meters' });
    } catch (e) {
        zone = null;
    }
    const autres = parcelles.filter((item) => estPolygone(item) && !memeGeometrie(polygone, item));
    let limites = null;
    if (zone) {
        try {
            limites = turf.bbox(zone);
        } catch (e) {
            limites = null;
        }
    }
    const proches = autres.filter((item) => {
        if (!limites) return false;
        const boite = boiteFeature(item);
        if (!boite || boite[2] < limites[0] || boite[0] > limites[2] || boite[3] < limites[1] || boite[1] > limites[3]) {
            return false;
        }
        try {
            return turf.booleanIntersects(zone, item);
        } catch (e) {
            return false;
        }
    });
    let tampon = null;
    try {
        tampon = turf.buffer(polygone, 1.5, { units: 'meters' });
    } catch (e) {
        tampon = null;
    }
    const demandes = proches.map((feature) => {
        let touche = false;
        try {
            touche = !!tampon && turf.booleanIntersects(tampon, feature);
        } catch (e) {
            touche = false;
        }
        let surface = 0;
        let interieur = false;
        try {
            surface = Math.round(turf.area(feature));
            interieur = turf.booleanPointInPolygon(turf.centroid(feature), polygone);
        } catch (e) {
            surface = 0;
            interieur = false;
        }
        const pointNumero = numeros.find((point) => pointDansPolygone(point, feature));
        return {
            feature,
            touche,
            lotPoint: lotDansParcelle(feature, pointsLot, parcelles),
            parcelle: chiffresCadastre(texteAttribut(pointNumero), 5),
            surface,
            interieur,
        };
    });
    return demandes.map((demande) => {
        return {
            ring: anneauPrincipal(demande.feature),
            lot: demande.lotPoint,
            parcelle: demande.parcelle,
            surface: demande.surface,
            titre: '',
            touche: demande.touche,
            interieur: demande.interieur,
        };
    }).filter((item) => item.ring.length >= 3);
}

async function polygoneSelectionne() {
    const feature = selectedInfo.value?.feature;
    if (estPolygone(feature)) return feature;
    if (!feature) return null;
    const parcelles = await lireCouches('limite_parcelle');
    return polygoneContenantPoint(feature, parcelles);
}

async function ouvrirExtraitPlan() {
    erreurExtrait.value = '';
    if (!selectedInfo.value?.feature) {
        erreurExtrait.value = 'Cette parcelle n’a pas de géométrie.';
        return;
    }
    planChoisi.value = 'extrait';
    echelleSituationChoisie.value = 2000;
    echelleParcelleChoisie.value = 500;
    echelleUniqueChoisie.value = 1000;
    coordonneesChoisies.value = false;
    choixEchelleOuvert.value = true;
}

async function validerEchelles() {
    choixEchelleOuvert.value = false;
    erreurExtrait.value = '';
    extraitEnCours.value = true;
    try {
        const polygone = await polygoneSelectionne();
        const plan = planChoisi.value;
        const message = blocageExtrait(selectedInfo.value?.data, estPolygone(polygone), plan);
        if (message) {
            erreurExtrait.value = message;
            return;
        }
        const data = selectedInfo.value?.data || {};
        const info = selectedInfo.value?.identification || {};
        const section = [info.nomSection, info.section].filter(Boolean).join(' · ') || selectedInfo.value?.section || '';
        const surfaceSaisie = Number(String(data.nbr_surface ?? '').replace(',', '.'));
        const superficieM2 = Number.isFinite(surfaceSaisie) && surfaceSaisie > 0
            ? surfaceSaisie
            : surfaceM2(polygone);
        const deuxEchelles = plan === 'extrait' || plan === 'morcellement';
        const echelleSituation = Number(echelleSituationChoisie.value) || 2000;
        const echelleParcelle = deuxEchelles
            ? (Number(echelleParcelleChoisie.value) || 500)
            : (Number(echelleUniqueChoisie.value) || 1000);
        const cadreCarte = plan === 'situation'
            ? { w: 186, h: 196 }
            : plan === 'autorisation'
                ? { w: 186, h: 226 }
                : { w: 186, h: 230 };
        const voisins = await parcellesAutour(
            polygone,
            echelleSituation,
            deuxEchelles ? null : porteeCarte(echelleParcelle, cadreCarte),
        );
        const commandes = deuxEchelles ? commandesExtrait({
            bureau: bureauExtrait.nom,
            ville: bureauExtrait.ville,
            infos: {
                region: data.region,
                departement: data.departement,
                arrondissement: data.arrondissement,
                commune: data.commune,
                requerant: data.titulaire,
                titre: data.txt_num_titre,
                titreMere: data.txt_titre_mere,
                lot: data.txt_num_lotissement,
                lotissement: data.txt_lotissement,
                nicad: data.txt_nicad,
                livre: data.livre_foncier,
                requisition: data.requisition,
                section,
                dossier: data.txt_num_dossier,
                anneeDossier: data.dt_date_creation },
            sujet: anneauPrincipal(polygone),
            parcelles: voisins,
            sections: await limitesSectionAutour(polygone),
            superficieM2,
            echelles: {
                situation: echelleSituation,
                parcelle: echelleParcelle,
            },
            variante: plan === 'morcellement' ? 'morcellement' : 'extrait',
            signataire: props.signataireExtrait,
            coordonnees: coordonneesChoisies.value,
        }) : commandesCarte({
            type: plan,
            bureau: bureauExtrait.nom,
            infos: {
                region: data.region,
                departement: data.departement,
                arrondissement: data.arrondissement,
                commune: data.commune,
                requerant: data.titulaire,
                titre: data.txt_num_titre,
                lot: data.txt_num_lotissement,
                nicad: data.txt_nicad,
            },
            sujet: anneauPrincipal(polygone),
            parcelles: voisins,
            superficieM2,
            echelle: echelleParcelle,
        });
        if (!commandes) {
            erreurExtrait.value = 'Cette parcelle n’a pas de géométrie.';
            return;
        }
        extraitOuvert.value = { commandes, fichier: nomFichierExtrait(data, plan) };
    } catch (e) {
        erreurExtrait.value = 'Impossible de préparer le plan.';
    } finally {
        extraitEnCours.value = false;
    }
}

// ============================================================
// 21. ENREGISTREMENT
// ============================================================
async function enregistrerShapefile(messageSucces, verification = null) {
    uploading.value = true;
    const fd = new FormData();
    Object.entries(form.value).forEach(([k, v]) => { if (v) fd.append(k, v); });
    if (verification) fd.append('verification', JSON.stringify(verification));

    try {
        await axios.post('/shapefiles', fd, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        toast.success(messageSucces);

        const { data } = await axios.get('/shapefiles');
        shapefiles.value = data;
        shapefileCache.clear();
        clesNumeroParcelle = null;
        await chargerShapefiles();

        activeTab.value = 'list';
        analyseParcelle.value = null;
        analyseProjet.value = [];
        projetAnalyse.value = false;
        form.value = { nom: '', type_entite: '', shp: null, shx: null, dbf: null, prj: null, srid: '4326' };
        document.querySelectorAll('input[type="file"]').forEach(i => i.value = '');
    } catch (e) {
        const errors = e.response?.data?.errors;
        const first = errors ? Object.values(errors).flat()[0] : null;
        const msg = first || e.response?.data?.message || 'Erreur import';
        toast.error(msg);
    } finally {
        uploading.value = false;
    }
}

// ============================================================
// 21. UPLOAD
// ============================================================
function uploadShapefile() {
    return enregistrerShapefile('Shapefile importé');
}

// ============================================================
// 22. ENREGISTREMENT DE PROJET
// ============================================================
async function enregistrerProjet() {
    if (!projetAnalyse.value) {
        const ok = await analyserProjet();
        if (!ok) return;
    }
    if (!form.value.type_entite) {
        toast.error('Choisissez le type du projet.');
        return;
    }
    await enregistrerShapefile('Projet importé', {
        type: 'projet',
        parcelles: analyseProjet.value.map((ligne) => ({
            section: ligne.section,
            nomSection: ligne.nomSection,
            parcelle: ligne.parcelle,
            lotissement: ligne.base?.txt_lotissement || 'ligne.lotissement' || '',
            suffixe: ligne.suffixe,
            surface: ligne.surface,
            occupee: ligne.occupee,
            part: ligne.part,
            nicad: ligne.base?.terrain?.txt_nicad || ligne.base?.nicad_calcule || '',
            dossier: ligne.base?.dossier?.txt_num_dossier || '' })) });
}

// ============================================================
// 23. OUVERTURE D'ONGLET PLAN
// ============================================================
function ouvrirOngletPlan(onglet) {
    activeTab.value = onglet;
    if (onglet === 'ajouterParcelle') form.value.type_entite = 'limite_parcelle';
}

function chiffresCadastre(valeur, longueur) {
    const chiffres = String(valeur ?? '').replace(/\D/g, '');
    if (!chiffres) return '';
    return chiffres.padStart(longueur, '0').slice(-longueur);
}

function texteAttribut(feature) {
    const props = feature?.properties || {};
    return String(props.TEXT ?? props.text ?? props.Text ?? '').trim();
}

function coucheAttribut(feature) {
    const props = feature?.properties || {};
    return String(props.LAYER ?? props.layer ?? props.Layer ?? '').trim();
}

function estPolygone(feature) {
    return ['Polygon', 'MultiPolygon'].includes(feature?.geometry?.type);
}

function pointsDe(feature) {
    const geometry = feature?.geometry;
    if (!geometry) return [];
    if (geometry.type === 'Point') return [turf.point(geometry.coordinates, feature.properties)];
    if (geometry.type === 'MultiPoint') {
        return geometry.coordinates.map((coordonnee) => turf.point(coordonnee, feature.properties));
    }
    return [];
}

function coordonneesPoint(feature) {
    return pointsDe(feature)[0]?.geometry?.coordinates || null;
}

function pointDansBoite(lon, lat, boite) {
    return !!boite && lon >= boite[0] && lon <= boite[2] && lat >= boite[1] && lat <= boite[3];
}

function pointDansPolygoneCoord(lon, lat, polygonFeature) {
    if (!pointDansBoite(lon, lat, boiteFeature(polygonFeature))) return false;
    try {
        return turf.booleanPointInPolygon(turf.point([lon, lat]), polygonFeature);
    } catch (e) {
        return false;
    }
}

function pointDansPolygone(pointFeature, polygonFeature) {
    const coords = coordonneesPoint(pointFeature);
    return !!coords && pointDansPolygoneCoord(coords[0], coords[1], polygonFeature);
}

function centreGeometrie(feature) {
    const coords = coordonneesPoint(feature);
    if (coords) return coords;
    try {
        return turf.centroid(feature).geometry.coordinates;
    } catch (e) {
        return null;
    }
}

function surfaceM2(feature) {
    try {
        return turf.area(feature);
    } catch (e) {
        return 0;
    }
}

function boitesSeChevauchent(a, b) {
    const boiteA = boiteFeature(a);
    const boiteB = boiteFeature(b);
    if (!boiteA || !boiteB) return false;
    return boiteA[2] >= boiteB[0] && boiteA[0] <= boiteB[2] && boiteA[3] >= boiteB[1] && boiteA[1] <= boiteB[3];
}

function surfaceCommune(a, b) {
    if (!boitesSeChevauchent(a, b)) return 0;
    try {
        const intersection = turf.intersect(turf.featureCollection([a, b]));
        return intersection ? turf.area(intersection) : 0;
    } catch (e) {
        return 0;
    }
}

async function lireCouches(type) {
    const features = [];
    for (const shp of shapefiles.value.filter((item) => item.type_entite === type)) {
        const data = await lireGeojsonShapefile(shp);
        features.push(...(data?.features || []));
    }
    return features;
}

function numeroDans(polygone, points, longueur) {
    const boite = boiteFeature(polygone);
    const centre = centreGeometrie(polygone);
    let choisi = null;
    let distance = Infinity;
    points.forEach((point) => {
        const coords = coordonneesPoint(point);
        if (!coords || !pointDansBoite(coords[0], coords[1], boite)) return;
        if (!pointDansPolygoneCoord(coords[0], coords[1], polygone)) return;
        const ecart = centre
            ? Math.hypot(coords[0] - centre[0], coords[1] - centre[1])
            : 0;
        if (ecart < distance) {
            distance = ecart;
            choisi = point;
        }
    });
    return chiffresCadastre(texteAttribut(choisi), longueur);
}

function sectionContenant(cible, sections) {
    const centre = centreGeometrie(cible);
    if (!centre) return null;
    return sections.find((section) => pointDansPolygoneCoord(centre[0], centre[1], section)) || null;
}

function polygoneContenantPoint(pointFeature, polygones) {
    const coords = coordonneesPoint(pointFeature);
    if (!coords) return null;
    return polygones.find((polygone) => pointDansPolygoneCoord(coords[0], coords[1], polygone)) || null;
}

async function consulterBase(parcelles) {
    const resultats = [];
    for (let index = 0; index < parcelles.length; index += 500) {
        const { data } = await axios.post('/api/plan/parcelles', {
            parcelles: parcelles.slice(index, index + 500),
        });
        resultats.push(...(data.resultats || []));
    }
    return resultats;
}

async function identifierGeometrie(feature, type) {
    const [sections, numerosSection, parcelles, numerosParcelle] = await Promise.all([
        lireCouches('limite_section'),
        lireCouches('numero_section'),
        lireCouches('limite_parcelle'),
        lireCouches('numero_parcelle'),
    ]);

    let polygone = estPolygone(feature) && type !== 'limite_section' ? feature : null;
    let pointNumero = type === 'numero_parcelle' ? feature : null;
    if (!polygone && pointNumero) {
        polygone = polygoneContenantPoint(pointNumero, parcelles);
    }
    if (polygone && !pointNumero) {
        pointNumero = numerosParcelle.find((point) => pointDansPolygone(point, polygone)) || null;
    }

    const ancre = polygone || pointNumero || feature;
    const section = sectionContenant(ancre, sections);
    const numeroSection = section
        ? numeroDans(section, numerosSection, 3)
        : (type === 'numero_section' ? chiffresCadastre(texteAttribut(feature), 3) : '');
    const numeroParcelle = chiffresCadastre(texteAttribut(pointNumero), 5);

    return {
        section: numeroSection,
        parcelle: numeroParcelle,
        lotissement: polygone ? coucheAttribut(polygone) : '',
        nomSection: section ? coucheAttribut(section) : '',
        suffixe: numeroSection && numeroParcelle ? `${numeroSection}${numeroParcelle}` : '',
        sectionFeature: section,
        parcelleFeature: polygone,
        numerosParcelle };
}

function nicadDuPlan(proprietes) {
    const chiffres = String(proprietes?.NICAD ?? '').replace(/\D/g, '');
    return chiffres.length >= 16 ? chiffres.slice(0, 16) : '';
}

function surfaceDuPlan(proprietes) {
    const valeur = Number(String(proprietes?.SupReelle ?? '').replace(',', '.'));
    return Number.isFinite(valeur) && valeur > 0 ? String(Math.round(valeur)) : '';
}

function texteDuPlan(proprietes, cle) {
    return String(proprietes?.[cle] ?? '').trim();
}

async function identifierSelection(shp, feature, jeton) {
    const types = ['limite_section', 'numero_section', 'limite_parcelle', 'numero_parcelle'];
    if (!types.includes(shp?.type_entite)) return;
    try {
        const lecture = await identifierGeometrie(feature, shp.type_entite);
        let base = null;
        if (lecture.section && lecture.parcelle) {
            const resultats = await consulterBase([{
                section: lecture.section,
                parcelle: lecture.parcelle,
                lotissement: lecture.lotissement }]);
            base = resultats[0] || null;
        }
        if (jeton !== jetonSelection) return;
        const terrain = base?.terrain;
        const lieu = lieuDuPlan(terrain);
        const dossier = base?.dossier;
        const sauve = shp?.verification?.type === 'parcelle' ? shp.verification : null;
        const estParcelle = ['limite_parcelle', 'numero_parcelle'].includes(shp?.type_entite) || !!sauve;
        const dossierTrouve = !!terrain;
        const proprietes = lecture.parcelleFeature?.properties || {};
        let lotPlan = '';
        if (!dossierTrouve && lecture.parcelleFeature) {
            const [pointsLot, parcelles] = await Promise.all([
                lireCouches('numero_lot'),
                lireCouches('limite_parcelle'),
            ]);
            if (jeton !== jetonSelection) return;
            lotPlan = lotDansParcelle(lecture.parcelleFeature, pointsLot, parcelles);
        }
        selectedInfo.value = {
            ...selectedInfo.value,
            parcelle: lecture.parcelle || sauve?.prochain || selectedInfo.value.parcelle,
            section: lecture.nomSection
                ? `${lecture.nomSection}${lecture.section ? ' · ' + lecture.section : ''}`
                : (lecture.section || sauve?.section || selectedInfo.value.section),
            loading: false,
            data: estParcelle ? {
                txt_num_dossier: dossier?.txt_num_dossier || '',
                txt_nicad: dossierTrouve
                    ? (terrain.txt_nicad || base?.nicad_calcule || sauve?.nicad || '')
                    : (nicadDuPlan(proprietes) || base?.nicad_calcule || sauve?.nicad || ''),
                nbr_surface: dossierTrouve ? (terrain.nbr_surface || '') : surfaceDuPlan(proprietes),
                txt_num_lotissement: dossierTrouve ? (terrain.txt_num_lotissement || '') : lotPlan,
                txt_lotissement: dossierTrouve
                    ? (terrain.txt_lotissement || lecture.lotissement || sauve?.lotissement || '')
                    : (lecture.lotissement || texteDuPlan(proprietes, 'Lotissemen') || sauve?.lotissement || ''),
                txt_num_titre: terrain?.txt_num_titre || '',
                txt_titre_mere: dossierTrouve ? (terrain.txt_titre_mere || '') : texteDuPlan(proprietes, 'TitreMere'),
                region: lieu.region,
                departement: lieu.departement,
                arrondissement: lieu.arrondissement,
                livre_foncier: terrain?.livre_foncier || '',
                requisition: terrain?.requisition || '',
                titulaire: dossierTrouve ? (terrain.titulaire || '') : texteDuPlan(proprietes, 'TitulaireD'),
                commune: lieu.commune,
                suffixe: lecture.suffixe || base?.suffixe || sauve?.suffixe || '',
                slt_service_rendu: dossier?.slt_service_rendu || '',
                txt_etat_cession: dossier?.txt_etat_cession || '',
                dt_date_creation: dossier?.dt_date_creation || '' } : selectedInfo.value.data,
            identification: {
                chargement: false,
                section: lecture.section,
                parcelle: lecture.parcelle,
                lotissement: lecture.lotissement,
                nomSection: lecture.nomSection,
                suffixe: lecture.suffixe,
                base } };
    } catch (e) {
        if (jeton !== jetonSelection) return;
        selectedInfo.value = {
            ...selectedInfo.value,
            loading: false,
            identification: { chargement: false, erreur: 'Impossible de rattacher le dossier.' } };
    }
}

async function geojsonDuFormulaire() {
    const fd = new FormData();
    ['shp', 'shx', 'dbf', 'prj'].forEach((ext) => {
        if (form.value[ext]) fd.append(ext, form.value[ext]);
    });
    fd.append('srid', form.value.srid || '4326');
    const { data } = await axios.post('/api/plan/previsualiser', fd, {
        headers: { 'Content-Type': 'multipart/form-data' } });
    return normaliserGeojson(data);
}

function valeurNumeroPlan(feature) {
    const valeur = parseInt(chiffresCadastre(texteAttribut(feature), 5), 10);
    return Number.isNaN(valeur) ? 0 : valeur;
}

function dernierNumeroShapefile(section, points) {
    const depuisLesPoints = points.reduce((max, point) => Math.max(max, valeurNumeroPlan(point)), 0);
    const depuisLesParcellesAjoutees = shapefiles.value.reduce((max, shp) => {
        const info = shp.verification;
        if (info?.type !== 'parcelle' || chiffresCadastre(info.section, 3) !== section) return max;
        const valeur = parseInt(chiffresCadastre(info.prochain || info.nicad, 5), 10);
        return Number.isNaN(valeur) ? max : Math.max(max, valeur);
    }, 0);
    return Math.max(depuisLesPoints, depuisLesParcellesAjoutees);
}

function codeCommuneShapefile(section) {
    const info = shapefiles.value
        .map((shp) => shp.verification)
        .find((verification) => verification?.type === 'parcelle'
            && chiffresCadastre(verification.section, 3) === section
            && verification.codeCommune);
    return String(info?.codeCommune || CODE_COMMUNE_NGOR).replace(/\D/g, '').slice(0, 8);
}

async function verifierParcelle() {
    if (!form.value.shp || !form.value.shx || !form.value.dbf) {
        toast.error('Ajoutez les fichiers .shp, .shx et .dbf.');
        return;
    }
    analyseEnCours.value = true;
    analyseParcelle.value = null;
    try {
        const data = await geojsonDuFormulaire();
        const polygones = (data?.features || []).filter(estPolygone);
        if (polygones.length !== 1) {
            analyseParcelle.value = {
                valide: false,
                message: 'Le fichier doit contenir une seule parcelle.' };
            return;
        }

        const nouvelle = polygones[0];
        const existantes = await lireCouches('limite_parcelle');
        const superpositions = existantes
            .map((parcelle) => ({ parcelle, surface: surfaceCommune(nouvelle, parcelle) }))
            .filter((item) => item.surface >= 1);
        const lecture = await identifierGeometrie(nouvelle, 'limite_parcelle');
        const section = lecture.sectionFeature;
        if (!section || !lecture.section) {
            analyseParcelle.value = {
                valide: false,
                message: 'La parcelle ne se trouve dans aucune section du plan.',
                superpositions };
            return;
        }

        const numeros = lecture.numerosParcelle.filter((point) => pointDansPolygone(point, section));
        const dernierNumero = dernierNumeroShapefile(lecture.section, numeros);
        const prochain = String(dernierNumero + 1).padStart(5, '0');
        const suffixe = `${lecture.section}${prochain}`;
        const codeCommune = codeCommuneShapefile(lecture.section);
        const dernierNicad = dernierNumero
            ? `${codeCommune}${lecture.section}${String(dernierNumero).padStart(5, '0')}`
            : '';
        analyseParcelle.value = {
            valide: superpositions.length === 0,
            message: superpositions.length
                ? 'Cette parcelle se superpose à une parcelle déjà présente.'
                : 'La parcelle peut être ajoutée.',
            section: lecture.section,
            nomSection: lecture.nomSection,
            lotissement: coucheAttribut(nouvelle),
            prochain,
            suffixe,
            codeCommune,
            dernierNicad,
            nicad: `${codeCommune}${suffixe}`,
            superpositions: superpositions.map((item) => ({
                nom: coucheAttribut(item.parcelle) || 'Parcelle existante',
                surface: Math.round(item.surface) })) };
    } catch (e) {
        const errors = e.response?.data?.errors;
        const first = errors ? Object.values(errors).flat()[0] : null;
        toast.error(first || 'Impossible de lire la parcelle.');
    } finally {
        analyseEnCours.value = false;
    }
}

async function enregistrerParcelle() {
    if (!analyseParcelle.value?.valide) {
        toast.error('Vérifiez la parcelle avant de l’enregistrer.');
        return;
    }
    const info = analyseParcelle.value;
    form.value.type_entite = 'limite_parcelle';
    await enregistrerShapefile('Parcelle enregistrée', {
        type: 'parcelle',
        section: info.section,
        nomSection: info.nomSection,
        lotissement: info.lotissement,
        prochain: info.prochain,
        suffixe: info.suffixe,
        codeCommune: info.codeCommune,
        nicad: info.nicad });
}

async function analyserProjet() {
    if (!form.value.shp || !form.value.shx || !form.value.dbf) {
        toast.error('Ajoutez les fichiers .shp, .shx et .dbf.');
        return false;
    }
    analyseEnCours.value = true;
    analyseProjet.value = [];
    projetAnalyse.value = false;
    try {
        const data = await geojsonDuFormulaire();
        const projets = (data?.features || []).filter(estPolygone);
        if (!projets.length) {
            toast.error('Le projet ne contient aucun polygone.');
            return false;
        }
        const [sections, numerosSection, parcelles, numerosParcelle] = await Promise.all([
            lireCouches('limite_section'),
            lireCouches('numero_section'),
            lireCouches('limite_parcelle'),
            lireCouches('numero_parcelle'),
        ]);
        const lignes = [];
        for (const projet of projets) {
            for (const parcelle of parcelles) {
                const occupee = surfaceCommune(projet, parcelle);
                if (occupee < 1) continue;
                const section = sectionContenant(parcelle, sections);
                const lecture = {
                    section: section ? numeroDans(section, numerosSection, 3) : '',
                    parcelle: numeroDans(parcelle, numerosParcelle, 5),
                    lotissement: coucheAttribut(parcelle),
                    nomSection: section ? coucheAttribut(section) : '' };
                lecture.suffixe = lecture.section && lecture.parcelle ? `${lecture.section}${lecture.parcelle}` : '';
                const surface = surfaceM2(parcelle);
                const cle = `${lecture.section}-${lecture.parcelle}-${lignes.length}`;
                const existante = lignes.find((ligne) => ligne.section === lecture.section && ligne.parcelle === lecture.parcelle && lecture.parcelle);
                if (existante && lecture.parcelle) {
                    existante.occupee += occupee;
                    continue;
                }
                lignes.push({
                    cle,
                    section: lecture.section,
                    parcelle: lecture.parcelle,
                    lotissement: lecture.lotissement || coucheAttribut(parcelle),
                    nomSection: lecture.nomSection,
                    suffixe: lecture.suffixe,
                    surface: Math.round(surface),
                    occupee,
                    part: surface > 0 ? Math.round((occupee / surface) * 1000) / 10 : 0 });
            }
        }
        lignes.forEach((ligne) => {
            ligne.occupee = Math.round(ligne.occupee);
            ligne.part = ligne.surface > 0 ? Math.round((ligne.occupee / ligne.surface) * 1000) / 10 : 0;
        });
        const consultations = lignes
            .filter((ligne) => ligne.section && ligne.parcelle)
            .map((ligne) => ({ section: ligne.section, parcelle: ligne.parcelle, lotissement: ligne.lotissement }));
        const bases = consultations.length ? await consulterBase(consultations) : [];
        analyseProjet.value = lignes.map((ligne) => ({
            ...ligne,
            base: bases.find((item) => item.section === ligne.section && item.parcelle === ligne.parcelle && (item.lotissement || '') === (ligne.lotissement || '')) || null }));
        if (!lignes.length) toast.info('Aucune parcelle n’est touchée par ce projet.');
        projetAnalyse.value = true;
        return true;
    } catch (e) {
        const errors = e.response?.data?.errors;
        const first = errors ? Object.values(errors).flat()[0] : null;
        toast.error(first || 'Impossible d’analyser le projet.');
        return false;
    } finally {
        analyseEnCours.value = false;
    }
}

// ============================================================
// 21. VISIBILITÉ / SUPPRESSION / ZOOM SHAPEFILE
// ============================================================
async function toggleShapefileVisibility(shp) {
    try {
        await axios.patch(`/shapefiles/${shp.id}`, { visible: !shp.visible });
        shp.visible = !shp.visible;
        await chargerShapefiles();
        toast.info(shp.visible ? `"${shp.nom}" affiché` : `"${shp.nom}" masqué`);
    } catch (e) {
        toast.error('Erreur de mise à jour');
    }
}

function editShapefile(shp) {
    editingShapefile.value = {
        id: shp.id,
        nom: shp.nom || '',
        type_entite: shp.type_entite || '',
        couleur: shp.couleur || '#3388ff' };
}

function cancelShapefileEdit() {
    editingShapefile.value = null;
}

async function saveShapefileEdit() {
    const edition = editingShapefile.value;
    if (!edition?.id || savingShapefile.value) return;

    const nom = edition.nom.trim();
    if (!nom) {
        toast.error('Le nom du plan est obligatoire.');
        return;
    }

    savingShapefile.value = true;
    try {
        const { data } = await axios.patch(`/shapefiles/${edition.id}`, {
            nom,
            type_entite: edition.type_entite || null,
            couleur: edition.couleur || '#3388ff' });

        const index = shapefiles.value.findIndex((item) => item.id === edition.id);
        if (index !== -1) {
            shapefiles.value[index] = { ...shapefiles.value[index], ...data };
        }

        editingShapefile.value = null;
        await chargerShapefiles();
        toast.success('Plan mis à jour');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Impossible d\'enregistrer la modification.');
    } finally {
        savingShapefile.value = false;
    }
}

const nomElementASupprimer = computed(() => {
    const cible = elementASupprimer.value;
    if (!cible?.item) return '';
    if (cible.type === 'point') return cible.item.txt_nomPoint || 'ce point densifié';
    return cible.item.nom || 'ce plan';
});

function demanderSuppression(type, item) {
    if (!item?.id) return;
    elementASupprimer.value = { type, item };
    showDeleteModal.value = true;
}

function retirerElementSupprime(cible) {
    if (cible.type === 'point') {
        pointsDensifies.value = pointsDensifies.value.filter((point) => point.id !== cible.item.id);
        if (selectedPointId.value === cible.item.id) selectedPointId.value = null;
        if (selectedInfo.value?.type === 'point' && selectedInfo.value.point?.id === cible.item.id) {
            selectedInfo.value = null;
            showInfo.value = false;
        }
        addPointsToMap({ ajusterVue: false });
        return;
    }

    if (editingShapefile.value?.id === cible.item.id) editingShapefile.value = null;
    if (featuredShapefileId.value === cible.item.id) featuredShapefileId.value = null;
    if (listeEntites.value?.shp?.id === cible.item.id) listeEntites.value = null;
    if (tableAttributs.value?.shp?.id === cible.item.id) tableAttributs.value = null;
    if (selectedInfo.value?.shapefile?.id === cible.item.id) {
        retirerSurlignageShapefile();
        selectedInfo.value = null;
        showInfo.value = false;
    }
}

async function confirmerSuppression(codes) {
    const cible = elementASupprimer.value;
    if (!cible?.item?.id || loadingDelete.value) return;

    loadingDelete.value = true;
    try {
        if (cible.type === 'point') {
            const { data } = await axios.delete(`/pointDensifier/destroy/${cible.item.id}`, {
                data: { code: codes.adminCode } });
            retirerElementSupprime(cible);
            toast.success(data.message || 'Point densifié supprimé');
        } else {
            await axios.delete(`/shapefiles/${cible.item.id}`, {
                data: { code: codes.adminCode } });
            retirerElementSupprime(cible);
            const { data } = await axios.get('/shapefiles');
            shapefiles.value = data;
            shapefileCache.delete(cible.item.id);
            clesNumeroParcelle = null;
            await chargerShapefiles();
            toast.success('Plan supprimé');
        }

        showDeleteModal.value = false;
        elementASupprimer.value = null;
    } catch (error) {
        const erreur = error.response?.data?.errors;
        const premiere = erreur ? Object.values(erreur).flat()[0] : null;
        toast.error(premiere || error.response?.data?.message || 'Erreur lors de la suppression');
    } finally {
        loadingDelete.value = false;
    }
}

function zoomToStoredBounds(shp) {
    if (!Array.isArray(shp.bbox) || shp.bbox.length !== 4) return false;

    const [minX, minY, maxX, maxY] = shp.bbox.map(Number);
    if ([minX, minY, maxX, maxY].some((value) => Number.isNaN(value))) return false;

    const bounds = L.latLngBounds([minY, minX], [maxY, maxX]);
    if (!bounds.isValid()) return false;

    safeFitBounds(bounds, { padding: [20, 20], maxZoom: 18 });
    return true;
}

async function zoomToShapefile(shp) {
    if (!map.value) return;

    let targetLayer = null;
    shapefilesLayerGroup.value?.eachLayer((layer) => {
        if (layer._shpId === shp.id) targetLayer = layer;
    });

    if (targetLayer && targetLayer.getBounds().isValid()) {
        safeFitBounds(targetLayer.getBounds(), { padding: [20, 20], maxZoom: 18 });
        closeShapefileModal();
        return;
    }

    if (zoomToStoredBounds(shp)) {
        closeShapefileModal();
        return;
    }

    try {
        const data = normaliserGeojson((await axios.get(`/api/shapefiles/${shp.id}/geojson`)).data);
        if (data) shapefileCache.set(shp.id, shapefileCache.get(shp.id) || data);
        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;
        let trouve = false;
        data?.features?.forEach((feature) => {
            const boite = boiteFeature(feature);
            if (!boite) return;
            trouve = true;
            minX = Math.min(minX, boite[0]);
            minY = Math.min(minY, boite[1]);
            maxX = Math.max(maxX, boite[2]);
            maxY = Math.max(maxY, boite[3]);
        });
        if (trouve) {
            safeFitBounds(L.latLngBounds([minY, minX], [maxY, maxX]), { padding: [20, 20], maxZoom: 18 });
            closeShapefileModal();
            return;
        }
    } catch (e) {
        toast.error('Impossible de lire ce plan.');
        return;
    }

    toast.warning('Shapefile masqué ou vide');
}

// ============================================================
// 22. INITIALISATION
// ============================================================
onMounted(() => {
    map.value = L.map('map', {
        preferCanvas: true,
        zoomAnimation: false,
        fadeAnimation: false,
        markerZoomAnimation: false,
        zoomSnap: 0.5,
        zoomDelta: 0.5,
        wheelPxPerZoomLevel: 100,
        inertia: true,
        easeLinearity: 0.25
    }).setView(CENTRE_NGOR_ALMADIES, ZOOM_NGOR_ALMADIES);

    // 🚫 Désactiver le double-clic zoom (source de bugs)
    map.value.doubleClickZoom.disable();

    const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { attribution: '&copy; Google', maxZoom: 24 });
    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 });
    const googleSatellite = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { attribution: '&copy; Google', maxZoom: 24 });
    const googleStreets = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { attribution: '&copy; Google', maxZoom: 22 });
    const openTopoMap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenTopoMap', maxZoom: 17 });

    const baseMaps = {
        'Hybride (Google)': googleHybrid,
        'Rue (OpenStreetMap)': osm,
        'Satellite (Google)': googleSatellite,
        'Routier (Google)': googleStreets,
        'Topographique (OpenTopoMap)': openTopoMap
    };

    googleHybrid.addTo(map.value);
    L.control.layers(baseMaps, null, { position: 'topright' }).addTo(map.value);

    updateScale();
    map.value.on('zoomend moveend', updateScale);

    map.value.on('zoomend moveend', () => {
        updateHistoricVisibility();
        scheduleShapefileReload();
    });
    map.value.on('zoomend', () => {
        chargerParcellesSiZoom();
    });

    map.value.getContainer().style.cursor = 'grab';

    map.value.whenReady(() => {
        map.value.invalidateSize();
        map.value.setView(CENTRE_NGOR_ALMADIES, ZOOM_NGOR_ALMADIES, { animate: false });
        pointsDensifies.value = props.point_densifiers;
        shapefiles.value = props.shapefiles || [];
        requestAnimationFrame(() => {
            try { addPointsToMap(); } catch (error) { console.error(error); }
            try { addHistoricalSites(); } catch (error) { console.error(error); }
            chargerShapefiles();
        });
    });
});

async function chargerParcellesSiZoom() {
    if (!map.value) return;

    if (map.value.getZoom() < ZOOM_THRESHOLD_POINTS && parcelsLayer.value && map.value.hasLayer(parcelsLayer.value)) {
        map.value.removeLayer(parcelsLayer.value);
    }
}

// ============================================================
// 23. NETTOYAGE
// ============================================================
onBeforeUnmount(() => {
    clearTimeout(shapefileReloadTimer);
    if (currentZoomAnimation) clearTimeout(currentZoomAnimation);
    if (map.value) {
        try {
            map.value.off();
            map.value.remove();
        } catch (e) {}
        map.value = null;
    }
    shapefileCache.clear();
    clesNumeroParcelle = null;
    parcelsLayer.value = null;
    selectionHighlightLayer.value = null;
});
</script>

<template>
    <Head title="Plan cadastral - Ngor Almadies" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex w-full min-w-0 flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-primary-txt">
                    <span class="inline-flex items-center justify-center w-10 h-10 bg-blue-600 rounded-xl text-white shadow-md">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </span>
                    Plan cadastral – Ngor Almadies
                </h2>

                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary-txt bg-primary-form rounded-full shadow-sm">
                        <span class="flex items-center justify-center w-6 h-6 text-xs text-white bg-primary rounded-full">📌</span>
                        {{ stats.total }} points densifiés
                    </span>

                    <button @click="openShapefileModal('list')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white transition bg-primary rounded-lg shadow-sm hover:bg-primary-dark">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Gérer les plans
                        <span v-if="shapefiles.length" class="ml-1 px-2 py-0.5 text-xs bg-white/25 rounded-full">
                            {{ shapefiles.length }}
                        </span>
                    </button>

                    <button @click="toggleList"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-primary-txt transition bg-white border border-primary-only rounded-lg shadow-sm hover:bg-primary-form">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        {{ showList ? 'Afficher' : 'Masquer' }} la liste
                    </button>

                    <button @click="recentrerSurNgorAlmadies" type="button"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white transition bg-primary rounded-lg shadow-sm hover:bg-primary-dark">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Ngor Almadies
                    </button>

                    <button @click="getUserLocation" :disabled="locationLoading"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white transition bg-primary rounded-lg shadow-sm hover:bg-primary-dark disabled:opacity-60">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ locationLoading ? 'Chargement…' : 'Ma position' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="p-6">
            <div class="relative overflow-hidden bg-white border border-primary-only rounded-2xl shadow-xl">
                <div id="map" class="w-full h-[700px]"></div>

                <div v-if="currentZoom < ZOOM_THRESHOLD_POINTS"
                    class="absolute top-4 right-4 z-[1000] px-3 py-2 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg shadow">
                    ℹ️ Zoomez à {{ ZOOM_THRESHOLD_POINTS }}+ pour voir les parcelles détaillées
                </div>

                <!-- Recherche de localité -->
                <div class="absolute top-4 left-1/2 z-[1000] w-11/12 max-w-md -translate-x-1/2">
                    <div class="relative">
                        <BaseInput
                            v-model="searchQuery"
                            type="text"
                            placeholder="Rechercher une localité (ex: Ngor, Almadies, Yoff…)"
                            wrapper-class="w-full"
                            @input="searchLocation"
                        >
                            <template #append>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-txt/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </template>
                        </BaseInput>
                        <div v-if="searchResults.length > 0"
                            class="absolute top-full left-0 right-0 mt-2 bg-white/95 backdrop-blur-sm rounded-xl shadow-xl border border-primary-only max-h-60 overflow-y-auto divide-y divide-primary-only">
                            <div v-for="result in searchResults" :key="result.place_id"
                                @click="selectLocation(result)"
                                class="flex items-start gap-3 px-4 py-3 transition cursor-pointer hover:bg-primary-form">
                                <span class="mt-0.5 text-primary">📍</span>
                                <div>
                                    <div class="text-sm font-medium text-primary-txt">{{ result.display_name.split(',').slice(0,3).join(',') }}</div>
                                    <div class="text-xs text-primary-txt capitalize">{{ result.type || 'Localité' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projection -->
                <div class="absolute top-24 left-4 z-[1000]">
                    <div class="flex flex-col items-center gap-1 p-2 bg-white/90 rounded-2xl shadow-lg border border-primary-only backdrop-blur-sm">
                        <span class="text-[10px] font-medium tracking-wider text-primary-txt uppercase">Projection</span>
                        <button @click="convert"
                            class="relative w-14 h-8 rounded-full transition-all duration-300 shadow-inner overflow-hidden"
                            :class="convertToUTM ? 'bg-green-500' : 'bg-primary-only'">
                            <div class="absolute inset-0 bg-white/20 backdrop-blur-sm"></div>
                            <div class="absolute top-1 w-6 h-6 rounded-full bg-white shadow-md transition-all duration-300 flex items-center justify-center text-[10px] font-bold"
                                :class="convertToUTM ? 'left-7' : 'left-1'">
                                {{ convertToUTM ? 'UTM' : 'GPS' }}
                            </div>
                        </button>
                    </div>
                </div>

                <div class="absolute bottom-6 left-1/2 z-[1000] -translate-x-1/2 bg-white/90 backdrop-blur-sm px-5 py-2 rounded-full shadow-xl border border-primary-only text-sm font-medium flex items-center gap-2 select-none">
                    <span class="text-primary-txt">Échelle :</span>
                    <span class="font-mono font-bold text-primary-txt">1 / {{ scaleDenominator.toLocaleString() }}</span>
                </div>

                <!-- Info Parcelle -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-2">
                    <div v-if="(selectedInfo?.type === 'parcelle' && selectedParcelleFeature) || selectedInfo?.type === 'shapefile'"
                        class="absolute top-20 right-6 z-[1000] max-w-sm px-4 py-2 text-sm font-medium text-white shadow-lg"
                        :class="selectedInfo?.type === 'parcelle' ? 'flex items-center gap-2 rounded-full bg-red-600' : 'rounded-2xl'"
                        :style="selectedInfo?.type === 'shapefile' ? { background: selectedInfo.shapefile?.couleur || '#613104' } : undefined">
                        <template v-if="selectedInfo?.type === 'parcelle'">
                            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                            Parcelle {{ selectedInfo.parcelle }} — Section {{ selectedInfo.section }}
                            <button @click="resetParcelleSelection(); selectedInfo = null; showInfo = false"
                                class="ml-1 hover:bg-red-700 rounded-full p-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                                <span class="truncate">{{ selectedInfo.shapefile?.nom }}</span>
                                <span v-if="libelleTypeEntite(selectedInfo.shapefile?.type_entite)" class="shrink-0 text-xs text-white/80">{{ libelleTypeEntite(selectedInfo.shapefile?.type_entite) }}</span>
                                <span class="shrink-0 text-xs text-white/80">{{ selectedInfo.shapefile?.nb_entites }} entité(s)</span>
                                <button type="button" @click="effacerSelectionShapefile"
                                    class="ml-1 rounded-full p-0.5 hover:bg-black/20">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="resumeSelectionShapefile" class="mt-1 text-xs font-normal text-white/90 truncate">
                                {{ resumeSelectionShapefile }}
                            </p>
                        </template>
                    </div>
                </Transition>

                <!-- Liste des entités -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 -translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 -translate-x-4">
                    <div v-if="listeEntites"
                        class="app-sidepanel absolute bottom-6 left-6 z-[1000] w-80 max-h-[60%] backdrop-blur-md overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between gap-2 p-4 border-b border-primary-only bg-gradient-to-r from-primary to-primary-menu rounded-t-3xl">
                            <h3 class="flex items-center gap-2 min-w-0 font-semibold text-white">
                                <span class="flex items-center justify-center w-6 h-6 text-xs bg-white/25 rounded-full">☰</span>
                                <span class="truncate">{{ listeEntites.shp.nom }}</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 text-xs font-bold text-primary bg-primary-form rounded-full shadow-sm">
                                    {{ entitesFiltrees.length }}
                                </span>
                                <button type="button" @click="fermerListeEntites" class="rounded-full p-1 text-white/80 hover:bg-white/20">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 p-3 space-y-1 overflow-y-auto">
                            <BaseInput
                                v-model="rechercheEntites"
                                type="text"
                                placeholder="Filtrer les entités…"
                                wrapper-class="mb-2 w-full"
                            />
                            <div v-if="entitesFiltrees.length === 0" class="py-8 text-sm text-center text-primary-txt/60">
                                Aucune entité trouvée
                            </div>
                            <div v-for="item in entitesFiltrees" :key="item.index"
                                class="point-list-item flex items-start gap-3 p-3 transition-all border border-transparent rounded-xl cursor-pointer hover:bg-primary-form hover:border-primary-only"
                                :class="{ 'active bg-primary-form border-primary-menu shadow-sm': selectedInfo?.type === 'shapefile' && selectedInfo.shapefile?.id === listeEntites.shp.id && selectedInfo.featureIndex === item.index }"
                                @click="choisirEntite(item.feature, item.index)">
                                <span class="mt-0.5 inline-flex min-w-8 items-center justify-center rounded-md bg-primary px-1.5 py-0.5 text-xs font-bold text-white">{{ item.titre }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-primary-txt truncate">{{ item.detail || 'Entité du plan' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Points densifiés -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 -translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 -translate-x-4">
                    <div v-if="!showList && !listeEntites"
                        class="app-sidepanel absolute bottom-6 left-6 z-[1000] w-80 max-h-[60%] backdrop-blur-md overflow-hidden flex flex-col">
                        <div class="flex items-center justify-between p-4 border-b border-primary-only bg-gradient-to-r from-primary to-primary-menu rounded-t-3xl">
                            <h3 class="flex items-center gap-2 font-semibold text-white">
                                <span class="flex items-center justify-center w-6 h-6 text-xs bg-white/25 rounded-full">📍</span>
                                Points densifiés
                            </h3>
                            <span class="px-3 py-1 text-xs font-bold text-primary bg-primary-form rounded-full shadow-sm">
                                {{ filteredPoints.length }}
                            </span>
                        </div>
                        <div class="flex-1 p-3 space-y-1 overflow-y-auto">
                            <BaseInput
                                v-model="searchQueryList"
                                type="text"
                                placeholder="Filtrer les points…"
                                wrapper-class="mb-2 w-full"
                            />
                            <div v-if="filteredPoints.length === 0" class="py-8 text-sm text-center text-primary-txt/60">
                                <span class="block text-3xl mb-2">🔍</span>
                                Aucun point trouvé
                            </div>
                            <div v-for="point in filteredPoints" :key="point.id"
                                :data-point-id="point.id"
                                class="point-list-item flex items-start gap-3 p-3 transition-all border border-transparent rounded-xl cursor-pointer hover:bg-primary-form hover:border-primary-only"
                                :class="{ 'active bg-primary-form border-primary-menu shadow-sm': selectedPointId === point.id }"
                                @click="localiserPoint(point)">
                                <span class="mt-0.5 text-primary">📍</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-primary-txt truncate">{{ point.txt_nomPoint || 'Point' }}</div>
                                    <div class="text-xs text-primary-txt truncate">{{ point.txt_descriptionPoint || 'Aucune description' }}</div>
                                    <div class="flex items-center gap-1 mt-0.5 text-xs text-primary-txt/60 truncate">
                                        <span>{{ point.departement?.slt_departement || '' }}</span>
                                        <span v-if="point.departement">·</span>
                                        <span>{{ point.arrondissement?.slt_arrondissement || '' }}</span>
                                        <span v-if="point.arrondissement">·</span>
                                        <span>{{ point.commune?.slt_commune || '' }}</span>
                                    </div>
                                </div>
                                <span v-if="point.txt_documentsAfferants" class="text-xs text-green-500" title="PDF disponible">📄</span>
                                <BoutonSupprimer @click.stop="demanderSuppression('point', point)" />
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Info -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0 translate-x-4"
                    enter-to-class="opacity-100 translate-x-0"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 translate-x-0"
                    leave-to-class="opacity-0 translate-x-4">
                    <div v-if="showInfo && selectedInfo"
                        class="plan-info app-sidepanel absolute right-6 z-[1200] max-h-[70%] overflow-y-auto p-5"
                        :class="[
                            selectedInfo.type === 'shapefile' || selectedInfo.type === 'parcelle' ? 'w-[26rem]' : 'w-80',
                            tableAttributs ? 'bottom-[48%]' : 'bottom-6',
                        ]">
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="flex items-center gap-2 text-lg font-bold text-primary">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl text-white text-sm bg-primary-menu">
                                    {{ selectedInfo.type === 'point' ? '📍' : selectedInfo.type === 'shapefile' ? '📐' : '🏠' }}
                                </span>
                                <span>{{ selectedInfo.type === 'point' ? 'Point densifié' : selectedInfo.type === 'shapefile' ? 'Shapefile' : 'Parcelle' }}</span>
                            </h3>
                            <button @click="fermerPanneauInfo"
                                class="p-1.5 text-primary-txt/60 transition rounded-xl hover:text-primary hover:bg-primary-form">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="my-3 border-t border-primary-only"></div>

                        <template v-if="selectedInfo.type === 'point'">
                            <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                <span class="font-medium text-primary-txt">Nom</span>
                                <span class="text-primary-txt truncate">{{ selectedInfo.point.txt_nomPoint || '-' }}</span>
                                <span class="font-medium text-primary-txt">Description</span>
                                <span class="text-primary-txt truncate">{{ selectedInfo.point.txt_descriptionPoint || '-' }}</span>
                                <span class="font-medium text-primary-txt">Situation</span>
                                <span class="text-primary-txt truncate">{{ selectedInfo.point.txt_situationPoint || '-' }}</span>
                                <span class="font-medium text-primary-txt">Département</span>
                                <span class="text-primary-txt truncate">{{ selectedInfo.point.departement?.slt_departement || '-' }}</span>
                                <span class="font-medium text-primary-txt">Commune</span>
                                <span class="text-primary-txt truncate">{{ selectedInfo.point.commune?.slt_commune || '-' }}</span>
                                <span class="font-medium text-primary-txt">Est (X)</span>
                                <span class="text-primary-txt">{{ selectedInfo.point.txt_estPoint || '-' }}</span>
                                <span class="font-medium text-primary-txt">Nord (Y)</span>
                                <span class="text-primary-txt">{{ selectedInfo.point.txt_nordPoint || '-' }}</span>
                                <span class="font-medium text-primary-txt">Hauteur (Z)</span>
                                <span class="text-primary-txt">{{ selectedInfo.point.txt_hauteurPoint || '-' }}</span>
                            </div>
                            <div v-if="selectedInfo.point.txt_documentsAfferants" class="mt-3">
                                <a :href="`/storage/${selectedInfo.point.txt_documentsAfferants}`" target="_blank"
                                    class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-dark">
                                    📄 Voir le PDF
                                </a>
                            </div>
                            <div class="mt-4 space-y-2">
                                <button @click="localiserPoint(selectedInfo.point)"
                                        class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-primary rounded-xl shadow-sm hover:bg-primary-dark">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Localiser
                                </button>
                                <button type="button" @click="demanderSuppression('point', selectedInfo.point)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-red-600 rounded-xl shadow-sm hover:bg-red-700">
                                    Supprimer ce point
                                </button>
                            </div>
                        </template>

                        <!-- Shapefile -->
                        <template v-else-if="selectedInfo.type === 'shapefile'">
                            <div class="p-3 mb-3 rounded-xl bg-gradient-to-br from-primary-form to-primary-only/70 border border-primary-only">
                                <div class="grid grid-cols-[7.5rem_1fr] gap-x-3 gap-y-2 text-sm">
                                    <span class="font-medium text-primary">Plan</span>
                                    <span class="font-bold text-primary-txt break-words">{{ selectedInfo.shapefile?.nom || '—' }}</span>
                                    <span class="font-medium text-primary">Type</span>
                                    <span class="font-bold text-primary-txt break-words">{{ libelleTypeEntite(selectedInfo.shapefile?.type_entite) || '—' }}</span>
                                    <span class="font-medium text-primary">Entités</span>
                                    <span class="font-bold text-primary-txt">{{ selectedInfo.shapefile?.nb_entites ?? '—' }}</span>
                                    <span class="font-medium text-primary">Géométrie</span>
                                    <span class="font-bold text-primary-txt">{{ selectedInfo.geometrie || '—' }}</span>
                                </div>
                            </div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-primary">Attributs</p>
                            <div v-if="selectedInfo.attributes?.length" class="space-y-0 text-sm">
                                <div v-for="item in selectedInfo.attributes" :key="item.key" class="grid grid-cols-[7.5rem_1fr] gap-3 py-2 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">
                                        {{ item.label }}
                                        <span class="block text-[10px] font-normal uppercase tracking-wide text-primary-txt/50">{{ item.key }}</span>
                                    </span>
                                    <span class="font-semibold text-primary-txt text-right break-words" :class="{ 'font-normal text-primary-txt/40': !item.value }">
                                        {{ item.value || '—' }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-primary-txt/70">Cliquez une entité du plan, ou ouvrez la table d’attributs.</p>
                            <div v-if="selectedInfo.shapefile?.verification?.type === 'projet'" class="mt-4 space-y-2 text-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-primary">Parcelles enregistrées</p>
                                <p v-if="!selectedInfo.shapefile.verification.parcelles?.length" class="text-primary-txt/70">Aucune parcelle touchée n’a été enregistrée.</p>
                                <div v-for="(ligne, index) in selectedInfo.shapefile.verification.parcelles" :key="index" class="rounded-xl border border-primary-only bg-white p-3">
                                    <p class="font-semibold text-primary-txt">{{ ligne.lotissement || 'Parcelle' }} · {{ ligne.parcelle || '—' }}</p>
                                    <p>Section {{ ligne.nomSection || ligne.section || '—' }}</p>
                                    <p>8 chiffres {{ ligne.suffixe || '—' }} · NICAD {{ ligne.nicad || '—' }}</p>
                                    <p>Occupé {{ ligne.occupee }} m² · {{ ligne.part }} %</p>
                                    <p>Dossier {{ ligne.dossier || '—' }}</p>
                                </div>
                            </div>
                            <!-- Parcelles enregistrées -->
                            <div v-if="selectedInfo.identification" class="mt-4 rounded-xl border border-primary-only bg-white p-3 text-sm">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-primary">Parcelle et dossier</p>
                                <p v-if="selectedInfo.identification.chargement" class="text-primary-txt">Recherche du dossier…</p>
                                <p v-else-if="selectedInfo.identification.erreur" class="text-red-700">{{ selectedInfo.identification.erreur }}</p>
                                <div v-else class="space-y-1.5">
                                    <p><span class="font-medium text-primary">Section :</span> {{ selectedInfo.identification.nomSection || '—' }} · {{ selectedInfo.identification.section || '—' }}</p>
                                    <p><span class="font-medium text-primary">Lotissement :</span> {{ selectedInfo.identification.lotissement || '—' }}</p>
                                    <p><span class="font-medium text-primary">8 derniers chiffres du NICAD :</span> {{ selectedInfo.identification.suffixe || '—' }}</p>
                                    <p><span class="font-medium text-primary">NICAD :</span> {{ selectedInfo.identification.base?.terrain?.txt_nicad || selectedInfo.identification.base?.nicad_calcule || '—' }}</p>
                                    <p><span class="font-medium text-primary">N° dossier :</span> {{ selectedInfo.identification.base?.dossier?.txt_num_dossier || 'Aucun dossier' }}</p>
                                    <p v-if="selectedInfo.identification.base?.dossier"><span class="font-medium text-primary">Service rendu :</span> {{ selectedInfo.identification.base.dossier.slt_service_rendu || '—' }}</p>
                                    <p v-if="selectedInfo.identification.base?.dossier"><span class="font-medium text-primary">Etat de Cession :</span> {{ selectedInfo.identification.base.dossier.txt_etat_cession || '—' }}</p>
                                    <p v-if="selectedInfo.identification.base?.terrain"><span class="font-medium text-primary">Titulaire :</span> {{ selectedInfo.identification.base.terrain.titulaire || '—' }}</p>
                                    <p v-if="selectedInfo.identification.base?.terrain"><span class="font-medium text-primary">Surface :</span> {{ selectedInfo.identification.base.terrain.nbr_surface || '—' }} m²</p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                                <button v-if="selectedInfo.feature" type="button" @click="zoomSurEntite(selectedInfo.feature)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-primary rounded-xl shadow-sm hover:bg-primary-dark">
                                    Zoomer sur l’entité
                                </button>
                                <button type="button" @click="ouvrirListeEntites(selectedInfo.shapefile)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-primary rounded-xl shadow-sm hover:bg-primary-dark">
                                    Toutes les entités
                                </button>
                                <button type="button" @click="ouvrirTableAttributs(selectedInfo.shapefile)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-primary transition bg-white border border-primary-only rounded-xl hover:bg-primary-form">
                                    Table d’attributs
                                </button>
                                <button type="button" @click="demanderSuppression('shapefile', selectedInfo.shapefile)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-red-600 rounded-xl shadow-sm hover:bg-red-700">
                                    Supprimer ce plan
                                </button>
                            </div>
                        </template>

                        <!-- Parcelle -->
                        <template v-else>
                            <div class="p-3 mb-3 rounded-xl bg-gradient-to-br from-primary-form to-primary-only/70 border border-primary-only">
                                <div class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                    <span class="font-medium text-primary">Parcelle</span>
                                    <span class="font-bold text-primary-txt">{{ selectedInfo.parcelle || '—' }}</span>
                                    <span class="font-medium text-primary">Section</span>
                                    <span class="font-bold text-primary-txt">{{ selectedInfo.section || 'Non définie' }}</span>
                                </div>
                            </div>

                            <div v-if="selectedInfo.loading" class="flex items-center justify-center gap-2 py-6 text-primary">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm font-medium">Chargement…</span>
                            </div>

                            <div v-else-if="selectedInfo.data" class="space-y-2 text-sm">
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">8 derniers chiffres</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.suffixe || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">N° Dossier</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.txt_num_dossier || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">NICAD</span>
                                    <span class="font-semibold text-primary-txt text-right break-all">{{ selectedInfo.data.txt_nicad || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Surface</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.nbr_surface || '—' }} m²</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">N° Lot</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.txt_num_lotissement || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Lotissement</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.txt_lotissement || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Titulaire</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.titulaire || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Région</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.region || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Département</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.departement || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Arrondissement</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.arrondissement || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Commune</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.commune || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-primary-only/70">
                                    <span class="font-medium text-primary-txt">Service rendu</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.slt_service_rendu || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 py-1.5">
                                    <span class="font-medium text-primary-txt">Etat de Cession</span>
                                    <span class="font-semibold text-primary-txt text-right">{{ selectedInfo.data.txt_etat_cession || '—' }}</span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-primary-txt/70">Aucune information enregistrée pour cette parcelle.</p>
                            <div class="mt-4 space-y-2">
                                <button type="button" @click="ouvrirExtraitPlan" :disabled="extraitEnCours || selectedInfo.loading"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-primary rounded-xl shadow-sm hover:bg-primary-dark disabled:opacity-60">
                                    {{ extraitEnCours ? 'Préparation du plan…' : 'Générer un plan' }}
                                </button>
                                <p v-if="erreurExtrait" class="text-sm text-red-700">{{ erreurExtrait }}</p>
                                <button v-if="selectedInfo.feature" type="button" @click="zoomSurEntite(selectedInfo.feature)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-primary rounded-xl shadow-sm hover:bg-primary-dark">
                                    Zoomer sur la parcelle
                                </button>
                                <button v-if="selectedInfo.shapefile" type="button" @click="demanderSuppression('shapefile', selectedInfo.shapefile)"
                                    class="flex items-center justify-center w-full gap-2 py-2.5 text-sm font-medium text-white transition bg-red-600 rounded-xl shadow-sm hover:bg-red-700">
                                    Supprimer ce plan
                                </button>
                            </div>
                        </template>
                    </div>
                </Transition>

                <!-- Table d'attributs -->
                <div v-if="tableAttributs"
                    class="shp-table absolute inset-x-0 bottom-0 z-[1100] flex h-[46%] flex-col border-t border-primary-only bg-[#fff9f4] shadow-[0_-12px_30px_rgba(97,49,4,0.16)]">
                    <div class="flex items-center gap-3 border-b border-primary-only px-4 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-primary">Table d’attributs — {{ tableAttributs.shp.nom }}</p>
                            <p class="text-xs text-primary-txt/70">{{ lignesTableAttributs.length }} / {{ tableAttributs.features.length }} entité(s)</p>
                        </div>
                        <input
                            v-model="rechercheAttributs"
                            type="search"
                            placeholder="Rechercher un attribut…"
                            class="w-56 rounded-lg border border-primary-only bg-white px-3 py-1.5 text-sm text-primary-txt outline-none focus:border-primary"
                        >
                        <button type="button" @click="fermerTableAttributs"
                            class="rounded-lg p-1.5 text-primary-txt/60 hover:bg-primary-form hover:text-primary">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-primary-form text-primary">
                                <tr>
                                    <th class="whitespace-nowrap border-b border-primary-only px-3 py-2 font-semibold">#</th>
                                    <th v-for="colonne in tableAttributs.columns" :key="colonne" class="whitespace-nowrap border-b border-primary-only px-3 py-2 font-semibold">
                                        {{ libelleChamp(colonne) }}
                                        <span class="block text-[10px] font-normal uppercase tracking-wide text-primary-txt/50">{{ colonne }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!lignesTableAttributs.length">
                                    <td :colspan="tableAttributs.columns.length + 1" class="px-3 py-6 text-center text-primary-txt/60">Aucune entité ne correspond à la recherche.</td>
                                </tr>
                                <tr v-for="ligne in lignesTableAttributs" :key="ligne.index"
                                    class="cursor-pointer border-b border-primary-only/60 hover:bg-primary-form"
                                    :class="{ 'bg-[#ffe5c5]': selectedInfo?.type === 'shapefile' && selectedInfo.shapefile?.id === tableAttributs.shp.id && selectedInfo.featureIndex === ligne.index }"
                                    @click="choisirLigneAttribut(ligne.feature, ligne.index)">
                                    <td class="whitespace-nowrap px-3 py-2 font-medium text-primary-txt/70">{{ ligne.index + 1 }}</td>
                                    <td v-for="colonne in tableAttributs.columns" :key="colonne" class="max-w-[16rem] px-3 py-2 text-primary-txt">
                                        <span class="block truncate" :class="{ 'text-primary-txt/35': !valeurAttribut(ligne.feature, colonne) }" :title="valeurAttribut(ligne.feature, colonne)">
                                            {{ valeurAttribut(ligne.feature, colonne) || '—' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="showShapefileModal"
                    class="fixed inset-0 z-[2000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                    @click.self="closeShapefileModal">

                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95">
                        <div v-if="showShapefileModal"
                            class="w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col">

                            <div class="flex items-center justify-between px-6 py-4 border-b border-primary-only bg-gradient-to-r from-primary-form to-primary-footer">
                                <div class="flex items-center gap-3">
                                    <span class="flex items-center justify-center w-10 h-10 text-white bg-primary rounded-xl shadow-md">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                        </svg>
                                    </span>
                                    <div>
                                        <h3 class="text-lg font-bold text-primary-txt">Gestion des plans</h3>
                                        <p class="text-xs text-primary-txt">Importer et gérer vos shapefiles</p>
                                    </div>
                                </div>
                                <button @click="closeShapefileModal"
                                    class="text-primary-txt/60 transition rounded-lg hover:text-primary hover:bg-primary-form p-1.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-2 border-b border-primary-only bg-gradient-to-b from-primary-form to-[#fff9f4] p-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    @click="ouvrirOngletPlan('list')"
                                    class="flex min-h-11 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold leading-tight transition duration-200"
                                    :class="activeTab === 'list'
                                        ? 'bg-primary text-white shadow-md'
                                        : 'border border-primary-only/80 bg-white/80 text-primary-txt hover:border-primary-menu hover:bg-white'"
                                >
                                    <span>Plans importés</span>
                                    <span
                                        class="inline-flex min-w-6 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-bold"
                                        :class="activeTab === 'list' ? 'bg-white/20 text-white' : 'bg-primary-form text-primary'"
                                    >
                                        {{ shapefiles.length }}
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    @click="ouvrirOngletPlan('upload')"
                                    class="min-h-11 rounded-xl px-3 py-2.5 text-sm font-semibold leading-tight transition duration-200"
                                    :class="activeTab === 'upload'
                                        ? 'bg-primary text-white shadow-md'
                                        : 'border border-primary-only/80 bg-white/80 text-primary-txt hover:border-primary-menu hover:bg-white'"
                                >
                                    Importer un shapefile
                                </button>
                                <button
                                    type="button"
                                    @click="ouvrirOngletPlan('ajouterParcelle')"
                                    class="min-h-11 rounded-xl px-3 py-2.5 text-sm font-semibold leading-tight transition duration-200"
                                    :class="activeTab === 'ajouterParcelle'
                                        ? 'bg-primary text-white shadow-md'
                                        : 'border border-primary-only/80 bg-white/80 text-primary-txt hover:border-primary-menu hover:bg-white'"
                                >
                                    Ajouter une parcelle
                                </button>
                                <button
                                    type="button"
                                    @click="ouvrirOngletPlan('uploadProjet')"
                                    class="min-h-11 rounded-xl px-3 py-2.5 text-sm font-semibold leading-tight transition duration-200"
                                    :class="activeTab === 'uploadProjet'
                                        ? 'bg-primary text-white shadow-md'
                                        : 'border border-primary-only/80 bg-white/80 text-primary-txt hover:border-primary-menu hover:bg-white'"
                                >
                                    Ajouter un projet
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-6">
                                <div v-if="activeTab === 'list'" class="space-y-3">
                                    <form v-if="editingShapefile" class="p-4 border border-primary-only rounded-xl bg-primary-form space-y-3" @submit.prevent="saveShapefileEdit">
                                        <p class="text-sm font-semibold text-primary-txt">Modifier le plan</p>
                                        <BaseInput
                                            v-model="editingShapefile.nom"
                                            label="Nom du plan"
                                            name="edit-nom"
                                            required
                                            type="text"
                                            wrapper-class="w-full"
                                        />
                                        <BaseSelect
                                            v-model="editingShapefile.type_entite"
                                            label="Type d'entité"
                                            name="edit-type"
                                            :options="optionsTypeEntite"
                                            wrapper-class="w-full"
                                        />
                                        <label class="block text-sm font-medium text-primary-txt">
                                            Couleur
                                            <input v-model="editingShapefile.couleur" type="color" class="mt-2 h-10 w-16 cursor-pointer rounded-lg border border-primary-only bg-white p-1">
                                        </label>
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="cancelShapefileEdit" class="px-4 py-2 text-sm font-medium text-primary-txt bg-white border border-primary-only rounded-lg hover:bg-white">Annuler</button>
                                            <button type="submit" :disabled="savingShapefile" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark disabled:opacity-60">
                                                {{ savingShapefile ? 'Enregistrement…' : 'Enregistrer' }}
                                            </button>
                                        </div>
                                    </form>
                                    <div v-if="!shapefiles.length" class="flex flex-col items-center justify-center py-12 text-primary-txt/60">
                                        <p class="text-sm font-medium">Aucun plan importé</p>
                                        <button @click="activeTab = 'upload'"
                                            class="mt-4 inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark">
                                            ⬆️ Importer maintenant
                                        </button>
                                    </div>

                                    <div v-for="shp in shapefilesTries" :key="shp.id"
                                        class="flex items-center gap-3 p-3 bg-white border border-primary-only rounded-xl hover:border-primary hover:shadow-sm transition group">
                                        <span class="flex-shrink-0 w-4 h-4 rounded-full ring-2 ring-white shadow"
                                            :style="{ background: estCoucheDoublon(shp) ? '#b91c1c' : (shp.couleur || '#3388ff') }"></span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-semibold text-primary-txt truncate">{{ shp.nom }}</p>
                                                <span v-if="!shp.visible" class="px-2 py-0.5 text-[10px] font-medium text-primary-txt bg-primary-form rounded-full">Masqué</span>
                                            </div>
                                            <p class="text-xs text-primary-txt mt-0.5">
                                                <span v-if="libelleTypeEntite(shp.type_entite)">{{ libelleTypeEntite(shp.type_entite) }} · </span>{{ shp.nb_entites }} entité(s) · SRID {{ shp.srid }}
                                                <span v-if="shp.verification?.type === 'parcelle'"> · NICAD {{ shp.verification.nicad || shp.verification.suffixe }}</span>
                                                <span v-else-if="shp.verification?.type === 'projet'"> · {{ shp.verification.parcelles?.length || 0 }} parcelle(s) du projet</span>
                                                <span v-else-if="estCoucheDoublon(shp)"> · NICAD en doublon</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1 opacity-70 group-hover:opacity-100 transition">
                                            <button @click="toggleShapefileVisibility(shp)" class="p-2 rounded-lg transition"
                                                :class="shp.visible ? 'text-primary hover:bg-primary-form' : 'text-primary-txt/60 hover:bg-primary-form'">
                                                👁
                                            </button>
                                            <button type="button" @click="mettreShapefileEnAvant(shp)" class="p-2 rounded-lg transition" :class="featuredShapefileId === shp.id ? 'bg-primary text-white' : 'text-primary hover:bg-primary-form'" title="Mettre en avant">⬆</button>
                                            <button type="button" @click="ouvrirListeEntites(shp)" class="p-2 text-primary rounded-lg hover:bg-primary-form transition" title="Toutes les entités">☰</button>
                                            <button type="button" @click="selectionnerShapefile(shp); closeShapefileModal()" class="p-2 text-primary rounded-lg hover:bg-primary-form transition" title="Informations">ℹ</button>
                                            <BoutonModifier @click="editShapefile(shp)" />
                                            <button type="button" @click="zoomToShapefile(shp)" class="p-2 text-primary rounded-lg hover:bg-primary-form transition" title="Zoomer">🔍</button>
                                            <BoutonSupprimer @click="demanderSuppression('shapefile', shp)" />
                                            <button @click="downloadShapefile(shp)" class="p-2 text-primary rounded-lg hover:bg-primary-form transition">💾</button>
                                        </div>
                                    </div>
                                </div>

                                <form v-else-if="activeTab === 'upload'" @submit.prevent="uploadShapefile" class="space-y-5">
                                    <BaseSelect
                                        v-model="form.type_entite"
                                        label="Type d'entité"
                                        name="type_entite"
                                        :options="optionsTypeEntite"
                                        required
                                        wrapper-class="w-full"
                                    />

                                    <BaseInput
                                        v-model="form.nom"
                                        label="Nom Entité"
                                        name="nom"
                                        required
                                        type="text"
                                        placeholder="Ex : Entité "
                                        wrapper-class="w-full"
                                    />
                                    
                                    <BaseSelect
                                        v-model="form.srid"
                                        label="Projection"
                                        name="srid"
                                        :options="[
                                            { label: 'WGS84 — EPSG:4326', value: '4326' },
                                            { label: 'UTM 28N — EPSG:32628', value: '32628' },
                                            { label: 'Lambert 93 — EPSG:2154', value: '2154' },
                                        ]"
                                        wrapper-class="w-full"
                                    />
                                    <div class="p-4 border border-primary-only rounded-xl bg-white space-y-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <BaseInput
                                                v-for="ext in ['shp', 'shx', 'dbf', 'prj']"
                                                :key="ext"
                                                :label="'.' + ext"
                                                type="file"
                                                :name="ext"
                                                :accept="'.' + ext"
                                                :required="ext !== 'prj'"
                                                wrapper-class="w-full"
                                                @change="e => form[ext] = e.target.files?.[0] || null"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-primary-only">
                                        <button type="button" @click="closeShapefileModal"
                                            class="px-4 py-2 text-sm font-medium text-primary-txt bg-white border border-primary-only rounded-lg hover:bg-primary-form">Annuler</button>
                                        <button type="submit" :disabled="uploading"
                                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark disabled:opacity-60">
                                            {{ uploading ? 'Import…' : 'Importer' }}
                                        </button>
                                    </div>
                                </form>

                                <form v-else-if="activeTab === 'ajouterParcelle'" @submit.prevent="verifierParcelle" class="space-y-5">
                                    <p class="text-sm text-primary-txt">Le fichier contient une seule parcelle. La vérification contrôle la section, la superposition et propose le numéro suivant.</p>
                                    <BaseInput
                                        v-model="form.nom"
                                        label="Nom de la parcelle"
                                        name="nom_parcelle"
                                        required
                                        type="text"
                                        placeholder="Ex : Parcelle nouvelle"
                                        wrapper-class="w-full"
                                    />
                                    <BaseSelect
                                        v-model="form.srid"
                                        label="Projection"
                                        name="srid_parcelle"
                                        :options="[
                                            { label: 'WGS84 — EPSG:4326', value: '4326' },
                                            { label: 'UTM 28N — EPSG:32628', value: '32628' },
                                            { label: 'Lambert 93 — EPSG:2154', value: '2154' },
                                        ]"
                                        wrapper-class="w-full"
                                    />
                                    <div class="p-4 border border-primary-only rounded-xl bg-white space-y-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <BaseInput
                                                v-for="ext in ['shp', 'shx', 'dbf', 'prj']"
                                                :key="'parcelle-' + ext"
                                                :label="'.' + ext"
                                                type="file"
                                                :name="'parcelle_' + ext"
                                                :accept="'.' + ext"
                                                :required="ext !== 'prj'"
                                                wrapper-class="w-full"
                                                @change="e => form[ext] = e.target.files?.[0] || null"
                                            />
                                        </div>
                                    </div>
                                    <div v-if="analyseParcelle" class="rounded-xl border p-4 text-sm" :class="analyseParcelle.valide ? 'border-green-300 bg-green-50 text-green-900' : 'border-red-300 bg-red-50 text-red-900'">
                                        <p class="font-semibold">{{ analyseParcelle.message }}</p>
                                        <div v-if="analyseParcelle.section" class="mt-2 space-y-1">
                                            <p>Section {{ analyseParcelle.nomSection || analyseParcelle.section }} · n° {{ analyseParcelle.section }}</p>
                                            <p>Lotissement : {{ analyseParcelle.lotissement || '—' }}</p>
                                            <p>Dernier NICAD : {{ analyseParcelle.dernierNicad || '—' }}</p>
                                            <p>Prochain numéro : {{ analyseParcelle.prochain }}</p>
                                            <p>8 derniers chiffres : {{ analyseParcelle.suffixe }}</p>
                                            <p>Nouveau NICAD : {{ analyseParcelle.nicad || 'Code commune introuvable pour cette section' }}</p>
                                        </div>
                                        <ul v-if="analyseParcelle.superpositions?.length" class="mt-2 list-disc pl-5">
                                            <li v-for="(item, index) in analyseParcelle.superpositions" :key="index">{{ item.nom }} · {{ item.surface }} m²</li>
                                        </ul>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-primary-only">
                                        <button type="button" @click="closeShapefileModal" class="px-4 py-2 text-sm font-medium text-primary-txt bg-white border border-primary-only rounded-lg hover:bg-primary-form">Annuler</button>
                                        <button type="submit" :disabled="analyseEnCours" class="px-4 py-2 text-sm font-medium text-primary bg-white border border-primary-only rounded-lg hover:bg-primary-form disabled:opacity-60">
                                            {{ analyseEnCours ? 'Vérification…' : 'Vérifier' }}
                                        </button>
                                        <button type="button" @click="enregistrerParcelle" :disabled="!analyseParcelle?.valide || uploading" class="px-5 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark disabled:opacity-60">
                                            {{ uploading ? 'Import…' : 'Enregistrer' }}
                                        </button>
                                    </div>
                                </form>

                                <form v-else-if="activeTab === 'uploadProjet'" @submit.prevent="enregistrerProjet" class="space-y-5">
                                    <BaseSelect
                                        v-model="form.type_entite"
                                        label="Type de projet"
                                        name="type_entite_projet"
                                        :options="optionsTypeEntite"
                                        required
                                        wrapper-class="w-full"
                                    />

                                    <BaseInput
                                        v-model="form.nom"
                                        label="Nom Entité Projet"
                                        name="nom_projet"
                                        required
                                        type="text"
                                        placeholder="Ex : Projet"
                                        wrapper-class="w-full"
                                    />

                                    <BaseSelect
                                        v-model="form.srid"
                                        label="Projection"
                                        name="srid_projet"
                                        :options="[
                                            { label: 'WGS84 — EPSG:4326', value: '4326' },
                                            { label: 'UTM 28N — EPSG:32628', value: '32628' },
                                            { label: 'Lambert 93 — EPSG:2154', value: '2154' },
                                        ]"
                                        wrapper-class="w-full"
                                    />
                                    <div class="p-4 border border-primary-only rounded-xl bg-white space-y-3">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <BaseInput
                                                v-for="ext in ['shp', 'shx', 'dbf', 'prj']"
                                                :key="'projet-' + ext"
                                                :label="'.' + ext"
                                                type="file"
                                                :name="'projet_' + ext"
                                                :accept="'.' + ext"
                                                :required="ext !== 'prj'"
                                                wrapper-class="w-full"
                                                @change="e => form[ext] = e.target.files?.[0] || null"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-3">
                                        <button type="button" @click="analyserProjet" :disabled="analyseEnCours" class="px-4 py-2 text-sm font-medium text-primary bg-white border border-primary-only rounded-lg hover:bg-primary-form disabled:opacity-60">
                                            {{ analyseEnCours ? 'Analyse…' : 'Analyser les parcelles' }}
                                        </button>
                                    </div>
                                    <div v-if="analyseProjet.length" class="overflow-x-auto rounded-xl border border-primary-only">
                                        <table class="w-full text-left text-sm">
                                            <thead class="bg-primary-form text-primary">
                                                <tr>
                                                    <th class="px-3 py-2 font-semibold">Section</th>
                                                    <th class="px-3 py-2 font-semibold">Parcelle</th>
                                                    <th class="px-3 py-2 font-semibold">8 chiffres</th>
                                                    <th class="px-3 py-2 font-semibold">Occupé</th>
                                                    <th class="px-3 py-2 font-semibold">Part</th>
                                                    <th class="px-3 py-2 font-semibold">Dossier</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="ligne in analyseProjet" :key="ligne.cle" class="border-t border-primary-only/70">
                                                    <td class="px-3 py-2">{{ ligne.nomSection || ligne.section || '—' }}</td>
                                                    <td class="px-3 py-2">{{ ligne.parcelle || '—' }}</td>
                                                    <td class="px-3 py-2">{{ ligne.suffixe || '—' }}</td>
                                                    <td class="px-3 py-2">{{ ligne.occupee }} m²</td>
                                                    <td class="px-3 py-2">{{ ligne.part }} %</td>
                                                    <td class="px-3 py-2">{{ ligne.base?.dossier?.txt_num_dossier || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-primary-only">
                                        <button type="button" @click="closeShapefileModal"
                                            class="px-4 py-2 text-sm font-medium text-primary-txt bg-white border border-primary-only rounded-lg hover:bg-primary-form">Annuler</button>
                                        <button type="submit" :disabled="uploading"
                                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark disabled:opacity-60">
                                            {{ uploading ? 'Import…' : 'Enregistrer le projet' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </div>

        <div v-if="choixEchelleOuvert" class="fixed inset-0 z-[3000] flex items-center justify-center bg-black/55 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-primary-txt">Choisir le plan</h2>
                <p class="mt-1 text-sm text-primary-txt/70">Le plan d’abord, puis l’échelle. L’aperçu n’enregistre rien.</p>
                <fieldset class="mt-5">
                    <legend class="text-sm font-semibold text-primary-txt">Plan</legend>
                    <div class="mt-2 space-y-2">
                        <label v-for="plan in plansDisponibles" :key="plan.id" class="flex items-center gap-2 text-sm text-primary-txt">
                            <input v-model="planChoisi" type="radio" name="plan-choisi" :value="plan.id" class="text-primary">
                            {{ plan.label }}
                        </label>
                    </div>
                </fieldset>
                <label v-if="planChoisi === 'extrait'" class="mt-5 flex items-center gap-2 text-sm text-primary-txt">
                    <input v-model="coordonneesChoisies" type="checkbox" class="text-primary">
                    Afficher les coordonnées des bornes
                </label>
                <fieldset v-if="planChoisi === 'extrait' || planChoisi === 'morcellement'" class="mt-5">
                    <legend class="text-sm font-semibold text-primary-txt">Échelle pour plan de situation</legend>
                    <div class="mt-2 space-y-2">
                        <label v-for="valeur in [2000, 5000, 10000]" :key="`situation-${valeur}`" class="flex items-center gap-2 text-sm text-primary-txt">
                            <input v-model="echelleSituationChoisie" type="radio" name="echelle-situation" :value="valeur" class="text-primary">
                            1/{{ valeur }}
                        </label>
                    </div>
                </fieldset>
                <fieldset v-if="planChoisi === 'extrait' || planChoisi === 'morcellement'" class="mt-5">
                    <legend class="text-sm font-semibold text-primary-txt">Échelle pour plan parcelle</legend>
                    <div class="mt-2 space-y-2">
                        <label v-for="valeur in [200, 500, 1000]" :key="`parcelle-${valeur}`" class="flex items-center gap-2 text-sm text-primary-txt">
                            <input v-model="echelleParcelleChoisie" type="radio" name="echelle-parcelle" :value="valeur" class="text-primary">
                            1/{{ valeur }}
                        </label>
                    </div>
                </fieldset>
                <fieldset v-else class="mt-5">
                    <legend class="text-sm font-semibold text-primary-txt">Échelle</legend>
                    <div class="mt-2 space-y-2">
                        <label v-for="valeur in [500, 1000, 2000]" :key="`unique-${valeur}`" class="flex items-center gap-2 text-sm text-primary-txt">
                            <input v-model="echelleUniqueChoisie" type="radio" name="echelle-unique" :value="valeur" class="text-primary">
                            1/{{ valeur }}
                        </label>
                    </div>
                </fieldset>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-primary-txt bg-white border border-primary-only rounded-lg hover:bg-primary-form" @click="choixEchelleOuvert = false">Annuler</button>
                    <button type="button" class="px-5 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark" @click="validerEchelles">Valider</button>
                </div>
            </div>
        </div>

        <ExtraitPlanApercu
            v-if="extraitOuvert"
            :commandes="extraitOuvert.commandes"
            :fichier="extraitOuvert.fichier"
            @fermer="extraitOuvert = null"
        />

        <DeleteModal
            :show="showDeleteModal"
            :itemName="nomElementASupprimer"
            :loading="loadingDelete"
            @update:show="showDeleteModal = $event"
            @confirm="confirmerSuppression"
        />
    </AuthenticatedLayout>
</template>

<style>
.shp-chiffre {
    background: transparent !important;
    border: none !important;
}
.shp-chiffre span {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #111111;
    background: transparent;
    border: none;
    font-family: Arial, Helvetica, sans-serif;
    font-weight: 500;
    font-size: 12px;
    line-height: 1;
}
.shp-numero {
    background: transparent !important;
    border: none !important;
}
.shp-numero span {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 22px;
    padding: 0 4px;
    background: #ffffff;
    border: 2px solid #1c0a00;
    border-radius: 6px;
    color: #1c0a00;
    font-weight: 800;
    font-size: 13px;
    line-height: 1;
    box-shadow: 0 0 0 2px #ffffff, 0 2px 6px rgba(0, 0, 0, 0.55);
}
.shp-numero span.is-active {
    background: #7f1d1d;
    color: #ffffff;
    border-color: #1c0a00;
}

.shp-etiquette {
    background: rgba(255, 249, 244, 0.94) !important;
    border: 1px solid #613104 !important;
    color: #613104 !important;
    font-weight: 700;
    font-size: 12px;
    line-height: 1.2;
    padding: 1px 5px !important;
    border-radius: 4px !important;
    box-shadow: none !important;
}
.shp-etiquette::before {
    display: none !important;
}

.leaflet-popup-content-wrapper {
    border-radius: 1.25rem !important;
    background: #fff9f4 !important;
    color: #643407 !important;
    border: 1px solid #e6c5a2 !important;
    box-shadow: 0 16px 40px rgba(97, 49, 4, 0.16) !important;
}
.leaflet-popup-content {
    margin: 12px !important;
    font-size: 13px;
    line-height: 1.5;
    color: #643407 !important;
}
.leaflet-popup-content h3,
.leaflet-popup-content h4,
.leaflet-popup-content a {
    color: #613104 !important;
}
.leaflet-popup-tip { background: #fff9f4 !important; }

.plan-info.app-sidepanel {
    background: linear-gradient(180deg, #fff9f4 0%, #faefe6 100%);
    border: 1px solid #e6c5a2;
    border-radius: 1.5rem;
    box-shadow: 0 16px 40px rgba(97, 49, 4, 0.16);
    color: #643407;
}

.leaflet-container { cursor: grab; background: #faefe6; }
.leaflet-container:active { cursor: grabbing; }
.leaflet-interactive { cursor: pointer; }

.user-marker { animation: pulse-blue 1.5s ease-in-out infinite; }
@keyframes pulse-blue {
    0%, 100% { transform: scale(1); box-shadow: 0 0 20px rgba(37, 99, 235, 0.6); }
    50% { transform: scale(1.1); box-shadow: 0 0 30px rgba(37, 99, 235, 0.8); }
}

.point-list-item.active {
    background-color: #ffe5c5 !important;
    border-color: #c7812e !important;
}

@media (max-width: 640px) {
    .absolute:not(.shp-table) {
        position: relative !important;
        left: auto !important;
        right: auto !important;
        bottom: auto !important;
        top: auto !important;
        transform: none !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
        z-index: 10 !important;
    }
    .relative { display: flex; flex-direction: column; gap: 0.5rem; }
    #map { height: 500px !important; }
}
</style>