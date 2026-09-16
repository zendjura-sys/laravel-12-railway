<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Вхід" />

        <h1 class="font-display mb-6 text-2xl font-semibold text-white">Вхід до кабінету</h1>

        <div v-if="status" class="mb-4 text-sm font-medium text-emerald-300">
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

            <div class="mt-4">
                <InputLabel for="password" value="Пароль" />

                <TextInput
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

                <InputError :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-white/50"
                        >Запам'ятати мене</span
                    >
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-white/40 underline hover:text-white"
                >
                    Забули пароль?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :disabled="form.processing"
                >
                    Увійти
                </PrimaryButton>
            </div>
        </form>

        <!-- Реєстрація існує (маршрут register), але зі сторінки входу на неї
             не вело жодне посилання — новий учасник упирався в глухий кут і
             міг потрапити на форму лише вручну набравши адресу. -->
        <p class="mt-7 border-t border-white/10 pt-6 text-center text-sm text-white/40">
            Ще не в родині?
            <Link
                :href="route('register')"
                class="link-underline ms-1 font-medium text-gold-300 transition-colors hover:text-gold-200"
            >
                Створити акаунт
            </Link>
        </p>
    </GuestLayout>
</template>
