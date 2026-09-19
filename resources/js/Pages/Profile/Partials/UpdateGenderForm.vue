<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({
    gender: user.gender ?? '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-normal text-white">
                Стать
            </h2>

            <p class="mt-1 text-sm text-white/40">
                За бажанням — впливає лише на граматику в текстах сайту й бота («подав»/«подала» тощо).
                Якщо не вказано, використовується чоловіча форма.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.gender'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel value="Стать" />

                <div class="mt-2 flex gap-3">
                    <label
                        class="flex-1 cursor-pointer rounded-lg border px-4 py-2.5 text-center text-sm transition-colors"
                        :class="form.gender === 'm' ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/50 hover:border-white/25'"
                    >
                        <input type="radio" class="hidden" value="m" v-model="form.gender" />
                        Чоловіча
                    </label>
                    <label
                        class="flex-1 cursor-pointer rounded-lg border px-4 py-2.5 text-center text-sm transition-colors"
                        :class="form.gender === 'f' ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/50 hover:border-white/25'"
                    >
                        <input type="radio" class="hidden" value="f" v-model="form.gender" />
                        Жіноча
                    </label>
                    <label
                        class="flex-1 cursor-pointer rounded-lg border px-4 py-2.5 text-center text-sm transition-colors"
                        :class="!form.gender ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/50 hover:border-white/25'"
                    >
                        <input type="radio" class="hidden" value="" v-model="form.gender" />
                        Не вказано
                    </label>
                </div>

                <InputError :message="form.errors.gender" />
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
