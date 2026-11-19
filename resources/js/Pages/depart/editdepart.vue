<script setup>
import BoutonSupprimer from "@/Components/BoutonSupprimer.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import MazBtn from "maz-ui/components/MazBtn";
import { ref, watch, watchEffect, computed } from "vue";
import axios from "axios";
import { toast } from "@/Components/AppToast";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import BaseInput from "@/Components/BaseInput.vue";
import BaseSelect from "@/Components/BaseSelect.vue";

defineOptions({ layout: DefaultLayout });

const CATEGORIES_LIEES = ["Reponse à un Courrier arrivé", "Dossier Retourné", "Dossier Retourne"];

const props = defineProps({
    arrivee: {
        type: Object,
        default: () => ({})
    },
    depart: {
        type: Object,
        default: () => ({})
    },
    departs: {
        type: Object,
        default: () => ({})
    },
    lotissements: {
        type: Array,
        default: () => []
    },
    arrivees: {
        type: Array,
        default: () => []
    } });

// Extraction sécurisée des données
const departs = props.departs || {};
const arrivees = props.arrivees || [];
const lotissements = props.lotissements || [];

// Refs
const references = ref([]);
const rechercheReference = ref('');
const referenceToExpediteur = ref({});
const referenceToObject = ref({});
const referenceToNombreDePiece = ref({});
const referenceToPrenomNom = ref({});
const referenceToNumLot = ref({});
const referenceToSuperficie = ref({});
const referenceToNumTF = ref({});
const referenceToNumTitreMere = ref({});
const referenceToNicad = ref({});
const referenceToLotissement = ref({});
const referenceToTypeCourrier = ref({});
const referenceToNumDossier = ref({});
const referenceToDesignation = ref({});
const designationLiee = ref("");

// Computed pour les options du sélecteur de références
const referenceOptions = computed(() => {
    const options = [];
    
    if (departs?.txt_referencecourierarriveecd) {
        options.push({
            value: departs.txt_referencecourierarriveecd,
            label: departs.txt_referencecourierarriveecd,
            selected: true
        });
    }
    
    if (references.value && references.value.length > 0) {
        references.value.forEach(ref => {
            if (!options.some(opt => opt.value === ref)) {
                options.push({
                    value: ref,
                    label: ref
                });
            }
        });
    }
    
    return options;
});

const superficieSansUnite = (valeur) => {
    if (valeur == null || valeur === "") return "";
    const nombre = String(valeur)
        .replace(/m\s*[²2]/gi, "")
        .replace(/\s/g, "")
        .replace(",", ".")
        .match(/-?\d+(?:\.\d+)?/);
    return nombre ? nombre[0] : "";
};

// Formulaire
const form = useForm({
    txt_numdordrecd: departs?.txt_numdordrecd ?? '',
    dt_datecouriercd: departs?.dt_datecouriercd ?? '',
    txt_numdossiercd: departs?.txt_numdossiercd ?? '',
    txt_avisReponsecd: departs?.txt_avisReponsecd ?? '',
    txt_typecourriercd: departs?.txt_typecourriercd ?? '',
    txt_caracterecd: departs?.txt_caracterecd ?? '', 
    txt_categoriecd: departs?.txt_categoriecd ?? '',
    txt_referencecourierarriveecd: departs?.txt_referencecourierarriveecd ?? '',
    txt_referencecourierdepartcd: departs?.txt_referencecourierdepartcd ?? '',
    txt_nombrepiececd: departs?.txt_nombrepiececd ?? '',
    txt_referencecd: departs?.txt_referencecd ?? '', 
    txt_prenomNomcd: departs?.txt_prenomNomcd ?? '',
    txt_numLotcd: departs?.txt_numLotcd ?? '',
    txt_superficiecd: superficieSansUnite(departs?.txt_superficiecd ?? ''),
    txt_numTitreFonciercd: departs?.txt_numTitreFonciercd ?? departs?.txt_numTFcd ?? '',
    txt_NICADcd: departs?.txt_NICADcd ?? '',
    txt_numTitreMerecd: departs?.txt_numTitreMerecd ?? '',
    txt_lotissementcd: departs?.txt_lotissementcd ?? '',
    txt_objetcd: departs?.txt_objetcd ?? '',
    txt_destinatairecd: departs?.txt_destinatairecd ?? '',
    dt_dateenvoicd: departs?.dt_dateenvoicd ?? '',
    txt_referencereceptioncd: departs?.txt_referencereceptioncd ?? '',
    txt_observationcd: departs?.txt_observationcd ?? '',
    txt_dureetraitementcd: departs?.txt_dureetraitementcd ?? '',
    fichierPDFcd: departs?.fichierPDFcd ?? null });

