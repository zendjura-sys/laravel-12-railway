<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { toDateInputValue } from '@/lib/date';

const user = usePage().props.auth.user;

const form = useForm({
    birth_date: toDateInputValue(user.birth_date),
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-normal text-white">
                Дата народження
            </h2>

            <p class="mt-1 text-sm text-white/40">
                За бажанням — якщо вкажете, бот сам привітає вас у сімейному чаті в цей день.
                Якщо не вказано, ніде не показується.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.birthday'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="birth_date" value="Дата народження" />

                <input
                    id="birth_date"
                    v-model="form.birth_date"
                    type="date"
                    class="mt-1 block w-full rounded-lg border-white/10 bg-obsidian-900/60 text-white shadow-sm focus:border-gold-400 focus:ring-gold-400"
                />

                <InputError :message="form.errors.birth_date" />
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
