<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    positions: {
        type: Array,
        default: () => [],
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    position_key: user.position_key ?? '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-lg font-medium text-white">
                Посада в родині
            </h2>

            <p class="mt-1 text-sm text-white/40">
                Вкажіть свою актуальну посаду самостійно — підвищення відбуваються
                в грі, і сайт не завжди встигає за ними.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.position'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="position_key" value="Посада" />

                <select
                    id="position_key"
                    v-model="form.position_key"
                    class="mt-1 block w-full rounded-lg border-white/10 bg-obsidian-900/60 text-white shadow-sm focus:border-gold-400 focus:ring-gold-400"
                >
                    <option value="">— Не вказано —</option>
                    <option
                        v-for="position in positions"
                        :key="position.key"
                        :value="position.key"
                    >
                        {{ position.title }}
                    </option>
                </select>

                <InputError :message="form.errors.position_key" />
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
