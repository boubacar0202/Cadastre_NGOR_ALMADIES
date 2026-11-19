<script setup>
import BoutonSupprimer from "@/Components/BoutonSupprimer.vue";
import BoutonModifier from "@/Components/BoutonModifier.vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

const props = defineProps({
    arrivees: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0 }) },
    designations: {
        type: Array,
        default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            totalCourrier: 0,
            convocations72h: 0,
            convocationsProches: [] }) },
    filters: {
        type: Object,
        default: () => ({}) } });

const filters = ref({
    mois: props.filters?.mois || '',
    annee: props.filters?.annee || '',
    numeroTF: props.filters?.numeroTF || '',
    numLot: props.filters?.numLot || '',
    prenom: props.filters?.prenom || '',
    reference: props.filters?.reference || '',
    designation: props.filters?.designation || '' });

const rowRefs = ref({});
const cibleConvocation = ref(null);

const uniqueDesignations = computed(() => props.designations || []);

const pageMeta = computed(() => props.arrivees?.meta || props.arrivees || {});

const paginatedArrivees = computed(() => (
    Array.isArray(props.arrivees) ? props.arrivees : (props.arrivees?.data || [])
));

const filtereArrivees = paginatedArrivees;

const pagination = computed(() => {
    const current = pageMeta.value.current_page || 1;
    const last = Math.max(1, pageMeta.value.last_page || 1);
    const perPage = pageMeta.value.per_page || 15;
    const pages = new Set([1, last, current, current - 1, current + 1, current - 2, current + 2]);
    const sorted = [...pages].filter((p) => p >= 1 && p <= last).sort((a, b) => a - b);
    const links = [];
    let previous = 0;

    sorted.forEach((p) => {
        if (p - previous > 1) {
            links.push({ label: '...', url: null, active: false });
        }
        links.push({ label: String(p), url: `?page=${p}`, active: p === current });
        previous = p;
    });

    links.prev = current > 1 ? `?page=${current - 1}` : null;
    links.next = current < last ? `?page=${current + 1}` : null;

    return {
        links,
        meta: { current_page: current, per_page: perPage } };
});

const mettreEnEvidence = async (txtNumdordre) => {
    await nextTick();
    const row = rowRefs.value[txtNumdordre];
    if (!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.add('bg-primary-only');
    setTimeout(() => row.classList.remove('bg-primary-only'), 5000);
};

const rechercher = (page = 1) => {
    router.get(route('instancearrivee.create'), {
        ...filters.value,
        page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['arrivees'],
        onSuccess: () => {
            if (!cibleConvocation.value) return;
            const cible = cibleConvocation.value;
            cibleConvocation.value = null;
            mettreEnEvidence(cible);
        } });
};

const debouncedSearch = debounce((page = 1) => rechercher(page), 350);

watch(filters, () => {
    debouncedSearch(1);
}, { deep: true });

onBeforeUnmount(() => debouncedSearch.cancel());

const goToPage = (url) => {
    if (!url) return;
    const params = new URLSearchParams(url.split('?')[1]);
    const next = Number(params.get('page'));
    if (next) rechercher(next);
};

const prevPage = () => {
    if (pagination.value.links?.prev) goToPage(pagination.value.links.prev);
};

const nextPage = () => {
    if (pagination.value.links?.next) goToPage(pagination.value.links.next);
};

const totalConvocationsImminentes = computed(() => props.stats?.convocations72h || 0);

const totalCourrier = computed(() => Number((props.arrivees?.meta || props.arrivees)?.total || 0));

// format de la date
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric' });
};

// fonction d'envoi du formulaire
function supprimerCourrierArrivee(arrivee) {
    if (confirm(`Voulez-vous vraiment supprimer le courrier arrivée N° ${arrivee.txt_numdordre} ?`)) {
        router.delete(route('instancearrivee.destroy', arrivee.id), {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Arrivée supprimée avec succès');
            },
            onError: (errors) => {
                console.error('Erreur lors de la suppression', errors);
            }
        });
    }
} 
const convocationProche = computed(() => props.stats?.convocationsProches || []);

const goToConvocation = (txt_numdordre) => {
    cibleConvocation.value = txt_numdordre;
    if (filters.value.numero === txt_numdordre) {
        mettreEnEvidence(txt_numdordre);
        return;
    }
    filters.value.numero = txt_numdordre;
};
const formatDateDMY = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',  
        year: 'numeric'
    });
};

</script>

