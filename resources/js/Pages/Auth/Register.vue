<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Реєстрація" />

        <h1 class="font-display mb-6 text-2xl font-semibold text-white">Створення акаунту</h1>

        <form @submit.prevent="submit">
            <div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="first_name" value="Ім'я" />

                        <TextInput
                            id="first_name"
                            type="text"
                            v-model="form.first_name"
                            required
                            autofocus
                            autocomplete="given-name"
                        />

                        <InputError :message="form.errors.first_name" />
                    </div>

                    <div>
                        <InputLabel for="last_name" value="Прізвище" />

                        <TextInput
                            id="last_name"
                            type="text"
                            v-model="form.last_name"
                            autocomplete="family-name"
                        />

                        <InputError :message="form.errors.last_name" />
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Пароль" />

                <TextInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    value="Підтвердження пароля"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="text-sm text-white/40 underline hover:text-white"
                >
                    Вже є акаунт?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :disabled="form.processing"
                >
                    Зареєструватися
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
