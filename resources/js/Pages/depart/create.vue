<script setup>
import { useForm, router } from "@inertiajs/vue3";
import MazBtn from "maz-ui/components/MazBtn";
import { onMounted, ref, watch, watchEffect, computed } from "vue";
import axios from "axios";
import { toast } from "@/Components/AppToast";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import BaseInput from "@/Components/BaseInput.vue";
import BaseSelect from "@/Components/BaseSelect.vue";
import PageFrame from "@/Components/PageFrame.vue";

defineOptions({ layout: DefaultLayout });

const CATEGORIES_LIEES = ["Reponse à un Courrier arrivé", "Dossier Retourné", "Dossier Retourne"];

const objetsAvecBail = new Set([
    "Demande de Régularisation par voie de Bail",
    "DEMANDE DE BAIL",
    "Morcellements",
    "Réquisition d'immatriculation",
    "Demande Avis Technique",
    "Demande de terrain/Echange",
    "Prospection de terrain",
    "Autorisation de construction",
    "Demande d'extraits de plan",
    "Autorisation de lotir",
    "Demande d'états des lieux",
    "Demande de délimitation",
    "Demande de reconstruction",
    "Réquisition DSCOS",
    "Tribunal",
    "Litiges",
    "Demande de situation foncière",
    "Demande de Cession définitive",
    "Demande d'évaluation",
    "Demande de Cession définitive a Titre Gratuit",
    "Demande d'attestation du Cadastre",
    "Réceptions de lotissements",
    "Demande de CIC",
    "Duplication de CIC",
    "Demande de Titre foncier",
    "Autorisation de morceler",
]);

const form = useForm({
    //  courierdepart 
    txt_numdordrecd:"",
    txt_caracterecd:"",
    dt_datecouriercd:"",
    txt_numdossiercd:"",
    txt_avisReponsecd:"",
    txt_typecourriercd:"",
    txt_categoriecd:"",
    txt_referencecourierarriveecd:"",
    txt_referencecourierdepartcd:"",
    txt_nombrepiececd:"",
    txt_referencecd:"",

    txt_prenomNomcd:"",
    txt_numLotcd:"",
    txt_superficiecd:"",
    txt_numTitreMerecd:"",
    txt_numTitreFonciercd:"",
    txt_NICADcd:"",
    txt_lotissementcd:"",

    txt_objetcd:"",
    txt_destinatairecd:"",
    dt_dateenvoicd:"",
    txt_referencereceptioncd:"",
    txt_observationcd:"",
    txt_dureetraitementcd:"",
    fichierPDFcd: null,
});
 
const fichierPDFcd = ref(null);
const designationLiee = ref("");
const anneeCourante = ref(null);
const numeroGenere = ref(null);

const showcd = computed(() => CATEGORIES_LIEES.includes(form.txt_categoriecd));

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

// Récuperer le fichier PDF 
function handleFileUploadcd(event) {
    const file = event.target.files[0];

    if (!file) return;

    if (file.type !== "application/pdf") {
        toast.error("Veuillez sélectionner un fichier PDF valide.");
        event.target.value = ""; // reset input
        return;
    }

    if (file.size > 100 * 1024 * 1024) { // 100 Mo
        toast.error("Le fichier dépasse 100 Mo !");
        event.target.value = ""; // reset input
        return;
    }

    fichierPDFcd.value = file;
    form.fichierPDFcd = file;

    console.log("Fichier PDF sélectionné :", fichierPDFcd.value);
}

const mettreAJourNumero = () => {
    if (!form.dt_datecouriercd || numeroGenere.value === null) return;
    const date = new Date(form.dt_datecouriercd);
    if (Number.isNaN(date.getTime())) return;
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    form.txt_numdordrecd = `${numeroGenere.value}/${day}-${month}-${year}`;
};

const fetchNextDossier = async (annee) => {
    if (annee === anneeCourante.value && numeroGenere.value !== null) {
        mettreAJourNumero();
        return;
    }

    try {
        const response = await axios.get(route("depart.next", annee));
        const numero = response.data.num_dordre;
        if (!numero) throw new Error("Numéro absent");
        anneeCourante.value = annee;
        numeroGenere.value = numero;
        mettreAJourNumero();
    } catch (error) {
        console.error("Erreur génération numéro départ :", error);
        if (!form.txt_numdordrecd) {
            const date = new Date(form.dt_datecouriercd || Date.now());
            const day = String(date.getDate()).padStart(2, "0");
            const month = String(date.getMonth() + 1).padStart(2, "0");
            const year = date.getFullYear();
            numeroGenere.value = "0001";
            anneeCourante.value = year;
            form.txt_numdordrecd = `0001/${day}-${month}-${year}`;
        }
    }
};

