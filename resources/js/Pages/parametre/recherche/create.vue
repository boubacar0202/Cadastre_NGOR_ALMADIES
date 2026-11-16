<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BaseInput from '@/Components/BaseInput.vue';
import BaseSelect from '@/Components/BaseSelect.vue';
import { computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineOptions({ layout: DefaultLayout });

const props = defineProps({
    lancee: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    totaux: {
        type: Object,
        default: () => ({ arrivees: 0, departs: 0, parcelles: 0, total: 0 }),
    },
    arrivees: { type: Object, default: () => ({ data: [], total: 0 }) },
    departs: { type: Object, default: () => ({ data: [], total: 0 }) },
    parcelles: { type: Object, default: () => ({ data: [], total: 0 }) },
});

const filtres = useForm({
    terme: props.filters?.terme || '',
    termeOption: props.filters?.termeOption || '',
    annee: props.filters?.annee || '',
    origine: props.filters?.origine || 'tous',
    traitement: props.filters?.traitement || 'tous',
});

const origines = [
    { value: 'tous', label: 'Arrivées, départs et parcelles' },
    { value: 'arrivee', label: 'Courriers arrivée' },
    { value: 'depart', label: 'Courriers départ' },
    { value: 'parcelle', label: 'Parcelles' },
];

const traitements = [
    { value: 'tous', label: 'Tous les fichiers' },
    { value: 'traites', label: 'Fichiers traités' },
];

const champs = [
    { value: '', label: 'Tous les champs' },
    { value: 'nom', label: 'Prénom / Nom' },
    { value: 'lot', label: 'N° lot' },
    { value: 'titre', label: 'N° titre foncier' },
    { value: 'NICAD', label: 'NICAD' },
];

const indication = {
    '': 'Nom, objet, désignation, lot, titre, NICAD',
    nom: 'Nom de la personne',
    lot: 'N° de lot',
    titre: 'N° de titre',
    NICAD: 'NICAD',
};

const origineAffichee = computed(() => props.filters?.origine || 'tous');
const traitementAffiche = computed(() => props.filters?.traitement || 'tous');
const voirArrivees = computed(() => (
    traitementAffiche.value === 'traites'
        ? origineAffichee.value !== 'parcelle'
        : ['tous', 'arrivee'].includes(origineAffichee.value)
));
const voirDeparts = computed(() => (
    traitementAffiche.value === 'traites'
        ? origineAffichee.value !== 'parcelle'
        : ['tous', 'depart'].includes(origineAffichee.value)
));
const voirParcelles = computed(() => traitementAffiche.value !== 'traites' && ['tous', 'parcelle'].includes(origineAffichee.value));

function retablir() {
    filtres.terme = '';
    filtres.termeOption = '';
    filtres.annee = '';
    filtres.origine = 'tous';
    filtres.traitement = 'tous';
    filtres.clearErrors();
    router.get(route('parametre.rechercheAvancee.create'), {}, {
        preserveScroll: true,
        replace: true,
    });
}

function rechercher(pages = {}) {
    filtres.transform((data) => ({
        terme: data.terme,
        termeOption: data.termeOption,
        annee: data.annee,
        origine: data.origine,
        traitement: data.traitement,
        page_arrivee: pages.page_arrivee || 1,
        page_depart: pages.page_depart || 1,
        page_parcelle: pages.page_parcelle || 1,
    })).get(route('parametre.rechercheAvancee.create'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function changerPage(cle, page) {
    if (!page) return;
    rechercher({
        page_arrivee: cle === 'page_arrivee' ? page : (props.arrivees.current_page || 1),
        page_depart: cle === 'page_depart' ? page : (props.departs.current_page || 1),
        page_parcelle: cle === 'page_parcelle' ? page : (props.parcelles.current_page || 1),
    });
}

function pages(liste) {
    const current = liste.current_page || 1;
    const last = Math.max(1, liste.last_page || 1);
    const ensemble = new Set([1, last, current, current - 1, current + 1]);
    return [...ensemble].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);
}
</script>

<template>
    <Head title="Recherche avancée" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">Recherche avancée</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto w-full max-w-8xl space-y-6 px-4">
                <form class="rounded-xl bg-white p-6 shadow-md" @submit.prevent="rechercher()">
                    <div class="flex justify-center items-center gap-4 mb-4">
                        <p class="mb-4 text-center text-lg font-bold text-primary-txt">
                            Interrogez la base de données, les courriers de départ et les parcelles directement
                            sans passer par l'interface de recherche.
                        </p>
                        <button type="button" class="w-24 h-7 justify-center flex items-center text-white bg-gradient-to-r from-primary via-primary-dark to-primary
                            hover:bg-gradient-to-br focus:ring-4 focus:outline-none focus:ring-blue-300
                            dark:focus:ring-blue-800 shadow-lg shadow-blue-500/50 dark:shadow-lg
                            dark:shadow-blue-800/80 font-medium rounded-lg text-xs px-3 py-1 text-center mb-2"
                            @click="retablir" size="medium">
                            Retablir
                        </button>
                    </div>
                    <div class="grid grid-cols-2 items-end gap-2 lg:grid-cols-12">
                        <BaseInput
                            label="Année"
                            name="annee"
                            wrapper-class="min-w-0 lg:col-span-1"
                            input-class="!h-9 !px-2 !text-sm"
                            font-size="0.75rem"
                            v-model="filtres.annee"
                            maxlength="4"
                            placeholder="année"
                            :error="filtres.errors.annee"
                        />
                        <BaseInput
                            label="Recherche"
                            name="terme"
                            wrapper-class="col-span-2 min-w-0 lg:col-span-3"
                            input-class="!h-9 !px-2.5 !text-sm"
                            font-size="0.75rem"
                            v-model="filtres.terme"
                            :placeholder="indication[filtres.termeOption] || indication['']"
                            :error="filtres.errors.terme"
                        />
                        <BaseSelect
                            label="Filtre"
                            name="termeOption"
                            wrapper-class="min-w-0 lg:col-span-2"
                            input-class="!h-9 !px-2.5 !text-sm"
                            font-size="0.75rem"
                            v-model="filtres.termeOption"
                            :options="champs"
                            placeholder="Tous les champs"
                            :custom-error="filtres.errors.termeOption"
                        />
                        <BaseSelect
                            label="Source"
                            name="origine"
                            wrapper-class="min-w-0 lg:col-span-3"
                            input-class="!h-9 !px-2.5 !text-sm"
                            font-size="0.75rem"
                            v-model="filtres.origine"
                            :options="origines"
                            :custom-error="filtres.errors.origine"
                        />
                        <BaseSelect
                            label="Traitement"
                            name="traitement"
                            wrapper-class="min-w-0 lg:col-span-3"
                            input-class="!h-9 !px-2.5 !text-sm"
                            font-size="0.75rem"
                            v-model="filtres.traitement"
                            :options="traitements"
                            :custom-error="filtres.errors.traitement"
                        />
                    </div>
                    <div class="mt-4 flex justify-center">
                        <button type="submit" class="w-64 h-10 justify-center flex items-center text-white bg-gradient-to-r from-primary via-primary-dark to-primary
                            hover:bg-gradient-to-br focus:ring-4 focus:outline-none focus:ring-blue-300
                            dark:focus:ring-blue-800 shadow-lg shadow-blue-500/50 dark:shadow-lg
                            dark:shadow-blue-800/80 font-medium rounded-lg text-xs px-3 py-1 text-center mb-2"
                            size="medium">
                            Rechercher
                        </button>
                    </div>
                </form>

                <div v-if="lancee" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-xl bg-white p-4 shadow-md">
                        <p class="text-xs uppercase tracking-wide text-primary-txt/70">Total</p>
                        <p class="mt-1 text-3xl font-bold text-primary-txt">{{ totaux.total }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-md">
                        <p class="text-xs uppercase tracking-wide text-primary-txt/70">Arrivées</p>
                        <p class="mt-1 text-3xl font-bold text-primary-txt">{{ totaux.arrivees }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-md">
                        <p class="text-xs uppercase tracking-wide text-primary-txt/70">Départs</p>
                        <p class="mt-1 text-3xl font-bold text-primary-txt">{{ totaux.departs }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 shadow-md">
                        <p class="text-xs uppercase tracking-wide text-primary-txt/70">Parcelles</p>
                        <p class="mt-1 text-3xl font-bold text-primary-txt">{{ totaux.parcelles }}</p>
                    </div>
                </div>

                <section v-if="lancee && voirArrivees" class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">
                        Courriers d'arrivée{{ traitementAffiche === 'traites' ? ' traités' : '' }} ({{ totaux.arrivees }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">N° ordre</th>
                                    <th class="px-3 py-2">Désignation / objet</th>
                                    <th class="px-3 py-2">Personne</th>
                                    <th class="px-3 py-2">Lot</th>
                                    <th class="px-3 py-2">Titre</th>
                                    <th class="px-3 py-2">Référence</th>
                                    <th class="px-3 py-2">NICAD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!arrivees.data?.length">
                                    <td colspan="8" class="px-3 py-6 text-center">Aucun courrier d'arrivée.</td>
                                </tr>
                                <tr v-for="ligne in arrivees.data" :key="ligne.id">
                                    <td class="border-b px-3 py-2">{{ ligne.date || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.numero || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.designation || ligne.objet || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.personne || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.lot || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.titre || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.reference || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.nicad || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-1">
                        <button
                            v-for="page in pages(arrivees)"
                            :key="page"
                            type="button"
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="page === arrivees.current_page ? 'bg-primary text-white' : 'bg-gray-200 text-primary-txt'"
                            @click="changerPage('page_arrivee', page)"
                        >
                            {{ page }}
                        </button>
                    </div>
                </section>

                <section v-if="lancee && voirDeparts" class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">
                        Courriers de départ{{ traitementAffiche === 'traites' ? ' traités' : '' }} ({{ totaux.departs }})
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Date</th>
                                    <th class="px-3 py-2">N° ordre</th>
                                    <th class="px-3 py-2">Objet</th>
                                    <th class="px-3 py-2">Personne</th>
                                    <th class="px-3 py-2">Lot</th>
                                    <th class="px-3 py-2">Titre</th>
                                    <th class="px-3 py-2">Référence</th>
                                    <th class="px-3 py-2">NICAD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!departs.data?.length">
                                    <td colspan="8" class="px-3 py-6 text-center">Aucun courrier de départ.</td>
                                </tr>
                                <tr v-for="ligne in departs.data" :key="ligne.id">
                                    <td class="border-b px-3 py-2">{{ ligne.date || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.numero || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.objet || ligne.designation || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.personne || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.lot || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.titre || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.reference || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.nicad || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-1">
                        <button
                            v-for="page in pages(departs)"
                            :key="page"
                            type="button"
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="page === departs.current_page ? 'bg-primary text-white' : 'bg-gray-200 text-primary-txt'"
                            @click="changerPage('page_depart', page)"
                        >
                            {{ page }}
                        </button>
                    </div>
                </section>

                <section v-if="lancee && voirParcelles" class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Parcelles ({{ totaux.parcelles }})</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Dossier</th>
                                    <th class="px-3 py-2">Personne</th>
                                    <th class="px-3 py-2">Lotissement</th>
                                    <th class="px-3 py-2">Lot</th>
                                    <th class="px-3 py-2">Section</th>
                                    <th class="px-3 py-2">Parcelle</th>
                                    <th class="px-3 py-2">Titre</th>
                                    <th class="px-3 py-2">NICAD</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!parcelles.data?.length">
                                    <td colspan="8" class="px-3 py-6 text-center">Aucune parcelle.</td>
                                </tr>
                                <tr v-for="ligne in parcelles.data" :key="ligne.id">
                                    <td class="border-b px-3 py-2">{{ ligne.dossier || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.personne || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.lotissement || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.lot || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.section || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.parcelle || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.titre || '—' }}</td>
                                    <td class="border-b px-3 py-2">{{ ligne.nicad || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-1">
                        <button
                            v-for="page in pages(parcelles)"
                            :key="page"
                            type="button"
                            class="rounded-lg px-3 py-1 text-sm"
                            :class="page === parcelles.current_page ? 'bg-primary text-white' : 'bg-gray-200 text-primary-txt'"
                            @click="changerPage('page_parcelle', page)"
                        >
                            {{ page }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
