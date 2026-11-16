<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import BaseInput from "@/Components/BaseInput.vue";
import BaseSelect from "@/Components/BaseSelect.vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { reactive } from "vue";
import { route } from "ziggy-js";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";

defineOptions({ layout: DefaultLayout });

const props = defineProps({
    disponibles: { type: Number, default: 0 },
    agents: { type: Array, default: () => [] },
    enCours: { type: Array, default: () => [] },
    traites: { type: Array, default: () => [] },
    signes: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ en_cours: 0, en_retard: 0, traites: 0, signes: 0 }),
    },
    mesDossiers: { type: Array, default: () => [] },
});

const form = useForm({
    origine: "dossier",
    txt_num_dossier: "",
    user_id: "",
});

const origines = [
    { value: "dossier", label: "Dossier du secrétariat" },
    { value: "arrivee", label: "Courrier arrivée" },
    { value: "depart", label: "Courrier départ" },
];

const indication = {
    dossier: "Numéro de dossier",
    arrivee: "N° d'ordre ou N° de dossier foncier",
    depart: "N° d'ordre ou N° de dossier foncier",
};
const reaffectations = reactive({});
const envoi = reactive({});

function attribuer() {
    form.post(route("traitementDossier.store"), {
        preserveScroll: true,
        onSuccess: () => {
            const origine = form.origine;
            form.reset();
            form.origine = origine;
        },
    });
}

function reattribuer(id) {
    envoi[id] = true;
    router.put(route("traitementDossier.reattribuer", id), {
        user_id: reaffectations[id],
    }, {
        preserveScroll: true,
        onFinish: () => {
            envoi[id] = false;
        },
    });
}

function signer(id) {
    envoi[id] = true;
    router.post(route("traitementDossier.signer", id), {}, {
        preserveScroll: true,
        onFinish: () => {
            envoi[id] = false;
        },
    });
}

function annuler(id) {
    if (!window.confirm("Annuler la signature de ce dossier ? Il retournera en attente de signature.")) {
        return;
    }
    envoi[id] = true;
    router.post(route("traitementDossier.annulerSignature", id), {}, {
        preserveScroll: true,
        onFinish: () => {
            envoi[id] = false;
        },
    });
}

const cartes = [
    { cle: "en_cours", libelle: "Attribués en cours" },
    { cle: "en_retard", libelle: "Signalés (+ 10 jours)" },
    { cle: "traites", libelle: "Traités à signer" },
    { cle: "signes", libelle: "Signés" },
];
</script>

