<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";
import Chart from "chart.js/auto";
import { onBeforeUnmount, onMounted, ref } from "vue";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";

defineOptions({ layout: DefaultLayout });

const props = defineProps({
    labels: { type: Array, default: () => [] },
    utilisateurs: { type: Array, default: () => [] },
    evolution: { type: Array, default: () => [] },
    synthese: {
        type: Object,
        default: () => ({ en_cours: 0, en_retard: 0, traites: 0, signes: 0 }),
    },
});

const syntheseRef = ref(null);
const barRef = ref(null);
const ligneRef = ref(null);
const graphiques = [];

const couleurs = ["#ff7a18", "#4facfe", "#16a34a", "#7c3aed", "#e11d48", "#0f766e", "#ca8a04", "#334155"];

onMounted(() => {
    if (!props.utilisateurs.length || !syntheseRef.value || !barRef.value || !ligneRef.value) {
        return;
    }

    graphiques.push(new Chart(syntheseRef.value, {
        type: "doughnut",
        data: {
            labels: ["En cours", "En retard", "À signer", "Signés"],
            datasets: [{
                data: [
                    props.synthese.en_cours,
                    props.synthese.en_retard,
                    props.synthese.traites,
                    props.synthese.signes,
                ],
                backgroundColor: ["#f59e0b", "#dc2626", "#4facfe", "#16a34a"],
            }],
        },
        options: { plugins: { legend: { position: "bottom" } } },
    }));

    graphiques.push(new Chart(barRef.value, {
        type: "bar",
        data: {
            labels: props.utilisateurs.map((user) => user.nom),
            datasets: [
                { label: "Traités", data: props.utilisateurs.map((user) => user.traites), backgroundColor: "#16a34a" },
                { label: "En cours", data: props.utilisateurs.map((user) => user.en_cours), backgroundColor: "#f59e0b" },
                { label: "En retard", data: props.utilisateurs.map((user) => user.en_retard), backgroundColor: "#dc2626" },
                { label: "Signés", data: props.utilisateurs.map((user) => user.signes), backgroundColor: "#4facfe" },
            ],
        },
        options: {
            responsive: true,
            scales: { x: { ticks: { maxRotation: 45, minRotation: 0 } }, y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    }));

    graphiques.push(new Chart(ligneRef.value, {
        type: "line",
        data: {
            labels: props.labels,
            datasets: props.evolution.map((serie, index) => ({
                label: serie.nom,
                data: serie.data,
                borderColor: couleurs[index % couleurs.length],
                backgroundColor: couleurs[index % couleurs.length],
                tension: 0.3,
            })),
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    }));
});

onBeforeUnmount(() => {
    graphiques.forEach((graphique) => graphique.destroy());
});
</script>

<template>
    <Head title="Evaluation User" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-primary-txt">Evaluation User</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto w-full max-w-8xl space-y-6 px-4">
                <div class="rounded-xl bg-white p-6 shadow-md">
                    <p class="text-sm text-primary-txt">
                        Évolution du traitement des dossiers pour chaque utilisateur : volume traité, dossiers encore en cours,
                        retards au-delà de 10 jours et délai moyen de validation.
                    </p>
                </div>

                <div v-if="utilisateurs.length === 0" class="rounded-xl bg-white p-10 text-center text-primary-txt shadow-md">
                    Aucune attribution pour le moment. Les graphiques apparaîtront dès le premier dossier attribué.
                </div>

                <template v-else>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="rounded-xl bg-white p-4 shadow-md lg:col-span-1">
                            <h3 class="mb-3 font-semibold text-primary-txt">Situation du bureau</h3>
                            <canvas ref="syntheseRef"></canvas>
                        </div>
                        <div class="rounded-xl bg-white p-4 shadow-md lg:col-span-2">
                            <h3 class="mb-3 font-semibold text-primary-txt">Charge par utilisateur</h3>
                            <canvas ref="barRef"></canvas>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-4 shadow-md">
                        <h3 class="mb-3 font-semibold text-primary-txt">Dossiers validés sur 6 mois</h3>
                        <canvas ref="ligneRef"></canvas>
                    </div>

                    <div class="overflow-x-auto rounded-xl bg-white p-4 shadow-md">
                        <table class="w-full text-left text-sm text-primary-txt">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-3 py-2">Utilisateur</th>
                                    <th class="px-3 py-2">Traités</th>
                                    <th class="px-3 py-2">Signés</th>
                                    <th class="px-3 py-2">En cours</th>
                                    <th class="px-3 py-2">En retard</th>
                                    <th class="px-3 py-2">Délai moyen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in utilisateurs" :key="user.id">
                                    <td class="border-b px-3 py-3 font-semibold">{{ user.nom }}</td>
                                    <td class="border-b px-3 py-3">{{ user.traites }}</td>
                                    <td class="border-b px-3 py-3">{{ user.signes }}</td>
                                    <td class="border-b px-3 py-3">{{ user.en_cours }}</td>
                                    <td class="border-b px-3 py-3" :class="user.en_retard > 0 ? 'font-bold text-red-600' : ''">{{ user.en_retard }}</td>
                                    <td class="border-b px-3 py-3">{{ user.delai_moyen === null ? "—" : user.delai_moyen + " j" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