const show = computed(() => CATEGORIES_LIEES.includes(form.txt_categoriecd));

const objetsAvecBail = new Set([
    'Demande de Régularisation par voie de Bail',
    'DEMANDE DE BAIL',
    'Morcellements',
    "Réquisition d'immatriculation",
    'Demande Avis Technique',
    'Demande de terrain/Echange',
    'Prospection de terrain',
    'Autorisation de construction',
    "Demande d'extraits de plan",
    'Autorisation de lotir',
    "Demande d'états des lieux",
    'Demande de délimitation',
    'Demande de reconstruction',
    'Réquisition DSCOS',
    'Tribunal',
    'Litiges',
    'Demande de situation foncière',
    'Demande de Cession définitive',
    "Demande d'évaluation",
    'Demande de Cession définitive a Titre Gratuit',
    "Demande d'attestation du Cadastre",
    'Réceptions de lotissements',
    'Demande de CIC',
    'Duplication de CIC',
    'Demande de Titre foncier',
    'Autorisation de morceler',
]);

const showBail = computed(() => {
    const dejaRenseigne = [
        form.txt_numdossiercd,
        form.txt_avisReponsecd,
        form.txt_prenomNomcd,
        form.txt_numLotcd,
        form.txt_superficiecd,
        form.txt_numTitreFonciercd,
        form.txt_numTitreMerecd,
        form.txt_NICADcd,
        form.txt_lotissementcd,
    ].some((valeur) => valeur !== "" && valeur != null);

    const concerneUneParcelle = objetsAvecBail.has(form.txt_objetcd) || objetsAvecBail.has(designationLiee.value);

    return dejaRenseigne || (concerneUneParcelle && form.txt_typecourriercd !== "BE");
});

const viderCartes = () => {
    references.value = [];
    referenceToExpediteur.value = {};
    referenceToObject.value = {};
    referenceToNombreDePiece.value = {};
    referenceToPrenomNom.value = {};
    referenceToNumLot.value = {};
    referenceToSuperficie.value = {};
    referenceToNumTF.value = {};
    referenceToNumTitreMere.value = {};
    referenceToNicad.value = {};
    referenceToLotissement.value = {};
    referenceToTypeCourrier.value = {};
    referenceToNumDossier.value = {};
    referenceToDesignation.value = {};
};

const fetchReferences = async (categorie) => {
    if (!categorie || !CATEGORIES_LIEES.includes(categorie)) {
        viderCartes();
        return;
    }

    try {
        const res = await axios.post("/fetch-references-arrivee", {
            categorie,
            q: rechercheReference.value });
        const data = res.data || {};
        references.value = data.references ?? [];
        referenceToExpediteur.value = data.map_ref_to_expediteur ?? {};
        referenceToObject.value = data.map_ref_to_objet ?? {};
        referenceToNombreDePiece.value = data.map_ref_to_nombrePiece ?? {};
        referenceToPrenomNom.value = data.map_ref_to_prenomNom ?? {};
        referenceToNumLot.value = data.map_ref_to_numLot ?? {};
        referenceToSuperficie.value = data.map_ref_to_superficie ?? {};
        referenceToNumTF.value = data.map_ref_to_numTF ?? {};
        referenceToNumTitreMere.value = data.map_ref_to_numTitreMere ?? {};
        referenceToNicad.value = data.map_ref_to_nicad ?? {};
        referenceToLotissement.value = data.map_ref_to_lotissement ?? {};
        referenceToTypeCourrier.value = data.map_ref_to_typeCourrier ?? {};
        referenceToNumDossier.value = data.map_ref_to_numDossier ?? {};
        referenceToDesignation.value = data.map_ref_to_designation ?? {};
    } catch (e) {
        console.error("Erreur lors de la récupération des références :", e);
        viderCartes();
    }
};

