<script setup>
import BoutonSupprimer from "@/Components/BoutonSupprimer.vue";
import BoutonModifier from "@/Components/BoutonModifier.vue";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';

const props = defineProps({
    departs: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0 }) },
    objetcourrier: {
        type: Array,
        default: () => [] },
    stats: {
        type: Object,
        default: () => ({ total: 0 }) },
    filters: {
        type: Object,
        default: () => ({}) } });

const filtres = ref({
    numeroTF: props.filters?.numeroTF || '',
    numLot: props.filters?.numLot || '',
    prenom: props.filters?.prenom || '',
    annee: props.filters?.annee || '',
    numero: props.filters?.numero || '',
    reference: props.filters?.reference || '',
    objetcourrier: props.filters?.objetcourrier || '' });

const uniqueObjetcourrier = computed(() => {
    const liste = Array.isArray(props.objetcourrier)
        ? props.objetcourrier
        : Object.values(props.objetcourrier || {});

    return [
        { value: '', label: 'Tous les objets' },
        ...liste.map((objet) => (
            objet && typeof objet === 'object'
                ? objet
                : { value: objet, label: objet }
        )),
    ];
});

const pageMeta = computed(() => props.departs?.meta || props.departs || {});
const paginatedDeparts = computed(() => (
    Array.isArray(props.departs) ? props.departs : (props.departs?.data || [])
));
const filtereDeparts = paginatedDeparts;

const pagination = computed(() => {
    const current = pageMeta.value.current_page || 1;
    const last = Math.max(1, pageMeta.value.last_page || 1);
    const perPage = pageMeta.value.per_page || 15;
    const pages = new Set([1, last, current, current - 1, current + 1, current - 2, current + 2]);
    const sorted = [...pages].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);
    const links = [];
    let previous = 0;

    sorted.forEach((page) => {
        if (page - previous > 1) {
            links.push({ label: '...', url: null, active: false });
        }
        links.push({ label: String(page), url: `?page=${page}`, active: page === current });
        previous = page;
    });

    links.prev = current > 1 ? `?page=${current - 1}` : null;
    links.next = current < last ? `?page=${current + 1}` : null;

    return {
        links,
        meta: { current_page: current, per_page: perPage } };
});

const rechercher = (page = 1) => {
    router.get(route('instancedepart.create'), {
        ...filtres.value,
        page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['departs'] });
};

const debouncedSearch = debounce((page = 1) => rechercher(page), 350);

watch(filtres, () => debouncedSearch(1), { deep: true });
onBeforeUnmount(() => debouncedSearch.cancel());

const goToPage = (url) => {
    if (!url) return;
    const next = Number(new URLSearchParams(url.split('?')[1]).get('page'));
    if (next) rechercher(next);
};

const prevPage = () => {
    if (pagination.value.links?.prev) goToPage(pagination.value.links.prev);
};

const nextPage = () => {
    if (pagination.value.links?.next) goToPage(pagination.value.links.next);
};

const totalCourrier = computed(() => Number((props.departs?.meta || props.departs)?.total || 0));
 
// filtrer les instances par categorie  
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric' });
};
 
function supprimerCourrierDepart(depart) {
    if (confirm(`Voulez-vous vraiment supprimer le courrier départ N° ${depart.txt_numdordrecd} ?`)) {
        router.delete(route('instancedepart.destroyDepart', depart.id), {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Courrier départ supprimé avec succès');
            },
            onError: (errors) => {
                console.error('Erreur lors de la suppression', errors);
            }
        });
    }
}

</script>

