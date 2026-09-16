<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    values: { type: Object, required: true },
});

const TABS = {
    general: {
        label: 'Загальні',
        fields: [
            { key: 'site_name', label: 'Назва сайту' },
            { key: 'site_tagline', label: 'Слоган' },
            { key: 'support_contact', label: 'Контакт підтримки (email або Telegram)' },
        ],
    },
    telegram: {
        label: 'Telegram',
        fields: [
            { key: 'telegram_bot_username', label: 'Юзернейм бота (без @)' },
            { key: 'telegram_bot_token', label: 'Bot Token', secret: true },
            { key: 'telegram_webhook_url', label: 'Webhook URL' },
            { key: 'telegram_bot_url', label: 'Посилання на бота для CTA (t.me/...)' },
        ],
    },
    discord: {
        label: 'Discord',
        fields: [
            { key: 'discord_client_id', label: 'Client ID' },
            { key: 'discord_client_secret', label: 'Client Secret', secret: true },
            { key: 'discord_redirect_uri', label: 'Redirect URI (OAuth)' },
            { key: 'discord_bot_token', label: 'Bot Token', secret: true },
            { key: 'discord_guild_id', label: 'ID сервера (guild)' },
        ],
    },
};

const activeTab = ref('general');

function makeForm(group) {
    const initial = {};
    for (const field of TABS[group].fields) {
        initial[field.key] = props.values[field.key] || '';
    }
    return useForm(initial);
}

const forms = {
    general: makeForm('general'),
    telegram: makeForm('telegram'),
    discord: makeForm('discord'),
};

function submit(group) {
    forms[group].put(route('admin.settings.update', group), { preserveScroll: true });
}
</script>

<template>
    <Head title="Налаштування — Monsory Connect" />

    <AdminLayout title="Налаштування">
        <div class="mb-8 flex flex-wrap gap-2">
            <button
                v-for="(tab, key) in TABS"
                :key="key"
                class="relative rounded-full border px-5 py-2.5 text-sm font-medium tracking-wide transition-all duration-300"
                :class="activeTab === key
                    ? 'border-gold-400/50 bg-gold-400/10 text-gold-200 shadow-gold'
                    : 'border-white/10 text-white/50 hover:border-white/25 hover:text-white'"
                @click="activeTab = key"
            >
                {{ tab.label }}
            </button>
        </div>

        <Transition name="fade-tab" mode="out-in">
            <form v-glow :key="activeTab" class="glass-panel max-w-xl space-y-5 p-8" @submit.prevent="submit(activeTab)">
                <div v-for="field in TABS[activeTab].fields" :key="field.key">
                    <InputLabel :value="field.label" />
                    <TextInput
                        v-model="forms[activeTab][field.key]"
                        :type="field.secret ? 'password' : 'text'"
                        autocomplete="off"
                    />
                    <InputError :message="forms[activeTab].errors[field.key]" />
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <PrimaryButton :disabled="forms[activeTab].processing">Зберегти</PrimaryButton>
                    <p v-if="forms[activeTab].recentlySuccessful" class="text-sm text-emerald-300">Збережено.</p>
                </div>
            </form>
        </Transition>
    </AdminLayout>
</template>

<style scoped>
.fade-tab-enter-active,
.fade-tab-leave-active {
    transition: all 0.25s ease;
}
.fade-tab-enter-from,
.fade-tab-leave-to {
    opacity: 0;
    transform: translateY(8px);
}
</style>