watch(() => form.dt_datecouriercd, (nouvelleDate) => {
    if (!nouvelleDate) return;
    const date = new Date(nouvelleDate);
    if (Number.isNaN(date.getTime())) return;
    const annee = date.getFullYear();
    if (annee !== anneeCourante.value) {
        fetchNextDossier(annee);
    } else {
        mettreAJourNumero();
    }
});

onMounted(() => {
    if (form.dt_datecouriercd) {
        fetchNextDossier(new Date(form.dt_datecouriercd).getFullYear());
    }
}); 
  
// Références disponibles 
const references = ref([]);
const rechercheReference = ref('');  
const referenceToExpediteur = ref({});
const referenceToObject = ref({}); 
const referenceToReception = ref({});
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

const viderCartes = () => {
    references.value = [];
    referenceToExpediteur.value = {};
    referenceToObject.value = {};
    referenceToReception.value = {};
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

const appliquerCartes = (data) => {
    references.value = data.references || [];
    referenceToExpediteur.value = data.map_ref_to_expediteur || {};
    referenceToObject.value = data.map_ref_to_objet || {};
    referenceToReception.value = data.map_ref_to_reception || {};
    referenceToNombreDePiece.value = data.map_ref_to_nombrePiece || {};
    referenceToPrenomNom.value = data.map_ref_to_prenomNom || {};
    referenceToNumLot.value = data.map_ref_to_numLot || {};
    referenceToSuperficie.value = data.map_ref_to_superficie || {};
    referenceToNumTF.value = data.map_ref_to_numTF || {};
    referenceToNumTitreMere.value = data.map_ref_to_numTitreMere || {};
    referenceToNicad.value = data.map_ref_to_nicad || {};
    referenceToLotissement.value = data.map_ref_to_lotissement || {};
    referenceToTypeCourrier.value = data.map_ref_to_typeCourrier || {};
    referenceToNumDossier.value = data.map_ref_to_numDossier || {};
    referenceToDesignation.value = data.map_ref_to_designation || {};
};

const fetchReferences = async (categorie) => {
    if (!categorie || !CATEGORIES_LIEES.includes(categorie)) {
        viderCartes();
        return;
    }

    try {
        const res = await axios.post("/fetch-references-arrivee", {
            categorie,
            q: rechercheReference.value,
        });
        appliquerCartes(res.data || {});
    } catch (e) {
        console.error("Erreur lors de la récupération des références :", e);
        viderCartes();
    }
};

const lire = (carte, cle) => (cle && carte[cle] != null ? carte[cle] : "");

const superficieSansUnite = (valeur) => {
    if (valeur == null || valeur === "") return "";
    const nombre = String(valeur)
        .replace(/m\s*[²2]/gi, "")
        .replace(/\s/g, "")
        .replace(",", ".")
        .match(/-?\d+(?:\.\d+)?/);
    return nombre ? nombre[0] : "";
};

const appliquerReference = (selectedRef) => {
    if (!selectedRef) {
        designationLiee.value = "";
        return;
    }

    designationLiee.value = lire(referenceToDesignation.value, selectedRef);
    form.txt_destinatairecd = lire(referenceToExpediteur.value, selectedRef);
    form.txt_objetcd = lire(referenceToObject.value, selectedRef) || designationLiee.value;
    form.txt_nombrepiececd = lire(referenceToNombreDePiece.value, selectedRef);
    form.txt_prenomNomcd = lire(referenceToPrenomNom.value, selectedRef);
    form.txt_numLotcd = lire(referenceToNumLot.value, selectedRef);
    form.txt_superficiecd = superficieSansUnite(lire(referenceToSuperficie.value, selectedRef));
    form.txt_numTitreFonciercd = lire(referenceToNumTF.value, selectedRef);
    form.txt_numTitreMerecd = lire(referenceToNumTitreMere.value, selectedRef);
    form.txt_NICADcd = lire(referenceToNicad.value, selectedRef);
    form.txt_lotissementcd = lire(referenceToLotissement.value, selectedRef);
    form.txt_typecourriercd = lire(referenceToTypeCourrier.value, selectedRef);
    form.txt_numdossiercd = lire(referenceToNumDossier.value, selectedRef);
};

watch(() => form.txt_categoriecd, (newCategorie, ancienne) => {
    if (newCategorie === ancienne) return;
    form.txt_referencecourierarriveecd = "";
    form.txt_destinatairecd = "";
    form.txt_objetcd = "";
    form.txt_nombrepiececd = "";
    form.txt_prenomNomcd = "";
    form.txt_numLotcd = "";
    form.txt_superficiecd = "";
    form.txt_numTitreFonciercd = "";
    form.txt_numTitreMerecd = "";
    form.txt_NICADcd = "";
    form.txt_lotissementcd = "";
    form.txt_typecourriercd = "";
    form.txt_numdossiercd = "";
    designationLiee.value = "";
    fetchReferences(newCategorie);
});

let rechercheReferenceTimer = null;
watch(rechercheReference, () => {
    clearTimeout(rechercheReferenceTimer);
    rechercheReferenceTimer = setTimeout(() => {
        if (form.txt_categoriecd) fetchReferences(form.txt_categoriecd);
    }, 300);
});

watch(() => form.txt_referencecourierarriveecd, (selectedRef) => {
    appliquerReference(selectedRef);
});

// reupèration references courrier depart
watch(
  () => [form.txt_numdordrecd, form.dt_datecouriercd],
  ([newNum, newDate]) => {
    if (!newNum || !newDate){
        form.txt_referencecd = '';
        return;
    }  

    // Sécuriser le parsing de la date
    const parseDate = (str) => {
        if (!str) return null;
        // accepter "YYYY-MM-DD" ou "DD-MM-YYYY"
        let parts = str.includes("-") ? str.split("-") : str.split("/");
        if (parts.length !== 3) return null;

        if (parts[0].length === 4) {
            // format "YYYY-MM-DD"
            return new Date(`${parts[0]}-${parts[1]}-${parts[2]}`);
        } else {
            // format "DD-MM-YYYY"
            return new Date(`${parts[2]}-${parts[1]}-${parts[0]}`);
        }
    };

    const date = parseDate(newDate);
    if (!date || isNaN(date.getTime())) {
        form.txt_referencecd = "Date invalide";
        return;
    }

    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();
    const dateFormatee = `${day}-${month}-${year}`; // "29-08-2025"

    // Extraire la partie après le dernier "/"
    const partsNumero = newNum.split("/");
    const complementRef = (partsNumero.length > 1 ? partsNumero[0] : newNum).trim();

    form.txt_referencecd = `${complementRef}/MFB/DGID/CSF/CSF-NGA/BCAD du ${dateFormatee}`;

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
 
// Soumission du formulaire
const submitForm = function () {  // Ajoutez `async` ici 
    const today = new Date().toISOString().split('T')[0];

    if (form.dt_dateenvoicd > today) {
        toast.error("La date d'envoi ne peut pas être postérieure à aujourd'hui.");
        return;
    }
    // Formulaire Laravel
    form.post(route('depart.store'), {
        forceFormData: true,
        onSuccess: () => {
            toast.success('Enregistré avec succès')
            router.visit(route("depart.create"), { replace: true });
        },
        onError: (errors) => {
        
            Object.values(errors).forEach(msg => toast.error(msg))
        }
    })

};

</script>

<template>
    <PageFrame
        page-title="Courrier Départ"
        header="Enregistrement des Courriers Départs"
        banner="Formulaire d'enregistrement des Courriers Départs"
    >
                        <form @submit.prevent="submitForm">
                            <div class="p-6 rounded-lg">
                                <!-- Section Parcelle --> 
                                <div class="mb-6">
                                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2"> 
                                        <BaseInput
                                            class="sm:col-span-2 bg-gray-100 cursor-not-allowed"
                                            id="txt_numdordrecd"
                                            v-model="form.txt_numdordrecd"
                                            label="N° d'ordre"
                                            type="text"
                                            :required="true"
                                            readonly
                                            :disabled="true"
                                            autocomplete="off" 
                                        />

                                        <BaseInput
                                            id="dt_datecouriercd"
                                            v-model="form.dt_datecouriercd"
                                            label="Date Courrier"
                                            type="date"
                                            autocomplete="off"
                                        />
 
                                        <BaseInput
                                            v-if="showBail" 
                                            id="txt_numdossiercd"
                                            v-model="form.txt_numdossiercd"
                                            label="N° Dossier Foncier" 
                                            autocomplete="off"
                                        /> 
 
                                        <BaseSelect  
                                            v-if="showBail" 
                                            id="txt_avisReponsecd"
                                            v-model="form.txt_avisReponsecd"
                                            label="Avis Réponse" 
                                            :options="[
                                                { value: 'Avis Favorable', label: 'Avis Favorable' },
                                                { value: 'Avis Défavorable', label: 'Avis Défavorable' }, 
                                            ]"
                                        /> 

                                        <BaseSelect 
                                            class="sm:col-span-1"
                                            label="Type de Courrier"
                                            name="txt_typecourriercd"  
                                            required
                                            v-model="form.txt_typecourriercd"
                                            :options="[
                                                { value: 'Individuel', label: 'Individuel' },
                                                { value: 'BE', label: 'BE' }, 
                                                { value: 'BE à réponses Multiples', label: 'BE à réponses Multiples' },
                                            ]"
                                        />  
                                    </div>
                                    <br />
                                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-2"> 
                                        <BaseSelect
                                            class="sm:col-span-1"
                                            id="txt_categoriecd"
                                            v-model="form.txt_categoriecd"
                                            label="Catégorie"
                                            :options="[
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
                                            v-if="showcd"
                                            v-model="rechercheReference"
                                            label="Rechercher une référence"
                                            type="search"
                                            placeholder="Quelques lettres de la référence"
                                        />
                                        <BaseSelect
                                            v-if="showcd"
                                            id="txt_referencecourierarriveecd"
                                            v-model="form.txt_referencecourierarriveecd"
                                            label="Ref.Courrier Arrivée à Repondre"
                                            :options="references.map(ref => ({ value: ref, label: ref }))"
                                            placeholder="Choisir une référence"
                                        /> 

                                        <BaseInput
                                            id="txt_nombrepiececd"
                                            v-model="form.txt_nombrepiececd"
                                            label="Nbre de pièces"
                                            type="text"
                                            :required="true"
                                            autocomplete="off"
                                            min="1"
                                        />

                                        <BaseInput
                                            class="sm:col-span-2"
                                            id="txt_referencecd"
                                            v-model="form.txt_referencecd"
                                            label="Référence Courrier"
                                            type="text"
                                            :required="true"
                                            autocomplete="off"
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
                                            label="N° Titre Mère"
                                            name="txt_numTitreMerecd"  
                                            autocomplete="off"  
                                            v-model="form.txt_numTitreMerecd"
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
                                            label="Lotissement"
                                            name="txt_lotissementcd"  
                                            autocomplete="on"  
                                            v-model="form.txt_lotissementcd"
                                        />    
 
                                        <BaseInput 
                                            id="txt_destinatairecd"
                                            v-model="form.txt_destinatairecd"
                                            label="Destinataire"
                                            type="text" 
                                            autocomplete="off"
                                        /> 

                                        <BaseInput 
                                            id="txt_referencereceptioncd"
                                            v-model="form.txt_referencereceptioncd"
                                            label="Ref. Décharge"
                                            type="text"
                                            autocomplete="off"
                                        />

                                        <BaseInput
                                            id="dt_dateenvoicd"
                                            v-model="form.dt_dateenvoicd"
                                            label="Date d'envoi"
                                            type="date"
                                            :required="true"
                                            autocomplete="off"
                                        /> 

                                        <BaseInput 
                                            v-if="showcd"
                                            id="txt_dureetraitementcd"
                                            v-model="form.txt_dureetraitementcd"
                                            label="Durée de traitement"
                                            type="text"
                                            autocomplete="off"
                                            :disabled="true"
                                        /> 

                                        <BaseSelect 
                                            id="txt_caracterecd"
                                            v-model="form.txt_caracterecd"
                                            label="Caractères"
                                            :options="[
                                                { value: 'Confidentiel', label: 'Confidentiel' },
                                                { value: 'Urgent', label: 'Urgent' },
                                                { value: 'Secret', label: 'Secret' }
                                            ]"
                                            placeholder="Sélectionner un caractère"
                                        /> 
 
                                        <BaseInput
                                            class="sm:col-span-2"
                                            id="txt_objetcd"
                                            v-model="form.txt_objetcd"
                                            label="Objet"
                                            type="text" 
                                            autocomplete="address-level2"
                                        /> 

                                        <BaseInput
                                            class="sm:col-span-2"
                                            id="txt_observationcd"
                                            v-model="form.txt_observationcd"
                                            label="Observation"
                                            type="text"
                                            autocomplete="off"
                                        /> 
                             
                                        <BaseInput 
                                            type="file"
                                            accept="application/pdf"
                                            class="sm:col-span-2"
                                            label="Importer le Fichier PDF" 
                                            name="fichier_PDFcd" 
                                            @change="handleFileUploadcd" 
                                        /> 
 
                                    </div>
                                    <br /> 
                                </div> 
 
                                <!-- Bouton de soumission -->
                                <div class="sm:col-span-6 flex justify-center">
                                    <MazBtn 
                                        type="submit" no-shadow no-hover-effect
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
    </PageFrame>
</template>