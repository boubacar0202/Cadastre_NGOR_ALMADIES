<script setup>
import { ref } from 'vue';
import { telechargerExtrait } from '@/utils/extraitPlan';

const props = defineProps({
    commandes: { type: Array, required: true },
    fichier: { type: String, required: true },
});

const emit = defineEmits(['fermer']);
const telechargement = ref(false);
const erreur = ref('');

function points(commande) {
    return commande.points.map((point) => point.join(',')).join(' ');
}

function ancre(commande) {
    if (commande.anchor === 'middle') return 'middle';
    if (commande.anchor === 'end') return 'end';
    return 'start';
}

async function telecharger() {
    erreur.value = '';
    telechargement.value = true;
    try {
        await telechargerExtrait(props.commandes, props.fichier);
    } catch (e) {
        erreur.value = 'Le téléchargement du PDF a échoué.';
    } finally {
        telechargement.value = false;
    }
}
</script>

<template>
    <div class="fixed inset-0 z-[3000] flex items-start justify-center overflow-y-auto bg-black/55 p-4" @click.self="emit('fermer')">
        <div class="my-4 w-full max-w-3xl">
            <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                <button type="button" class="rounded-xl bg-white px-4 py-2.5 text-sm font-medium text-primary" @click="emit('fermer')">
                    Fermer
                </button>
                <button type="button" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60" :disabled="telechargement" @click="telecharger">
                    {{ telechargement ? 'Téléchargement…' : 'Télécharger' }}
                </button>
            </div>
            <p v-if="erreur" class="mb-2 text-sm text-white">{{ erreur }}</p>
            <svg viewBox="0 0 210 297" class="w-full bg-white shadow-xl">
                <template v-for="(commande, index) in commandes" :key="index">
                    <rect
                        v-if="commande.type === 'rect'"
                        :x="commande.x"
                        :y="commande.y"
                        :width="commande.w"
                        :height="commande.h"
                        :fill="commande.fill ? `rgb(${commande.fill.join(',')})` : 'none'"
                        :stroke="commande.color ? `rgb(${commande.color.join(',')})` : '#111111'"
                        :stroke-width="commande.width || 0.25"
                    />
                    <line
                        v-else-if="commande.type === 'line'"
                        :x1="commande.x1"
                        :y1="commande.y1"
                        :x2="commande.x2"
                        :y2="commande.y2"
                        :stroke="commande.color ? `rgb(${commande.color.join(',')})` : '#111111'"
                        :stroke-width="commande.width || 0.2"
                    />
                    <polygon
                        v-else-if="commande.type === 'polygon'"
                        :points="points(commande)"
                        :fill="commande.fill ? `rgb(${commande.fill.join(',')})` : 'none'"
                        :stroke="commande.stroke ? `rgb(${commande.stroke.join(',')})` : '#111111'"
                        :stroke-width="commande.width || 0.25"
                    />
                    <image
                        v-else-if="commande.type === 'image'"
                        :href="commande.src"
                        :x="commande.x"
                        :y="commande.y"
                        :width="commande.w"
                        :height="commande.h"
                    />
                    <text
                        v-else-if="commande.type === 'text'"
                        :x="commande.x"
                        :y="commande.y"
                        :font-size="commande.size || 2.7"
                        :font-weight="commande.weight === 'bold' ? 700 : 400"
                        font-family="Helvetica, Arial, sans-serif"
                        :fill="commande.color ? `rgb(${commande.color.join(',')})` : '#111111'"
                        :text-anchor="ancre(commande)"
                        dominant-baseline="middle"
                        :transform="commande.rotation ? `rotate(${commande.rotation} ${commande.x} ${commande.y})` : undefined"
                    >{{ commande.text }}</text>
                </template>
            </svg>
        </div>
    </div>
</template>