<template>
    <Head title="Instances Départs">
        <link rel="icon" sizes="512x512" href="/logo-01.png">
    </Head>

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl text-primary-txt font-semibold leading-tight"
            >
                Liste des Courriers Departs
            </h2>
            
            <!-- Vérification pour afficher un message si aucune donnée -->
            <template v-if="!stats?.total">
                <p>Aucun Instance Départ n'est trouvé.</p>
            </template>
           
        </template>

        <div class="py-12">
            <div class="flex justify-center">
                <div class="w-full max-w-8xl">
                    <div class="bg-white shadow-md rounded-xl">
                        <div class="p-4 border-b bg-primary rounded-t-xl">
                            <h1 class="text-lg text-white font-semibold">Liste des courriers départs</h1>
                        </div>

                        <div class="p-6">
                            <div class="p-4 bg-primary-form border border-primary-only rounded-xl">
                                <div class="flex flex-wrap items-center justify-between gap-3"> 
                                    <h1 class="text-lg text-primary-txt font-bold">
                                        Liste des Courriers Départs : 
                                        <span v-if="totalCourrier>0" class="text-primary-txt">
                                            ({{ totalCourrier }})
                                        </span>
                                        <span v-else class="text-red-600">
                                            Aucun enregistrement
                                        </span> 
                                    </h1>  

                                    <form @submit.prevent class="flex flex-wrap items-center gap-2">

                                        <BaseInput v-model="filtres.numeroTF" type="search" placeholder="N° Titre" wrapper-class="w-28" />
                                        <BaseInput v-model="filtres.numLot" type="search" placeholder="N° LOT" wrapper-class="w-28" />
                                        <BaseInput v-model="filtres.prenom" type="search" placeholder="Prénom Nom" wrapper-class="w-32" />
                                        <BaseInput v-model="filtres.annee" type="search"maxlength="4" placeholder="année" wrapper-class="w-20" />
                                        <BaseInput
                                            v-model="filtres.numero"
                                            type="search"
                                            placeholder="N° Ordre"
                                            wrapper-class="w-28"
                                        />
                                        <BaseInput
                                            v-model="filtres.reference"
                                            type="search"
                                            placeholder="Référence C.A"
                                            wrapper-class="w-36"
                                        />
                                        <BaseSelect
                                            v-model="filtres.objetcourrier"
                                            :options="uniqueObjetcourrier"
                                            placeholder="Objet Courrier"
                                            wrapper-class="w-72"
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
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° d'ordre départ</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Avis</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° Dossier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Types de Courrier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° BE</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Date Courrier</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Categorie</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Référence C.Arrivée</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Nombre Pièce</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Référence</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Objet</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Destinataire</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Prénom Nom</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° LOT</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Superficie</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">N° TF</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Lotissement</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Date Envoie</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Référence Réception</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Observation</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Durée Traitement</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">Caractère</th>
                                                <th class="px-2 py-2 text-xs text-primary text-center font-semibold whitespace-nowrap">PDF</th>
                                                <th class="px-2 py-2 text-xs text-center bg-primary-only font-semibold whitespace-nowrap">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(depart, index) in paginatedDeparts"
                                                :key="depart.id"
                                                class="h-8 text-xs hover:bg-primary hover:text-primary font-semibold text-primary-txt text-center"
                                            >
                                                <td class="sticky left-0 z-8 text-xs border bg-white px-2 py-1 whitespace-nowrap">{{ ((pagination.meta?.current_page || 1) - 1) * (pagination.meta?.per_page || 15) + index + 1 }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_numdordrecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_avisReponsecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap"><span class="font-mono">{{ depart.txt_numdossiercd || '' }}</span></td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_typecourriercd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_numdordrecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(depart.dt_datecouriercd) || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_categoriecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_referencecourierarriveecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_nombrepiececd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_referencecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_objetcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_destinatairecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_prenomNomcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_numLotcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_superficiecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_numTitreFonciercd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_lotissementcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(depart.dt_dateenvoicd) || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_referencereceptioncd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_observationcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_dureetraitementcd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ depart.txt_caracterecd || '' }}</td>
                                                <td class="px-2 py-1 text-xs border whitespace-nowrap">
                                                    <a
                                                        v-if="depart.fichierPDFcd"
                                                        :href="`/storage/${depart.fichierPDFcd}`"
                                                        target="_blank"
                                                        class="text-primary underline flex justify-center items-center"
                                                    >
                                                        <img src="/iconePDF.png" alt="PDF" class="w-8 h-8 mx-auto">
                                                    </a>
                                                    <span v-else class="text-primary-txt/60 italic">Aucun PDF</span>
                                                </td>
                                                <td class="sticky right-0 z-8 px-2 py-1 border bg-white whitespace-nowrap">
                                                    <div class="flex justify-center gap-2">
                                                        <BoutonModifier :href="`/depart/editdepart/${depart.id}`" />
                                                        <BoutonSupprimer @click="() => supprimerCourrierDepart(depart)" />
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-if="!filtereDeparts?.length">
                                                <td colspan="25" class="text-center py-12 text-primary-txt text-base">
                                                    Aucun courrier départ trouvé
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
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
 