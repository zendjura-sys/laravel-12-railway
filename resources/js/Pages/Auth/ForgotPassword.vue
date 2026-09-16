<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Відновлення пароля" />

        <h1 class="font-display mb-4 text-2xl font-semibold text-white">Забули пароль?</h1>

        <div class="mb-4 text-sm text-white/50">
            Не проблема. Вкажіть email, вказаний при реєстрації, і ми
            надішлемо посилання для встановлення нового пароля.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-emerald-300"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError :message="form.errors.email" />
            </div>

            <div class="mt-6 flex items-center justify-end">
                <PrimaryButton
                    :disabled="form.processing"
                >
                    Надіслати посилання
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