<template>
    <Head title="Traitement des dossiers" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">Suivi des dossiers attribués</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto w-full max-w-8xl space-y-6 px-4">
                <div class="rounded-xl bg-white p-6 shadow-md">
                    <p class="text-xl text-primary-txt">
                        L’attribution couvre les dossiers du secrétariat, les courriers d’arrivée et les courriers de départ.
                        Seul l’agent désigné peut valider le sien. La signature est réservée au chef de bureau.
                        Un dossier non traité après 10 jours est signalé ici et un rappel est envoyé à l’agent.
                    </p>
                    <a :href="route('traitementDossier.create')" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">
                        Voir les dossiers qui me sont attribués
                    </a>
                </div>

                <div v-if="mesDossiers.length" class="rounded-xl border border-amber-300 bg-amber-50 p-5 shadow-md">
                    <p class="text-lg font-semibold text-primary-txt">
                        {{ mesDossiers.length === 1
                            ? "Un dossier vous est attribué"
                            : mesDossiers.length + " dossiers vous sont attribués" }}
                    </p>
                    <p class="mt-1 text-sm text-primary-txt">
                        Vous êtes administrateur et agent de ces dossiers. Ils restent à traiter dans vos dossiers personnels.
                    </p>
                    <ul class="mt-3 space-y-1 text-sm text-primary-txt">
                        <li v-for="dossier in mesDossiers" :key="dossier.id">
                            <span class="font-semibold">{{ dossier.origine_libelle }} n° {{ dossier.numero }}</span>
                            <span v-if="dossier.en_retard" class="ml-2 font-bold text-red-600">En retard</span>
                        </li>
                    </ul>
                    <a :href="route('traitementDossier.create')" class="mt-3 inline-block text-sm font-semibold text-primary hover:underline">
                        Traiter mes dossiers
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div v-for="carte in cartes" :key="carte.cle" class="rounded-xl bg-white p-4 shadow-md">
                        <p class="text-xs uppercase tracking-wide text-primary-txt/70">{{ carte.libelle }}</p>
                        <p class="mt-1 text-3xl font-bold text-primary-txt" :class="carte.cle === 'en_retard' && stats.en_retard > 0 ? 'text-red-600' : ''">
                            {{ stats[carte.cle] }}
                        </p>
                    </div>
                </div>

                <form class="rounded-xl bg-white p-6 shadow-md" @submit.prevent="attribuer">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Attribuer un dossier ou un courrier</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <BaseSelect
                            label="Origine"
                            name="origine"
                            v-model="form.origine"
                            :options="origines"
                            :custom-error="form.errors.origine"
                        />
                        
                        <BaseInput
                            label="Numéro"
                            name="txt_num_dossier"
                            v-model="form.txt_num_dossier"
                            :placeholder="indication[form.origine] || 'Saisir le numéro exact'"
                            :error="form.errors.txt_num_dossier"
                        />

                        <BaseSelect
                            label="Agent"
                            name="user_id"
                            v-model="form.user_id"
                            placeholder="Choisir un utilisateur"
                            :options="agents"
                            :custom-error="form.errors.user_id"
                        />
                    </div>
                    <button
                        type="submit"
                        class="mt-4 rounded-lg bg-primary px-5 py-2 font-semibold text-white disabled:opacity-50"
                        :disabled="form.processing || disponibles === 0"
                    >
                        Attribuer
                    </button>
                    <p v-if="disponibles === 0" class="mt-2 text-sm text-primary-txt/70">Tous les dossiers ont déjà une attribution.</p>
                    <p v-else class="mt-2 text-sm text-primary-txt/70">{{ disponibles }} dossier(s) ou courrier(s) peuvent encore être attribués.</p>
                </form>

                <section class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Dossiers attribués en cours</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Dossier</th>
                                    <th class="px-3 py-2">Agent</th>
                                    <th class="px-3 py-2">Échéance</th>
                                    <th class="px-3 py-2">État</th>
                                    <th class="px-3 py-2">Réattribuer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="enCours.length === 0">
                                    <td colspan="5" class="px-3 py-6 text-center">Aucun dossier en cours.</td>
                                </tr>
                                <tr v-for="ligne in enCours" :key="ligne.id" :class="ligne.en_retard ? 'bg-red-50' : 'bg-white'">
                                    <td class="border-b px-3 py-3">
                                        <div class="text-xs uppercase tracking-wide">{{ ligne.origine_libelle }}</div>
                                        <div class="font-semibold">{{ ligne.numero }}</div>
                                        <div class="text-xs">{{ ligne.detail || ligne.service || "—" }}</div>
                                    </td>
                                    <td class="border-b px-3 py-3">
                                        {{ ligne.agent }}
                                        <span v-if="ligne.a_moi" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Vous</span>
                                    </td>
                                    <td class="border-b px-3 py-3">{{ ligne.echeance }}</td>
                                    <td class="border-b px-3 py-3">
                                        <span v-if="ligne.en_retard" class="font-bold text-red-600">En retard</span>
                                        <span v-else>{{ ligne.jours_restants }} j restants</span>
                                        <div v-if="ligne.rappel_envoye" class="text-xs">Rappel envoyé</div>
                                        <div v-if="!ligne.nicad_present" class="text-xs text-amber-700">{{ ligne.preuve_absente }}</div>
                                    </td>
                                    <td class="border-b px-3 py-3">
                                        <div class="flex min-w-56 items-center gap-2">
                                            <select v-model="reaffectations[ligne.id]" class="w-full rounded-lg border px-2 py-1">
                                                <option value="">Agent</option>
                                                <option v-for="agent in agents" :key="agent.value" :value="agent.value">{{ agent.label }}</option>
                                            </select>
                                            <button
                                                type="button"
                                                class="rounded-lg bg-primary px-3 py-1 text-white disabled:opacity-50"
                                                :disabled="!reaffectations[ligne.id] || envoi[ligne.id]"
                                                @click="reattribuer(ligne.id)"
                                            >
                                                OK
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Dossiers traités, en attente de signature</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Dossier</th>
                                    <th class="px-3 py-2">Agent</th>
                                    <th class="px-3 py-2">Validé le</th>
                                    <th class="px-3 py-2">Signature</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="traites.length === 0">
                                    <td colspan="4" class="px-3 py-6 text-center">Aucun dossier en attente de signature.</td>
                                </tr>
                                <tr v-for="ligne in traites" :key="ligne.id">
                                    <td class="border-b px-3 py-3">
                                        <div class="text-xs uppercase tracking-wide">{{ ligne.origine_libelle }}</div>
                                        <div class="font-semibold">{{ ligne.numero }}</div>
                                    </td>
                                    <td class="border-b px-3 py-3">
                                        {{ ligne.agent }}
                                        <span v-if="ligne.a_moi" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Vous</span>
                                    </td>
                                    <td class="border-b px-3 py-3">{{ ligne.traite_le }}</td>
                                    <td class="border-b px-3 py-3">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-emerald-600 px-3 py-1 font-semibold text-white disabled:opacity-50"
                                            :disabled="envoi[ligne.id]"
                                            @click="signer(ligne.id)"
                                        >
                                            Signer
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Dossiers signés</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Dossier</th>
                                    <th class="px-3 py-2">Agent</th>
                                    <th class="px-3 py-2">Signé le</th>
                                    <th class="px-3 py-2">Chef de bureau</th>
                                    <th class="px-3 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="signes.length === 0">
                                    <td colspan="5" class="px-3 py-6 text-center">Aucun dossier signé.</td>
                                </tr>
                                <tr v-for="ligne in signes" :key="ligne.id">
                                    <td class="border-b px-3 py-3">
                                        <div class="text-xs uppercase tracking-wide">{{ ligne.origine_libelle }}</div>
                                        <div class="font-semibold">{{ ligne.numero }}</div>
                                    </td>
                                    <td class="border-b px-3 py-3">
                                        {{ ligne.agent }}
                                        <span v-if="ligne.a_moi" class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Vous</span>
                                    </td>
                                    <td class="border-b px-3 py-3">{{ ligne.signe_le }}</td>
                                    <td class="border-b px-3 py-3">{{ ligne.signataire }}</td>
                                    <td class="border-b px-3 py-3">
                                        <button
                                            type="button"
                                            class="rounded-lg bg-red-600 px-3 py-1 font-semibold text-white disabled:opacity-50"
                                            :disabled="envoi[ligne.id]"
                                            @click="annuler(ligne.id)"
                                        >
                                            Annuler la signature
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