watch(() => form.txt_categoriecd, (newValue) => {
    fetchReferences(newValue);
}, { immediate: true });

let rechercheReferenceTimer = null;
watch(rechercheReference, () => {
    clearTimeout(rechercheReferenceTimer);
    rechercheReferenceTimer = setTimeout(() => {
        if (form.txt_categoriecd) fetchReferences(form.txt_categoriecd);
    }, 300);
});

const lire = (carte, cle) => (cle && carte[cle] != null && carte[cle] !== "" ? carte[cle] : null);

watch(() => form.txt_referencecourierarriveecd, (selectedRef, ancienne) => {
    if (!selectedRef || selectedRef === ancienne) return;
    const destinataire = lire(referenceToExpediteur.value, selectedRef);
    const objet = lire(referenceToObject.value, selectedRef);
    const designation = lire(referenceToDesignation.value, selectedRef);
    if (destinataire === null && objet === null && designation === null) return;

    designationLiee.value = designation || "";
    form.txt_destinatairecd = destinataire ?? form.txt_destinatairecd;
    form.txt_objetcd = objet || designation || form.txt_objetcd;
    form.txt_nombrepiececd = lire(referenceToNombreDePiece.value, selectedRef) ?? form.txt_nombrepiececd;
    form.txt_prenomNomcd = lire(referenceToPrenomNom.value, selectedRef) ?? form.txt_prenomNomcd;
    form.txt_numLotcd = lire(referenceToNumLot.value, selectedRef) ?? form.txt_numLotcd;
    const superficie = lire(referenceToSuperficie.value, selectedRef);
    form.txt_superficiecd = superficie === null ? form.txt_superficiecd : superficieSansUnite(superficie);
    form.txt_numTitreFonciercd = lire(referenceToNumTF.value, selectedRef) ?? form.txt_numTitreFonciercd;
    form.txt_numTitreMerecd = lire(referenceToNumTitreMere.value, selectedRef) ?? form.txt_numTitreMerecd;
    form.txt_NICADcd = lire(referenceToNicad.value, selectedRef) ?? form.txt_NICADcd;
    form.txt_lotissementcd = lire(referenceToLotissement.value, selectedRef) ?? form.txt_lotissementcd;
    form.txt_typecourriercd = lire(referenceToTypeCourrier.value, selectedRef) ?? form.txt_typecourriercd;
    form.txt_numdossiercd = lire(referenceToNumDossier.value, selectedRef) ?? form.txt_numdossiercd;
});

// Génération de la référence
watch(
    [() => form.txt_numdordrecd, () => form.dt_datecouriercd],
    ([newNum, newDate]) => {
        if (!newNum || !newDate) return;
        const date = new Date(newDate);
        if (Number.isNaN(date.getTime())) return;
        const day = String(date.getDate()).padStart(2, "0");
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const year = date.getFullYear();
        const numero = newNum.split("/")[0].trim();
        form.txt_referencecd = `${numero}/MFB/DGID/CSF/CSF-NGA/BCAD du ${day}-${month}-${year}`;
    }
);

const instantCalendaire = (texte, depuisReference) => {
    if (!texte) return null;
    const source = String(texte).trim();
    const cible = depuisReference
        ? (source.match(/du\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/i)?.[1] ?? "")
        : source.slice(0, 10);
    const morceaux = cible.match(/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})$/);
    if (!morceaux) return null;

    let jour;
    let mois;
    let annee;
    if (morceaux[1].length === 4) {
        annee = Number(morceaux[1]);
        mois = Number(morceaux[2]);
        jour = Number(morceaux[3]);
    } else {
        jour = Number(morceaux[1]);
        mois = Number(morceaux[2]);
        annee = Number(morceaux[3].length === 2 ? `20${morceaux[3]}` : morceaux[3]);
    }

    const instant = Date.UTC(annee, mois - 1, jour);
    const date = new Date(instant);
    if (date.getUTCFullYear() !== annee || date.getUTCMonth() !== mois - 1 || date.getUTCDate() !== jour) {
        return null;
    }
    return instant;
};

