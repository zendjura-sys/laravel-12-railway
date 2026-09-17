<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="font-display text-lg font-normal text-white">
                Видалення акаунту
            </h2>

            <p class="mt-1 text-sm text-white/40">
                Після видалення акаунту всі його дані будуть видалені
                безповоротно. Перед видаленням завантажте все, що хочете зберегти.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">Видалити акаунт</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="font-display text-lg font-normal text-white">
                    Ви дійсно хочете видалити акаунт?
                </h2>

                <p class="mt-1 text-sm text-white/50">
                    Після видалення акаунту всі його дані будуть видалені
                    безповоротно. Введіть пароль, щоб підтвердити видалення.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Пароль"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        placeholder="Пароль"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeModal">
                        Скасувати
                    </SecondaryButton>

                    <DangerButton
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Видалити акаунт
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
