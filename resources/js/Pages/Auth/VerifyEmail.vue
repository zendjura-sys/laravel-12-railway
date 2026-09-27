<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Підтвердження email" />

        <h1 class="font-display mb-4 text-2xl font-light text-white">Підтвердьте email</h1>

        <div class="mb-4 text-sm text-white/50">
            Дякуємо за реєстрацію! Перш ніж почати, підтвердіть свою email-адресу,
            перейшовши за посиланням, яке ми щойно надіслали. Якщо лист не
            прийшов — ми радо надішлемо ще один.
        </div>

        <div
            class="mb-4 text-sm font-medium text-emerald-300"
            v-if="verificationLinkSent"
        >
            Нове посилання для підтвердження надіслано на email, вказаний
            під час реєстрації.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <PrimaryButton
                    :disabled="form.processing"
                >
                    Надіслати ще раз
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm text-white/40 underline hover:text-white"
                    >Вийти</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
