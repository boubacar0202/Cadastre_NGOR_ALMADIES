<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import { ref, computed, watch, onBeforeUnmount } from 'vue';
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
            total: 0,
        }),
    },
    designations: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, enRetard: 0, totalGeneral: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const filters = ref({
    mois: props.filters?.mois || '',
    annee: props.filters?.annee || '',
    numeroTF: props.filters?.numeroTF || '',
    numLot: props.filters?.numLot || '',
    prenom: props.filters?.prenom || '',
    reference: props.filters?.reference || '',
    designation: props.filters?.designation || '',
});

const uniqueDesignations = computed(() => {
    const liste = Array.isArray(props.designations)
        ? props.designations
        : Object.values(props.designations || {});

    return [
        { value: '', label: 'Toutes les désignations' },
        ...liste.map((designation) => (
            designation && typeof designation === 'object'
                ? designation
                : { value: designation, label: designation }
        )),
    ];
});

const pageMeta = computed(() => props.arrivees?.meta || props.arrivees || {});
const filteredInstance = computed(() => (
    Array.isArray(props.arrivees) ? props.arrivees : (props.arrivees?.data || [])
));

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
        meta: { current_page: current, per_page: perPage },
    };
});

const rechercher = (page = 1) => {
    router.get(route('instance.create'), {
        ...filters.value,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['arrivees', 'stats'],
    });
};

const debouncedSearch = debounce(() => rechercher(1), 350);

watch(filters, () => debouncedSearch(), { deep: true });
onBeforeUnmount(() => debouncedSearch.cancel());

const goToPage = (url) => {
    if (!url) return;
    const next = Number(new URLSearchParams(url.split('?')[1]).get('page'));
    if (next) rechercher(next);
};

const totalInstance = computed(() => props.stats?.enRetard || 0);
const totalCourrierInstance = computed(() => props.stats?.total || 0);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};
 
</script>

<template>
    <Head title="Instances">
        <link rel="icon" sizes="512x512" href="/logo-01.png">
    </Head>

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">
                Courriers en Instance
            </h2>
            
            <!-- Vérification pour afficher un message si aucune donnée -->
            <p v-if="!stats?.totalGeneral" class="mt-2 text-sm font-medium text-primary-txt">
                Aucun courrier arrivée trouvé.
            </p>
           
        </template>

        <div class="py-12">
            <div class="flex justify-center">
                <div class="w-full max-w-8xl">
                    <div class="bg-white shadow-md rounded-xl">
                        <div class="p-4 border-b bg-primary rounded-t-xl">
                            <h1 class="text-lg font-semibold text-white">Liste des courriers en instance</h1>
                        </div>

                        <div class="p-6">
                            <div class="rounded-xl border border-primary-only bg-primary-form p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h1 class="text-lg font-bold text-primary-txt">
                                        Instances 
                                        <span v-if="totalCourrierInstance>0" class="text-primary-txt">
                                            ({{ totalCourrierInstance }})
                                        </span>
                                        <span v-else class="text-red-600">
                                            Aucun enregistrement
                                        </span> 
                                    </h1>

                                    <h2 class="text-sm font-bold text-primary">
                                        Instances de plus de 10 jours :
                                        <span class="text-primary-dark">{{ totalInstance }}</span>
                                    </h2>

                                    <form @submit.prevent class="flex flex-wrap items-center gap-2">
                                        <BaseInput v-model="filters.mois" type="search" maxlength="2" placeholder="Mois" wrapper-class="w-20" />
                                        <BaseInput v-model="filters.annee" type="search" maxlength="4" placeholder="Années" wrapper-class="w-20" />
                                        <BaseInput v-model="filters.numeroTF" type="search" placeholder="N° Titre" wrapper-class="w-28" />
                                        <BaseInput v-model="filters.numLot" type="search" placeholder="N° LOT" wrapper-class="w-28" />
                                        <BaseInput v-model="filters.prenom" type="search" placeholder="Prénom Nom" wrapper-class="w-32" />
                                        <BaseInput v-model="filters.reference" type="search" placeholder="Référence courrier arrivée" wrapper-class="w-64" />
                                        <BaseSelect
                                            v-model="filters.designation"
                                            :options="uniqueDesignations"
                                            placeholder="Désignation"
                                            wrapper-class="w-44"
                                        />
                                    </form>
                                </div>
                            </div>

                            <div class="mt-4 max-h-[600px] overflow-x-auto shadow-md rounded-xl">
                                <table class="table table-auto w-full text-base text-left text-primary-txt">
                                    <thead class="sticky top-0 z-10 text-center uppercase whitespace-nowrap text-primary-txt bg-primary-form">
                                        <tr class="h-10">
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N°</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° d'ordre d'arrivée</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Types de Courrier</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° BE</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° Dossier</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Catégorie</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Date Arrivée</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° Courrier</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Date Courrier</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Référence</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Caractère</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Désignation</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Prénom Nom</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° LOT</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Superficie</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">N° TF</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Lotissement</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Date</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Heure</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Lieu</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Nombre Pièces</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Objet</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Expéditeur</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Agent Traiteur</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Observation</th>
                                            <th class="px-2 py-2 text-xs font-semibold text-center text-primary whitespace-nowrap">Fichier PDF</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <tr
                                            v-for="(arrivee, index) in filteredInstance"
                                            :key="arrivee.id"
                                            class="h-8 bg-white text-xs font-semibold text-center text-primary-txt hover:bg-primary-form"
                                        >
                                            <td class="sticky left-0 z-8 px-2 py-1 text-xs border bg-white whitespace-nowrap">{{ ((pagination.meta?.current_page || 1) - 1) * (pagination.meta?.per_page || 15) + index + 1 }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap" :class="arrivee.isOverdue ? 'text-red-600 font-bold' : ''">{{ arrivee.txt_numdordre }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_typecourrier || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numbordereau || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap"><span class="font-mono">{{ arrivee.txt_numdossier || '-' }}</span></td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_categorie || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_datearrivee) }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numcourier || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_datecourier) }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_reference || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_caractere || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_designation || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_prenomNom || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numLot || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_superficie || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_numTitreFoncier || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_lotissement || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ formatDate(arrivee.dt_date) }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_heure || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_lieu || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_nombrepiece || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_objet || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_expediteur || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_agenttraiteur || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">{{ arrivee.txt_observation || '-' }}</td>
                                            <td class="px-2 py-1 text-xs border whitespace-nowrap">
                                                <a
                                                    v-if="arrivee.fichierPDF"
                                                    :href="`/storage/${arrivee.fichierPDF}`"
                                                    target="_blank"
                                                    class="flex items-center justify-center text-primary underline"
                                                >
                                                    <img src="/iconePDF.png" alt="PDF" class="w-8 h-8 mx-auto">
                                                </a>
                                                <span v-else class="italic text-primary-txt/60">Aucun PDF</span>
                                            </td>
                                        </tr>
                                        <tr v-if="!filteredInstance.length">
                                            <td colspan="26" class="px-6 py-12 text-center text-base text-primary-txt">
                                                Aucun courrier en instance.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-1 border-t pt-4">
                                <button
                                    @click="goToPage(pagination.links?.prev)"
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
                                        link.active ? 'bg-primary text-white shadow-md' : 'bg-gray-200 hover:bg-gray-300 text-primary-txt',
                                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                    ]"
                                    v-html="link.label"
                                ></button>
                                <button
                                    @click="goToPage(pagination.links?.next)"
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
    </AuthenticatedLayout>
</template>
 
