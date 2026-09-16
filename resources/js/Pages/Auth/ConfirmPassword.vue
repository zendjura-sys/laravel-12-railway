<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Підтвердження пароля" />

        <h1 class="font-display mb-4 text-2xl font-semibold text-white">Підтвердіть пароль</h1>

        <div class="mb-4 text-sm text-white/50">
            Це захищений розділ системи. Перш ніж продовжити, підтвердіть свій пароль.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Пароль" />
                <TextInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="mt-6 flex justify-end">
                <PrimaryButton
                    class="ms-4"
                    :disabled="form.processing"
                >
                    Підтвердити
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