const calculerDureeTraitement = (reference, dateEnvoi) => {
    const arrivee = instantCalendaire(reference, true);
    const envoi = instantCalendaire(dateEnvoi, false);
    if (arrivee == null || envoi == null) return "";

    const jours = Math.round((envoi - arrivee) / 86400000);
    if (jours < 0) return "";
    if (jours === 0) return "24 heures";
    return jours === 1 ? "1 jour" : `${jours} jours`;
};

watchEffect(() => {
    if (!CATEGORIES_LIEES.includes(form.txt_categoriecd)) {
        form.txt_dureetraitementcd = "";
        return;
    }

    form.txt_dureetraitementcd = calculerDureeTraitement(
        form.txt_referencecourierarriveecd,
        form.dt_dateenvoicd
    );
});

// Gestion du fichier PDF
function handleFileChangecd(event) {
    const file = event.target?.files?.[0];
    if (!file) {
        form.fichierPDFcd = null;
        return;
    }

    if (file.type !== "application/pdf") {
        toast.error("Veuillez sélectionner un fichier PDF valide.");
        event.target.value = "";
        form.fichierPDFcd = null;
        return;
    }

    if (file.size > 100 * 1024 * 1024) {
        toast.error("Le fichier dépasse 100 Mo !");
        event.target.value = "";
        form.fichierPDFcd = null;
        return;
    }

    form.fichierPDFcd = file;
}

// Suppression du fichier PDF
const deleteFile = (depart) => {
    if (!depart || !depart.id) {
        console.error('Aucun départ fourni à deleteFile');
        toast.error('Aucun fichier à supprimer');
        return;
    }

    if (confirm('Voulez-vous vraiment supprimer ce fichier PDF ?')) {
        router.delete(route('depart.pdf.destroy', depart.id), {
            onSuccess: () => {
                depart.fichierPDFcd = null;
                toast.success('Fichier supprimé avec succès ✅');
            },
            onError: (error) => {
                console.error('Erreur lors de la suppression du PDF :', error);
                toast.error('Erreur lors de la suppression du fichier ❌');
            } });
    }
};

