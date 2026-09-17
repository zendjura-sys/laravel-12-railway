<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    first_name: user.first_name ?? '',
    last_name: user.last_name ?? '',
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-normal text-white">
                Дані профілю
            </h2>

            <p class="mt-1 text-sm text-white/40">
                Оновіть ім'я, прізвище та email вашого акаунту.
            </p>
            <p class="mt-1 text-xs text-white/30">
                В полях «Ім'я» та «Прізвище» вказуйте ігрові ім'я й прізвище персонажа, а не реальні — за ними подаються звіти й ведеться статистика.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div class="grid gap-6 sm:grid-cols-2">
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

            <div>
                <InputLabel for="email" value="Пошта" />

                <TextInput
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-white/60">
                    Ваша email-адреса не підтверджена.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-sm text-gold-300 underline hover:text-gold-200"
                    >
                        Надіслати лист підтвердження ще раз.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-emerald-300"
                >
                    Нове посилання для підтвердження надіслано на вашу email-адресу.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Зберегти</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-white/50"
                    >
                        Збережено.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
