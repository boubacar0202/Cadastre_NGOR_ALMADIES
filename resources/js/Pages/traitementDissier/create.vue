<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue"; 
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, reactive, ref } from "vue";
import { route } from "ziggy-js";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";

defineOptions({ layout: DefaultLayout });

const props = defineProps({
    attributions: {
        type: Array,
        default: () => [],
    },
    traites: {
        type: Array,
        default: () => [],
    }, 
    communes: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const estAdmin = computed(() => page.props.auth?.user?.role === "admin");
const preuves = {
    nicad: { label: "NICAD du dossier", placeholder: "Saisir le NICAD" },
    courrier: { label: "N° du courrier", placeholder: "Saisir le numéro du courrier" },
    reference: { label: "Référence du courrier", placeholder: "Saisir la référence" },
};
const nicads = reactive({});
const saisies = reactive({});
const erreursSaisie = reactive({});
const erreur = ref("");
const erreurId = ref(null);
const envoi = ref(null);

function saisie(id) {
    if (!saisies[id]) {
        saisies[id] = { section: "", parcelle: "", appartement: "" };
    }
    return saisies[id];
}

function chiffres(valeur, longueur) {
    return String(valeur || "").replace(/\D/g, "").slice(0, longueur);
}

function messageLongueur(valeur, longueur) {
    if (!valeur) return "";
    return valeur.length === longueur ? "" : `Exactement ${longueur} chiffres.`;
}

function communeDuDossier(attribution) {
    return props.communes.find((commune) => commune.id == attribution.slt_commune);
}

function peutSaisir(attribution) {
    if (attribution.verrouille) return false;
    if (attribution.preuve === "nicad") {
        return Boolean(String(communeDuDossier(attribution)?.txt_codeCommune || "").trim());
    }
    return Boolean(attribution.preuve_presente);
}

function nicadDe(attribution) {
    const valeurs = saisie(attribution.id);
    const prefix = String(communeDuDossier(attribution)?.txt_codeCommune || "").replace(/\s/g, "");
    const section = String(valeurs.section || "").replace(/\s/g, "");
    const parcelle = String(valeurs.parcelle || "").replace(/\s/g, "");
    const appart = String(valeurs.appartement || "").replace(/\s/g, "");
    const cleaned = (prefix + section + parcelle + appart).replace(/\s/g, "");

    return cleaned.length >= 9 ? cleaned : "";
}

function valider(attribution) {
    erreur.value = "";
    erreurId.value = attribution.id;
    erreursSaisie[attribution.id] = { section: "", parcelle: "", appartement: "" };

    if (attribution.preuve === "nicad") {
        const valeurs = saisie(attribution.id);
        erreursSaisie[attribution.id] = {
            section: messageLongueur(valeurs.section, 3) || (valeurs.section ? "" : "Le N° de section est requis."),
            parcelle: messageLongueur(valeurs.parcelle, 5) || (valeurs.parcelle ? "" : "Le N° de parcelle est requis."),
            appartement: valeurs.appartement ? messageLongueur(valeurs.appartement, 3) : "",
        };
        if (Object.values(erreursSaisie[attribution.id]).some(Boolean) || !nicadDe(attribution)) {
        return;
    }
    }

    envoi.value = attribution.id;
    router.post(route("traitementDossier.valider", attribution.id), attribution.preuve === "nicad"
        ? {
            txt_num_section: saisie(attribution.id).section,
            txt_num_parcelle: saisie(attribution.id).parcelle,
            txt_appartement: saisie(attribution.id).appartement,
            txt_nicad: nicadDe(attribution),
        }
        : { txt_nicad: nicads[attribution.id] || "" }, {
        preserveScroll: true,
        onError: (errors) => {
            erreur.value = errors.txt_nicad
                || errors.txt_num_section
                || errors.txt_num_parcelle
                || errors.txt_appartement
                || "La validation a échoué.";
        },
        onFinish: () => {
            envoi.value = null;
        },
    });
        }
</script>

<template>
    <Head title="Mes dossiers" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">Dossiers qui me sont attribués</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto w-full max-w-8xl space-y-4 px-4">
                <div class="rounded-xl bg-white p-6 shadow-md">
                    <p class="text-md text-primary-txt">
                        Seuls vos dossiers et courriers apparaissent ici. Pour un dossier, le NICAD se constitue
                        comme au secrétariat : code commune, N° de section et N° de parcelle. Il est refusé
                        s’il est déjà porté par un autre dossier.
                    </p>
                    <a
                        v-if="estAdmin"
                        :href="route('traitementDossier.index')"
                        class="mt-3 inline-block text-xs font-semibold text-primary"
                    >
                        Retour au suivi administrateur
                    </a>
                            </div> 

                <div v-if="attributions.length === 0" class="rounded-xl bg-white p-10 text-center text-primary-txt shadow-md">
                    Aucun dossier ne vous est attribué pour le moment.
                </div>

                <article
                    v-for="attribution in attributions"
                    :key="attribution.id"
                    class="rounded-xl bg-white p-6 shadow-md"
                    :class="attribution.en_retard ? 'ring-2 ring-red-400' : ''"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-primary-txt">{{ attribution.origine_libelle }} n° {{ attribution.numero }}</h3>
                            <p class="text-sm text-primary-txt/80">
                                <span v-if="attribution.detail">{{ attribution.detail }}</span>
                                <span v-if="attribution.service"> · {{ attribution.service }}</span>
                            </p>
                        </div>  
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold uppercase"
                                :class="attribution.en_retard ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ attribution.en_retard ? "En retard" : "À traiter" }}
                            </span>
                            <Link
                                :href="route('traitementDossier.show', attribution.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 px-3 py-1.5 text-sm font-semibold text-primary-txt transition hover:bg-primary hover:text-white"
                            >
                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                Visualiser
                            </Link>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-primary-txt">
                        Attribué le {{ attribution.attribue_le }} · échéance le {{ attribution.echeance }}
                        <span v-if="attribution.en_retard"> · délai de 10 jours dépassé</span>
                        <span v-else> · {{ attribution.jours_restants }} jour(s) restant(s)</span>
                    </p>

                    <div v-if="attribution.preuve === 'nicad'" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-medium text-primary-txt">
                            N° Section
                            <input
                                :value="saisie(attribution.id).section"
                                type="text"
                                inputmode="numeric"
                                maxlength="3"
                                autocomplete="off"
                                :disabled="!peutSaisir(attribution)"
                                class="mt-1 w-full rounded-lg border border-primary/40 px-3 py-2 outline-none focus:border-primary"
                                @input="saisie(attribution.id).section = chiffres($event.target.value, 3)"
                            />
                            <span v-if="erreursSaisie[attribution.id]?.section" class="mt-1 block text-xs text-red-600">
                                {{ erreursSaisie[attribution.id].section }}
                            </span>
                        </label>
                        <label class="block text-sm font-medium text-primary-txt">
                            N° Parcelle
                            <input
                                :value="saisie(attribution.id).parcelle"
                                type="text"
                                inputmode="numeric"
                                maxlength="5"
                                autocomplete="off"
                                :disabled="!peutSaisir(attribution)"
                                class="mt-1 w-full rounded-lg border border-primary/40 px-3 py-2 outline-none focus:border-primary"
                                @input="saisie(attribution.id).parcelle = chiffres($event.target.value, 5)"
                            />
                            <span v-if="erreursSaisie[attribution.id]?.parcelle" class="mt-1 block text-xs text-red-600">
                                {{ erreursSaisie[attribution.id].parcelle }}
                            </span>
                        </label>
                        <label class="block text-sm font-medium text-primary-txt">
                            N° Appartement
                            <input
                                :value="saisie(attribution.id).appartement"
                                type="text"
                                inputmode="numeric"
                                maxlength="3"
                                autocomplete="off"
                                :disabled="!peutSaisir(attribution)"
                                class="mt-1 w-full rounded-lg border border-primary/40 px-3 py-2 outline-none focus:border-primary"
                                @input="saisie(attribution.id).appartement = chiffres($event.target.value, 3)"
                            />
                            <span v-if="erreursSaisie[attribution.id]?.appartement" class="mt-1 block text-xs text-red-600">
                                {{ erreursSaisie[attribution.id].appartement }}
                            </span>
                        </label>
                        <label class="block text-sm font-medium text-primary-txt">
                            NICAD
                            <input
                                :value="nicadDe(attribution)"
                                type="text"
                                            readonly 
                                class="mt-1 w-full rounded-lg border border-primary/40 bg-primary-form px-3 py-2"
                            />
                                        </label>
                                    </div>

                    <div v-else class="mt-4">
                        <label class="block text-sm font-medium text-primary-txt">
                            {{ preuves[attribution.preuve]?.label || "Pièce de contrôle" }}
                            <input
                                v-model="nicads[attribution.id]"
                                type="text"
                                autocomplete="off"
                                :disabled="!peutSaisir(attribution)"
                                class="mt-1 w-full rounded-lg border border-primary/40 px-3 py-2 outline-none focus:border-primary"
                                :placeholder="preuves[attribution.preuve]?.placeholder || 'Saisir la pièce de contrôle'"
                            />
                        </label>
                                </div> 

                    <button
                        type="button"
                        class="mt-4 h-10 rounded-lg bg-primary px-5 font-semibold text-white disabled:opacity-50"
                        :disabled="!peutSaisir(attribution) || envoi === attribution.id || (attribution.preuve === 'nicad' && !nicadDe(attribution))"
                        @click="valider(attribution)"
                    >
                        {{ envoi === attribution.id ? "Validation…" : "Valider" }}
                    </button>
                    <p v-if="attribution.preuve === 'nicad' && !peutSaisir(attribution) && !attribution.verrouille" class="mt-2 text-sm text-amber-700">
                        La commune du dossier n'a pas de code. Le NICAD ne peut pas être constitué.
                    </p>
                    <p v-else-if="attribution.preuve !== 'nicad' && !attribution.preuve_presente" class="mt-2 text-sm text-amber-700">
                        La pièce de contrôle n'est pas enregistrée. Contactez l'administrateur.
                    </p>
                    <p v-if="attribution.verrouille" class="mt-2 text-sm text-red-600">
                        Trop de tentatives. Réessayez après {{ attribution.verrouille_jusqua }}.
                    </p>
                    <p v-if="erreur && erreurId === attribution.id" class="mt-2 text-sm text-red-600">{{ erreur }}</p>
                </article>

                <section class="rounded-xl bg-white p-6 shadow-md">
                    <h3 class="mb-4 text-lg font-semibold text-primary-txt">Dossiers traités</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Dossier</th>
                                    <th class="px-3 py-2">Traité le</th>
                                    <th class="px-3 py-2">État</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="traites.length === 0">
                                    <td colspan="3" class="px-3 py-6 text-center">Aucun dossier traité pour le moment.</td>
                                </tr>
                                <tr v-for="ligne in traites" :key="ligne.id">
                                    <td class="border-b px-3 py-3">
                                        <div class="text-xs uppercase tracking-wide">{{ ligne.origine_libelle }}</div>
                                        <div class="font-semibold">{{ ligne.numero }}</div>
                                        <div class="text-xs">{{ ligne.detail || ligne.service || "—" }}</div>
                                    </td>
                                    <td class="border-b px-3 py-3">{{ ligne.traite_le }}</td>
                                    <td class="border-b px-3 py-3">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-bold uppercase"
                                            :class="ligne.statut === 'signe' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-800'"
                                        >
                                            {{ ligne.statut_libelle }}
                                        </span>
                                        <div v-if="ligne.signe_le" class="mt-1 text-xs">Signé le {{ ligne.signe_le }}</div>
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