// ✅ Prévisualiser la réponse
const previewReponse = () => {
    try {
        if (!form.txt_referencecourierarriveecd && form.txt_objetcd !== "Demande de Régularisation par voie de Bail" && form.txt_objetcd !== "DEMANDE DE BAIL") {
            toast.warning('Veuillez vérifier si le courrier est une demande bail');
            return;
        }

        const numOrdre = form.txt_numdordrecd.split("/")[0] || '097';
        const dateCourrier = form.dt_datecouriercd ? new Date(form.dt_datecouriercd).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase() : '24 JUN 2026';
        const objet = form.txt_objetcd || 'Demande de bail';
        const referenceArrivee = form.txt_referencecourierarriveecd || 'BE N° 006 /DGID/DD/CSF-NGA/BD du 21 mai 2026';
        const dossier = form.txt_numdossiercd || '049 du 21 mai 2026';
        const destinataire = form.txt_destinatairecd || 'le Centre des Services Fiscaux de Ngor Almadies';
        const nicad = form.txt_NICADcd || '13 1 201 01 004 00127';
        const superficie = form.txt_superficiecd || '49ha 00a 00ca';
        const lotissement = form.txt_lotissementcd || 'lotissement';
        const observation = form.txt_observationcd || 'dans la commune de Ngor, arrondissement des Almadies';
        const avis = form.txt_avisReponsecd || 'avis favorable';
        const prenomNom = form.txt_prenomNomcd || '';
        const numLot = form.txt_numLotcd || '';

        const win = window.open('', '_blank', 'width=800,height=900');
        if (!win) {
            toast.error('Veuillez autoriser les popups');
            return;
        }

        win.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Réponse Courrier N° ${numOrdre}</title>
                <style>
                    @page { margin: 60px 70px; }
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body {
                        font-family: 'Times New Roman', Times, serif;
                        font-size: 12px;
                        line-height: 1.6;
                        padding: 40px 50px;
                        background: white;
                        color: #1a1a1a;
                    }
                    .header { text-align: center; margin-bottom: 30px; }
                    .header .logo { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
                    .header .subtitle { font-size: 11px; color: #333; }
                    .header .line { border-top: 2px solid #333; margin: 8px auto; width: 80%; }
                    .reference { font-size: 13px; font-weight: bold; margin-bottom: 5px; }
                    .date { text-align: right; font-size: 13px; margin-bottom: 20px; }
                    .destinataire { font-size: 13px; margin-bottom: 15px; }
                    .objet-ref { margin-bottom: 15px; font-size: 13px; }
                    .corps { text-align: justify; font-size: 13px; margin-bottom: 20px; line-height: 1.8; }
                    .corps p { margin-bottom: 10px; }
                    .signature { text-align: right; font-size: 13px; margin-bottom: 30px; margin-top: 50px; }
                    .signature .nom { font-weight: bold; margin-top: 30px; }
                    .footer { margin-top: 50px; text-align: center; font-size: 11px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
                    @media print { body { padding: 20px 40px; } .no-print { display: none !important; } }
                    .no-print {
                        position: fixed;
                        bottom: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        display: flex;
                        gap: 10px;
                        z-index: 999;
                        background: white;
                        padding: 10px 20px;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    }
                    .no-print button {
                        padding: 8px 20px;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 13px;
                        font-weight: 600;
                        transition: all 0.2s;
                    }
                    .btn-print { background: #1a56db; color: white; }
                    .btn-print:hover { background: #1e40af; }
                    .btn-close { background: #6b7280; color: white; }
                    .btn-close:hover { background: #4b5563; }
                    .cachet { margin-top: 40px; font-weight: bold; }
                    .cachet .ligne1 { font-size: 12px; letter-spacing: 1px; }
                    .cachet .ligne2 { font-size: 12px; margin-top: 5px; }
                </style>
            </head>
            <body>
                <div class="no-print">
                    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
                    <button class="btn-close" onclick="window.close()">✖ Fermer</button>
                </div>

                <div class="header">
                    <div class="logo">RÉPUBLIQUE DU SÉNÉGAL</div>
                    <div class="subtitle">Un Peuple - Un But - Une Foi</div>
                    <div class="line"></div>
                    <div style="font-size: 13px; font-weight: bold;">MINISTÈRE DES FINANCES ET DU BUDGET</div>
                    <div style="font-size: 12px;">DIRECTION GÉNÉRALE DES IMPOTS ET DES DOMAINES</div>
                    <div style="font-size: 12px;">DIRECTION DES CENTRES FISCAUX</div>
                    <div style="font-size: 12px; font-weight: bold;">CENTRE DES SERVICES FISCAUX DE NGOR ALMADIES</div>
                    <div style="font-size: 12px; font-style: italic;">Bureau du Cadastre</div>
                </div>

                <div class="reference">No. ${numOrdre}/MFB/DGID/DCF/CSF-NGA/BCAD</div>
                <div class="date">Dakar, le ${dateCourrier}</div>
                <div class="destinataire"><strong>Le Chef du Bureau du Cadastre</strong></div>

                <div class="objet-ref">
                    <div><strong>Objet :</strong> ${objet}</div>
                    <div><strong>Référence :</strong> ${referenceArrivee},</div> 
                </div>

                <div class="corps">
                    <p>Je vous envoie en retour avec <strong>${avis}</strong> le dossier visé en référence par lequel Mr/Mme <strong>${prenomNom}</strong> sollicite un bail sur la parcelle de terrain objet du NICAD <strong>${nicad}</strong>${numLot ? ', Lot N°' + numLot : ''} pour le projet de lotissement de <strong>${lotissement}</strong> ${observation}.</p>
                    <p>Je rappelle que la parcelle objet de la demande fait une superficie de <strong>${superficie}</strong> et dépend du domaine national.</p>
                </div>

                <div class="signature">
                    <div>Le Chef de Bureau du Cadastre</div>
                    ${prenomNom ? `<div style="font-weight: bold;">${prenomNom}</div>` : ''} 
                </div>

                <div style="margin-top: 30px;">
                    <div>A</div>
                    <div style="font-weight: bold;">Monsieur le Chef du Bureau Du Domaine</div>
                </div>

                <div class="cachet">
                    <div class="ligne1">CENTRE DES SERVICES FISCAUX DE NGOR ALMADIES</div>
                    <div class="ligne2">Le ${dateCourrier}</div> 
                </div>

                <div class="footer">Fait le : ${new Date().toLocaleString('fr-FR')}</div>
            </body>
            </html>
        `);
        win.document.close();

    } catch (error) {
        console.error('Erreur lors de la prévisualisation :', error);
        toast.error('Erreur lors de la prévisualisation');
    }
};
 
 
// Soumission du formulaire
async function submit() {
    try {
        console.log("📤 Envoi du formulaire départ...");

        const formData = new FormData();
        formData.append('_method', 'PUT');
        
        const fields = [
            'txt_numdordrecd', 'dt_datecouriercd', 'txt_numdossiercd',
            'txt_avisReponsecd', 'txt_typecourriercd', 'txt_caracterecd',
            'txt_referencecourierarriveecd', 'dt_dateenvoicd',
            'txt_referencecd', 'txt_prenomNomcd', 'txt_numLotcd',
            'txt_superficiecd', 'txt_numTitreFonciercd', 'txt_numTitreMerecd',
            'txt_NICADcd', 'txt_lotissementcd',
            'txt_nombrepiececd', 'txt_categoriecd', 'txt_destinatairecd',
            'txt_objetcd', 'txt_referencereceptioncd', 'txt_dureetraitementcd',
            'txt_observationcd'
        ];

        fields.forEach(field => {
            formData.append(field, form[field] ?? '');
        });

        if (form.fichierPDFcd instanceof File) {
            formData.append('fichierPDFcd', form.fichierPDFcd);
        }

        await axios.post(route('depart.update', departs.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        toast.success('Modification réussie !');
        router.visit(route('instancedepart.create'), { replace: true });

    } catch (error) {
        console.error('Erreur détaillée:', error.response?.data);
        if (error.response?.data?.errors) {
            Object.values(error.response.data.errors).forEach(err => toast.error(err));
        } else {
            toast.error('Erreur lors de la modification');
        }
    }
}
</script>

<template>
    <Head title="Courrier Départ" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl text-primary-txt font-semibold leading-tight">
                Modification Courrier Départ
            </h2>
        </template>

        <div class="py-12">
            <div class="flex justify-center">
                <div class="w-full max-w-8xl">
                    <div class="bg-white shadow-md rounded-xl">
                        <div class="p-4 border-b bg-primary sm:rounded-t-xl">
                            <h1 class="text-lg text-white font-semibold">
                                Formulaire de Modification des Courriers Départs
                            </h1>
                        </div>
                        
                        <form @submit.prevent="submit">
                            <div class="p-6">
                                <h5 class="text-lg text-primary-txt font-bold">
                                    Modification du Courrier N° : {{ departs?.txt_numdordrecd || 'N/A' }}
                                </h5>
                                <br />

                                <!-- Ligne 1 -->
                                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                    <BaseInput  
                                        class="sm:col-span-1"
                                        label="N° d'ordre départ"
                                        name="txt_numdordrecd"  
                                        autocomplete="off"  
                                        v-model="form.txt_numdordrecd" 
                                    />

                                    <BaseInput
                                        class="sm:col-span-1"
                                        label="Date Courrier"
                                        name="dt_datecouriercd" 
                                        type="date" 
                                        v-model="form.dt_datecouriercd"
                                        autocomplete="off"
                                    />
                                     
                                    <BaseInput
                                        v-if="showBail"
                                        class="sm:col-span-1"
                                        id="txt_numdossiercd"
                                        v-model="form.txt_numdossiercd"
                                        label="N° Dossier Foncier" 
                                        autocomplete="off"
                                    /> 

                                    <BaseSelect  
                                        v-if="showBail"
                                        class="sm:col-span-1"
                                        id="txt_avisReponsecd"
                                        v-model="form.txt_avisReponsecd"
                                        label="Avis Réponse" 
                                        :options="[
                                            { value: '', label: 'Sélectionner' },
                                            { value: 'Avis Favorable', label: 'Avis Favorable' },
                                            { value: 'Avis Défavorable', label: 'Avis Défavorable' }, 
                                        ]"
                                    /> 
                                                                                
                                    <BaseSelect  
                                        label="Type de Courrier"
                                        name="txt_typecourriercd"  
                                        required
                                        v-model="form.txt_typecourriercd"
                                        :options="[
                                            { value: '', label: 'Sélectionner' },
                                            { value: 'Individuel', label: 'Individuel' },
                                            { value: 'BE', label: 'BE' }, 
                                            { value: 'BE à réponses Multiples', label: 'BE à réponses Multiples' },
                                        ]"
                                    /> 
                                </div>
                                <br />

                                <!-- Ligne 2 -->
                                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                    <BaseSelect 
                                        class="sm:col-span-2"
                                        id="txt_categoriecd"
                                        v-model="form.txt_categoriecd"
                                        label="Catégorie"
                                        :options="[
                                            { value: '', label: 'Sélectionner' },
                                            { value: 'Reponse à un Courrier arrivé', label: 'Reponse à un Courrier arrivé' },
                                            { value: 'Demande', label: 'Demande' },
                                            { value: 'Transmission de Documents', label: 'Transmission de Documents' },
                                            { value: 'Information', label: 'Information' },
                                            { value: 'Alerte', label: 'Alerte' },
                                            { value: 'Signalement', label: 'Signalement' },
                                            { value: 'Dossier Retourné', label: 'Dossier Retourné' }
                                        ]"
                                        placeholder="Sélectionner une catégorie"
                                    />

                                    <BaseInput
                                        v-if="show"
                                        v-model="rechercheReference"
                                        label="Rechercher une référence"
                                        type="search"
                                        placeholder="Quelques lettres de la référence"
                                    />

                                    <BaseSelect
                                        v-if="show"
                                        id="txt_referencecourierarriveecd"
                                        v-model="form.txt_referencecourierarriveecd"
                                        label="Ref.Courrier Arrivée à Répondre"
                                        name="txt_referencecourierarriveecd"
                                        :options="referenceOptions"
                                        placeholder="Choisir une référence"
                                    />

                                    <BaseInput
                                        class="sm:col-span-1"
                                        label="Nbre de pièces"
                                        name="txt_nombrepiececd"
                                        v-model="form.txt_nombrepiececd"
                                        autocomplete="off"
                                    />

                                    <BaseInput
                                        class="sm:col-span-2"
                                        label="Référence Courrier"
                                        name="txt_referencecd"
                                        v-model="form.txt_referencecd"
                                        required
                                    />

                                    <BaseInput
                                        class="sm:col-span-2"
                                        id="txt_objetcd"
                                        v-model="form.txt_objetcd"
                                        label="Objet"
                                        type="text"
                                        :required="true"
                                    />

                                    <BaseInput
                                        class="sm:col-span-2"
                                        label="Destinataire"
                                        name="txt_destinatairecd"
                                        v-model="form.txt_destinatairecd"
                                        required
                                    />
                                    
                                    <BaseInput  
                                        v-if="showBail"
                                        class="sm:col-span-1"
                                        label="Prénom et Nom"
                                        name="txt_prenomNomcd"  
                                        autocomplete="off"  
                                        v-model="form.txt_prenomNomcd"
                                    />

                                    <BaseInput  
                                        v-if="showBail"
                                        class="sm:col-span-1"
                                        label="N° Lot"
                                        name="txt_numLotcd"  
                                        autocomplete="off"  
                                        v-model="form.txt_numLotcd"
                                    />
 
                                    <BaseInput  
                                        v-if="showBail" 
                                        class="sm:col-span-1"
                                        label="N° Titre Foncier"
                                        name="txt_numTitreFonciercd"  
                                        autocomplete="off"  
                                        v-model="form.txt_numTitreFonciercd"
                                    />   

                                    <BaseInput  
                                        v-if="showBail" 
                                        class="sm:col-span-1"
                                        label="N° NICAD"
                                        name="txt_NICADcd"  
                                        autocomplete="off"  
                                        v-model="form.txt_NICADcd"
                                    />   

                                    <BaseInput
                                        v-if="showBail"
                                        class="sm:col-span-1"
                                        label="Superficie (m²)"
                                        name="txt_superficiecd"
                                        autocomplete="off"
                                        v-model="form.txt_superficiecd"
                                    />

                                    <BaseInput
                                        v-if="showBail" 
                                        class="sm:col-span-1"
                                        label="N° Titre Mère"
                                        name="txt_numTitreMerecd"  
                                        autocomplete="off"  
                                        v-model="form.txt_numTitreMerecd"
                                    />   

                                    <BaseSelect
                                        v-if="showBail"
                                        wrapperClass="sm:col-span-2"
                                        label="Lotissement"
                                        name="txt_lotissementcd"
                                        v-model="form.txt_lotissementcd"
                                        :options="lotissements"
                                        optionLabel="txt_denomination"
                                        optionValue="txt_denomination"
                                    />
                                    
                                    <BaseInput
                                        class="sm:col-span-1"
                                        label="Réf. Réception"
                                        name="txt_referencereceptioncd"
                                        v-model="form.txt_referencereceptioncd"
                                    />

                                    <BaseInput
                                        class="sm:col-span-1"
                                        label="Date d'envoi"
                                        name="dt_dateenvoicd"
                                        type="date"
                                        v-model="form.dt_dateenvoicd"
                                    />
                                    
                                    <BaseInput
                                        v-if="show"
                                        class="sm:col-span-1"
                                        label="Durée de traitement"
                                        name="txt_dureetraitementcd"
                                        v-model="form.txt_dureetraitementcd"
                                        readonly
                                    />

                                    <BaseSelect
                                        class="sm:col-span-1"
                                        label="Caractères"
                                        name="txt_caracterecd"
                                        v-model="form.txt_caracterecd"
                                        :options="[
                                            { value: '', label: 'Sélectionner' },
                                            { value: 'Confidentiel', label: 'Confidentiel' },
                                            { value: 'Urgent', label: 'Urgent' },
                                            { value: 'Secret', label: 'Secret' }
                                        ]"
                                    />

                                    <BaseInput
                                        label="Observation"
                                        name="txt_observationcd"
                                        v-model="form.txt_observationcd"
                                    />
                                
                                    <BaseInput
                                        label="Importer le Fichier PDF"
                                        name="fichierPDFcd"
                                        id="fichierPDFcd"
                                        type="file"
                                        accept="application/pdf"
                                        :error="form.errors.fichierPDFcd"
                                        @change="handleFileChangecd"
                                    />

                                    <div class="sm:col-span-3">
                                        <div v-if="departs?.fichierPDFcd" class="mt-2 flex items-center space-x-2">
                                            <a 
                                                :href="`/storage/${departs.fichierPDFcd}`" 
                                                target="_blank" 
                                                class="text-primary underline text-sm"
                                            >
                                                📄 Voir le PDF
                                            </a>
                                            <BoutonSupprimer @click="deleteFile(departs)" />
                                        </div>
                                    </div>

                                    <!-- ✅ Boutons de génération -->
                                    <div class="sm:col-span-4">
                                        <button
                                            type="button"
                                            @click="previewReponse"
                                            class="h-10 w-64 bg-gradient-to-r from-blue-500 to-blue-700 text-white font-semibold rounded-xl hover:shadow-lg transition-all duration-200"
                                        >
                                            👁️ Réponse
                                        </button> 
                                    </div>
                                </div>

                                <br />

                                <!-- Bouton de soumission -->
                                <div class="flex justify-center">
                                    <MazBtn 
                                        type="submit" 
                                        no-shadow 
                                        no-hover-effect
                                        class="w-64 h-10 text-white bg-gradient-to-r from-primary via-primary-dark 
                                        to-primary hover:bg-gradient-to-br focus:ring-4 focus:outline-none 
                                        focus:ring-blue-300 dark:focus:ring-blue-800 shadow-lg shadow-blue-500/50 
                                        dark:shadow-lg dark:shadow-blue-800/80 font-medium rounded-lg text-sm px-5 
                                        py-2.5 text-center me-2 mb-2"
                                        size="medium"
                                    >
                                        Enregistrer
                                    </MazBtn>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>