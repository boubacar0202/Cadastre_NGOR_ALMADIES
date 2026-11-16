<script setup>
import { computed, ref, watch } from "vue"; 
import MazBtn from "maz-ui/components/MazBtn";
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import BaseInput from "@/Components/BaseInput.vue";

const props = defineProps({
    show: { type: Boolean, required: true },
    itemName: { type: String, default: "" },
    mutation: { type: Object, default: null },
    loading: { type: Boolean, default: false }
});

const displayName = computed(() => props.itemName || props.mutation?.txt_numeroMutation || "");

const emit = defineEmits(["update:show", "confirm"]);

const deleteCode = ref("");
const adminCode = ref("");
const showAdminPassword = ref(false);
const adminError = ref("");
const deleteError = ref("");

const resetFields = () => {
    deleteCode.value = "";
    adminCode.value = "";
    showAdminPassword.value = false;
    adminError.value = "";
    deleteError.value = "";
};

watch(() => props.show, (newVal) => {
    if (newVal) resetFields();
});

const closeModal = () => {
    emit("update:show", false);
    resetFields();
};

const confirmer = () => {
    adminError.value = "";
    deleteError.value = "";

    const codeOk = Boolean(adminCode.value && adminCode.value.trim() !== "");
    const motOk = deleteCode.value.trim().toUpperCase() === "SUPPRIMER";

    if (!codeOk) {
        adminError.value = "Entrez le code administrateur.";
    }
    if (!motOk) {
        deleteError.value = "Tapez SUPPRIMER pour confirmer.";
    }
    if (!codeOk || !motOk) return;

    emit("confirm", {
        deleteCode: deleteCode.value,
        adminCode: adminCode.value
    });
};

const toggleAdminPassword = () => {
    showAdminPassword.value = !showAdminPassword.value;
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-[2000]">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-red-600 text-center mb-4">
                Confirmer la suppression
            </h2>

            <p class="text-center text-primary-txt mb-4">
                Voulez-vous vraiment supprimer :
                <br>
                <span class="font-semibold text-primary">{{ displayName }}</span>
            </p>

            <BaseInput
                v-model="adminCode"
                label="Code administrateur"
                :type="showAdminPassword ? 'text' : 'password'"
                autocomplete="off"
                placeholder="Code administrateur"
                wrapper-class="mb-2 w-full"
                :error="adminError"
                @input="adminError = ''"
            >
                <template #append>
                    <button
                        type="button"
                        @click="toggleAdminPassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-primary-txt/60 hover:text-primary"
                    >
                        <EyeIcon v-if="!showAdminPassword" class="h-5 w-5" />
                        <EyeSlashIcon v-else class="h-5 w-5" />
                    </button>
                </template>
            </BaseInput>

            <BaseInput
                v-model="deleteCode"
                label="Confirmation"
                type="text"
                autocomplete="off"
                spellcheck="false"
                placeholder="SUPPRIMER"
                wrapper-class="mb-1 w-full"
                :error="deleteError"
                @input="deleteError = ''"
            />
            <p class="mb-4 text-sm text-primary-txt">Tapez SUPPRIMER.</p>

            <div class="flex justify-center items-center gap-3">
                <MazBtn
                    size="large"
                    class="h-10 w-36 bg-gradient-to-r from-green-400 to-green-600 text-white font-semibold rounded-2xl"
                    @click="closeModal"
                    :disabled="loading"
                >
                    Annuler
                </MazBtn>

                <MazBtn
                    size="large"
                    :loading="loading"
                    class="h-10 w-36 bg-gradient-to-r from-red-500 via-red-600 to-red-700 text-white font-semibold rounded-2xl"
                    @click="confirmer"
                    :disabled="loading"
                >
                    Supprimer
                </MazBtn>
            </div>
        </div>
    </div>
</template>