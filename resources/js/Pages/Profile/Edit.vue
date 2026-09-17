<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import TelegramLinkForm from './Partials/TelegramLinkForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdatePositionForm from './Partials/UpdatePositionForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    telegramReady: {
        type: Boolean,
        default: false,
    },
    promptTelegramLink: {
        type: Boolean,
        default: false,
    },
    positions: {
        type: Array,
        default: () => [],
    },
});

const highlightTelegram = props.promptTelegramLink && props.telegramReady;

onMounted(() => {
    if (highlightTelegram) {
        document
            .getElementById('telegram-link-section')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<template>
    <Head title="Профіль" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-display text-2xl font-semibold text-white">
                Профіль
            </h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
            <div v-reveal v-glow class="glass-panel p-6 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div
                v-if="telegramReady"
                id="telegram-link-section"
                v-reveal:100
                v-glow
                class="glass-panel p-6 sm:p-8"
                :class="highlightTelegram && 'ring-2 ring-gold-400/60 shadow-gold'"
            >
                <p v-if="highlightTelegram" class="mb-4 text-sm font-medium text-gold-300">
                    Пошту підтверджено! Залишився останній крок — привʼяжіть Telegram,
                    щоб отримувати особисті сповіщення.
                </p>
                <TelegramLinkForm class="max-w-xl" />
            </div>

            <div v-reveal:150 v-glow class="glass-panel p-6 sm:p-8">
                <UpdatePositionForm :positions="positions" class="max-w-xl" />
            </div>

            <div v-reveal:200 v-glow class="glass-panel p-6 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div v-reveal:250 v-glow class="glass-panel p-6 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
