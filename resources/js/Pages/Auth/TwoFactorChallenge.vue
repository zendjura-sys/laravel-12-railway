<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const useRecovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

function submit() {
    form.transform((data) => (useRecovery.value ? { recovery_code: data.recovery_code } : { code: data.code }))
        .post(route('two-factor.login.store'), {
            onFinish: () => form.reset(),
        });
}

function toggleMode() {
    useRecovery.value = !useRecovery.value;
    form.clearErrors();
}
</script>

<template>
    <GuestLayout>
        <Head title="Підтвердження входу" />

        <h1 class="font-display mb-2 text-2xl font-light text-white">Двофакторна перевірка</h1>
        <p class="mb-6 text-sm text-white/40">
            {{ useRecovery
                ? 'Введіть один із резервних кодів, збережених при увімкненні 2FA.'
                : 'Введіть 6-значний код із застосунку-аутентифікатора.' }}
        </p>

        <form @submit.prevent="submit">
            <div v-if="!useRecovery">
                <InputLabel for="code" value="Код підтвердження" />
                <TextInput
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    class="tracking-[0.4em]"
                    placeholder="000000"
                />
                <InputError :message="form.errors.code" />
            </div>
            <div v-else>
                <InputLabel for="recovery_code" value="Резервний код" />
                <TextInput
                    id="recovery_code"
                    v-model="form.recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    autofocus
                />
                <InputError :message="form.errors.recovery_code" />
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button
                    type="button"
                    class="text-sm text-white/40 underline hover:text-white"
                    @click="toggleMode"
                >
                    {{ useRecovery ? 'Ввести код із застосунку' : 'Використати резервний код' }}
                </button>

                <PrimaryButton :disabled="form.processing">Підтвердити</PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
