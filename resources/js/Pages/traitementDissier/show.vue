<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";

defineOptions({ layout: DefaultLayout });

defineProps({
    fiche: {
        type: Object,
        required: true,
    },
});

function classeStatut(fiche) {
    if (fiche.statut === "signe") return "bg-emerald-100 text-emerald-800";
    if (fiche.en_retard) return "bg-red-100 text-red-700";
    if (fiche.statut === "traite") return "bg-sky-100 text-sky-800";
    return "bg-amber-100 text-amber-800";
}
</script>

<template>
    <Head :title="`${fiche.origine_libelle} ${fiche.numero || ''}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">
                {{ fiche.origine_libelle }} n° {{ fiche.numero }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto w-full max-w-8xl space-y-6 px-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        :href="route('traitementDossier.create')"
                        class="text-sm font-semibold text-primary hover:text-bold"
                    >
                        Retour à mes dossiers
                    </Link>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" :class="classeStatut(fiche)">
                        {{ fiche.statut_libelle }}
                    </span>
                </div>

                <section class="rounded-xl bg-white p-6 shadow-md">
                    <p class="text-sm text-primary-txt/80">
                        <span v-if="fiche.detail">{{ fiche.detail }}</span>
                        <span v-if="fiche.service"> · {{ fiche.service }}</span>
                    </p>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-primary-txt/60">Agent</dt>
                            <dd class="mt-1 font-semibold text-primary-txt">{{ fiche.agent || "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-primary-txt/60">Attribué le</dt>
                            <dd class="mt-1 font-semibold text-primary-txt">{{ fiche.attribue_le || "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-primary-txt/60">Échéance</dt>
                            <dd class="mt-1 font-semibold text-primary-txt">{{ fiche.echeance || "—" }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-primary-txt/60">Traité le</dt>
                            <dd class="mt-1 font-semibold text-primary-txt">{{ fiche.traite_le || "—" }}</dd>
                        </div>
                    </dl>
                </section>

                <section
                    v-for="(section, index) in fiche.sections"
                    :key="`${section.titre}-${index}`"
                    class="rounded-xl bg-white p-6 shadow-md"
                >
                    <h3 class="border-b border-primary/20 pb-3 text-lg font-semibold text-primary-txt">
                        {{ section.titre }}
                    </h3>
                    <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div v-for="ligne in section.lignes" :key="ligne.label" class="min-w-0">
                            <dt class="text-xs uppercase tracking-wide text-primary-txt/60">{{ ligne.label }}</dt>
                            <dd class="mt-1 break-words text-sm font-medium text-primary-txt">{{ ligne.valeur }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