<template>
    <Head title="Instances Arrivées">
        <link rel="icon" sizes="512x512" href="/logo-01.png">
    </Head>

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-primary-txt"
            >
                Liste des Courriers Arrivés
            </h2>
            
            <!-- Vérification pour afficher un message si aucune donnée -->
            <template class="text-xl font-semibold leading-tight text-primary-txt" v-if="!stats?.total">
                <p>Aucun Courrier arrivée trouvé.</p>
            </template>
           
        </template v-else>

        <div class="py-12">
            <div class="flex justify-center">
                <div class="w-full max-w-8xl">
                    <div class="bg-white shadow-md rounded-xl">
                        <div class="p-4 border-b bg-primary rounded-t-xl">
                            <h1 class="text-lg text-white font-semibold">Liste des courriers arrivées</h1>
                        </div>

                        <div class="p-6">
                            <div class="p-4 bg-primary-form border border-primary-only rounded-xl">
                                <div class="flex flex-wrap items-center justify-between gap-3">  
                                    <h1 class="text-lg text-primary-txt font-bold">
                                        Liste des Courriers Arrivés : 
                                        <span v-if="totalCourrier>0" class="text-primary-txt">
                                            ({{ totalCourrier }})
                                        </span>
                                        <span v-else class="text-red-600">
                                            Aucun enregistrement
                                        </span> 
                                    </h1> 
                                    <h2 class="text-sm text-primary font-bold">
                                        Convocation dans 72H : 
                                        <span class="text-primary-dark font-bold">{{ totalConvocationsImminentes }}</span> 
                                    </h2>  
                                    
                                    <ul v-if="convocationProche && convocationProche.length" class="space-y-2">
                                        <li
                                            v-for="convocation in convocationProche"
                                            :key="convocation.txt_numdordre"
                                            class="p-2 h-10 bg-white border border-primary-only rounded-xl shadow-sm hover:bg-green-300 hover:text-white cursor-pointer flex justify-between items-center"
                                             @click="goToConvocation(convocation.txt_numdordre)"
                                        >
                                            <div class="flex items-center space-x-2">
                                                <p class="font-medium text-sm text-primary-txt"> 
                                                    Dans 48H : 📅 {{ formatDateDMY(convocation.dt_date) }} à {{ convocation.txt_heure }}
                                                </p>
                                            </div>
                                        </li>
                                    </ul>

                                    <p v-else class="text-sm text-primary-txt p-2 bg-white border border-primary-only rounded-xl shadow-sm italic">
                                        Pas de convocation dans les 48h
                                    </p>
 
                                    <form @submit.prevent class="flex flex-wrap items-center gap-2">
                                        <BaseInput v-model="filters.txt_numdossier" type="search" maxlength="4" placeholder="Mois" wrapper-class="w-20" />
                                        <BaseInput v-model="filters.mois" type="search" maxlength="4" placeholder="Mois" wrapper-class="w-20" />
                                        <BaseInput v-model="filters.annee" type="search" maxlength="4" placeholder="Années" wrapper-class="w-20" />
                                        <BaseInput v-model="filters.numeroTF" type="search" placeholder="N° Titre" wrapper-class="w-28" />
                                        <BaseInput v-model="filters.numLot" type="search" placeholder="N° LOT" wrapper-class="w-28" />
                                        <BaseInput v-model="filters.prenom" type="search" placeholder="Prénom Nom" wrapper-class="w-32"/>
                                        <BaseInput v-model="filters.numNICAD" type="search" placeholder="N° NICAD" wrapper-class="w-28" />
                                        <BaseInput v-model="filters.reference" type="search" placeholder="Ref.C.A" wrapper-class="w-28" />
                                        <BaseSelect
                                            v-model="filters.designation"
                                            :options="uniqueDesignations"
                                            placeholder="Tous"
                                            wrapper-class="w-36"
                                        />
                                    </form>
                                </div>
                            </div>
 
                            <div class="p-4">
                                <div class="max-h-[600px] overflow-x-auto shadow-md rounded-xl">
                                    <table class="table table-auto w-full text-base text-left text-primary-txt">
                                        <thead class="sticky top-0 z-10 text-primary-txt text-font-bold text-center uppercase bg-primary-form bg-gray-50 dark:bg-gray-700 dark:text-primary-txt whitespace-nowrap bg-primary-only">
                                            <tr class="h-10">
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N°</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° d'ordre d'arrivée</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Types de Courrier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° BE</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° Dossier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Date Récéption</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Categorie</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° Courier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Date Courrier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Référence</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Caractère</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Designation</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Prénom Nom</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° LOT</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Superficie</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Titre foncier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° Titre Mère</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Lotissement</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Date</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Heure</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Lieu</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Nombre Pièce</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Objet</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Expéditeur</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Agent Traiteur</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Observation</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">PDF</th>
                                                <th class="px-2 py-2 text-xs text-center bg-primary-only font-semibold whitespace-nowrap">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(arrivee, index) in paginatedArrivees"
                                                :key="arrivee.id"
                                                :ref="el => (rowRefs[arrivee.txt_numdordre] = el)"
                                                class="h-8 text-xs hover:bg-primary hover:text-primary font-semibold text-primary-txt text-center"
                                            >
                                                <td class="sticky left-0 z-8 text-xs border bg-white px-2 py-1 whitespace-nowrap">{{ ((pagination.meta?.current_page || 1) - 1) * (pagination.meta?.per_page || 15) + index + 1 }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap" :class="arrivee.isLateConvocation ? 'text-primary font-bold' : ''">{{ arrivee.txt_numdordre }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_typecourrier || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numbordereau || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap"><span class="font-mono">{{ arrivee.txt_numdossier || '' }}</span></td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_datearrivee) || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_categorie || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numcourier || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_datecourier) || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_reference || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_caractere || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_designation || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_prenomNom || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numLot || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_superficie || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numTitreFoncier || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numTitreMere || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_lotissement || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_date) || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_heure || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_lieu || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_nombrepiece || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_objet || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_expediteur || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_agenttraiteur || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_observation || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">
                                                    <a
                                                        v-if="arrivee.fichierPDF"
                                                        :href="`/storage/${arrivee.fichierPDF}`"
                                                        target="_blank"
                                                        class="text-primary underline flex justify-center items-center"
                                                    >                                                                    
                                                        <img src="/iconePDF.png" alt="PDF" class="w-8 h-8 mx-auto">
                                                    </a>
                                                    <span v-else class="text-primary-txt/60 italic">Aucun PDF</span>
                                                </td>
                                                <td class="sticky right-0 z-8 px-2 py-1 border bg-white whitespace-nowrap">
                                                    <div class="flex justify-center gap-2">
                                                        <BoutonModifier :href="route('arrivee.edit', arrivee.id)" />
                                                        <BoutonSupprimer @click="() => supprimerCourrierArrivee(arrivee)" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!filtereArrivees?.length">
                                                <td colspan="27" class="text-center py-12 text-primary-txt text-base">
                                                    Aucun courrier arrivée trouvé
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="p-4 border-t flex flex-wrap justify-between items-center gap-4 bg-gray-50 rounded-b-xl">
                                <div class="flex gap-1 flex-wrap">
                                    <button
                                        @click="prevPage"
                                        :disabled="!pagination.links?.prev"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed bg-gray-200 hover:bg-gray-300 text-primary-txt"
                                    >
                                        ← Précédent
                                    </button>

                                    <button
                                        v-for="(link, linkIndex) in pagination.links"
                                        :key="linkIndex"
                                        @click="goToPage(link.url)"
                                        :disabled="!link.url"
                                        :class="[
                                            'px-3.5 py-1.5 rounded-lg text-sm font-medium transition',
                                            link.active
                                                ? 'bg-primary text-white shadow-md'
                                                : 'bg-gray-200 hover:bg-gray-300 text-primary-txt',
                                            !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                        ]"
                                        v-html="link.label"
                                    >
                                    </button>

                                    <button
                                        @click="nextPage"
                                        :disabled="!pagination.links?.next"
                                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition disabled:opacity-40 disabled:cursor-not-allowed bg-gray-200 hover:bg-gray-300 text-primary-txt"
                                    >
                                        Suivant →
                                    </button>
                                </div>
                            </div>
                        </div><br>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>

.animate-fade-in {
    animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

table tr td,
table tr th {
    white-space: nowrap !important;
    padding: 10px 10px !important;
    font-size: 0.75rem !important;
}

thead th {
    white-space: nowrap !important;
    padding: 10px 10px !important;
    font-size: 0.70rem !important;
    background-color: #E6C5A2;
}

tr:hover td {
    background-color: #f8fafc;
}

button:active:not(:disabled) {
    transform: scale(0.95);
}


</style>
 
 
