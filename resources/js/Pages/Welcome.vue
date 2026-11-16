<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});

const services = [
    {
        title: 'Dossiers',
        text: 'Enregistrement et suivi des dossiers cadastraux du centre.',
    },
    {
        title: 'Courriers',
        text: 'Arrivées, départs et courriers encore en instance.',
    },
    {
        title: 'Matrice',
        text: 'Parcelles, NICAD, lotissements et mutations.',
    },
    {
        title: 'Plans',
        text: 'Consultation cartographique et points densifiés.',
    },
];
</script>

<template>
    <Head title="Cadastre de Ngor Almadies">
        <link rel="icon" sizes="512x512" href="/logo-01.png">
    </Head>

    <div class="min-h-screen bg-primary-layout text-primary-txt">
        <div class="h-1.5 bg-gradient-to-r from-primary via-primary-menu to-primary-only"></div>

        <header class="sticky top-0 z-20 border-b border-primary-only/80 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <img
                    src="/logo.jpg"
                    alt="Direction Générale des Impôts et des Domaines"
                    class="h-12 w-auto sm:h-16"
                >

                <nav v-if="canLogin" class="flex shrink-0 items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-primary-dark"
                    >
                        Tableau de bord
                    </Link>
                    <template v-else>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="hidden rounded-xl px-4 py-2.5 text-sm font-semibold text-primary-txt transition hover:bg-primary-form sm:inline-flex"
                        >
                            S'inscrire
                        </Link>
                        <Link
                            :href="route('login')"
                            class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-primary-dark"
                        >
                            Se connecter
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:py-16">
                <div>
                    <h1 class="text-4xl font-bold leading-tight tracking-tight text-primary sm:text-5xl">
                        Cadastre de Ngor Almadies
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed">
                        Centre des services fiscaux de Ngor Almadies. Programme de gestion de la base de données MB-1. Enregistrez les dossiers,
                        suivez les courriers et consultez la matrice cadastrale ainsi que les plans.
                    </p>

                    <div v-if="canLogin" class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            :href="$page.props.auth.user ? route('dashboard') : route('login')"
                            class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-primary to-primary-dark px-5 py-3 text-sm font-semibold text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl"
                        >
                            {{ $page.props.auth.user ? 'Ouvrir le tableau de bord' : "Accéder à l'application" }}
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6" />
                            </svg>
                        </Link>
                    </div>
                </div>

                <div class="overflow-hidden rounded-3xl border border-primary-only bg-white shadow-[0_18px_40px_rgba(97,49,4,0.12)]">
                    <img
                        src="/pageAcceuil.jpg?v=ngor"
                        alt="Carte du Sénégal, pointe de Ngor Almadies dans la région de Dakar"
                        class="h-auto w-full"
                    >
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
                <h2 class="text-2xl font-bold tracking-tight text-primary">Services disponibles</h2>
                <ul class="mt-8 grid gap-x-12 gap-y-8 sm:grid-cols-2">
                    <li v-for="service in services" :key="service.title">
                        <h3 class="text-lg font-semibold text-primary">{{ service.title }}</h3>
                        <p class="mt-1 max-w-md text-sm leading-relaxed">{{ service.text }}</p>
                    </li>
                </ul>
            </section>
        </main>

        <footer class="border-t border-primary/20 bg-primary px-4 py-8 text-center text-sm text-primary-form">
            <p class="font-semibold tracking-wide text-white">Centre des services fiscaux de Ngor Almadies</p>
            <p class="mt-1">Cadastre de Ngor Almadies — Programme de gestion de base de données MB-1</p>
        </footer>
    </div>
</template>
