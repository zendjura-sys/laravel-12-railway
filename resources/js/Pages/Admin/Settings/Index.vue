<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
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
            { key: 'telegram_group_id', label: 'ID групи родини (напр. -1004369425235)' },
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
    ai: {
        label: 'AI',
        fields: [
            { key: 'mistral_api_key', label: 'Mistral API Key', secret: true },
            {
                key: 'mistral_proxy_url',
                label: 'HTTP proxy для Mistral (необов\'язково)',
                secret: true,
                hint: 'Заповнюйте лише якщо "Перевірити підключення" видає помилку — деякі хостинги можуть бути заблоковані на мережевому рівні. Формат: http://user:pass@host:port',
            },
            { key: 'ai_reports_analysis_enabled', label: 'Аналіз фото-доказів звіту (дата на скріні, кількість)', type: 'checkbox' },
            { key: 'ai_rejection_advice_enabled', label: 'Кнопка "Згенерувати рекомендацію" при відхиленні звіту', type: 'checkbox' },
            { key: 'ai_grade_advice_enabled', label: 'Кнопка "Порекомендувати оцінку" при затвердженні звіту', type: 'checkbox' },
            { key: 'ai_applications_review_enabled', label: 'Оцінка якості анкети при новій заявці на вступ', type: 'checkbox' },
            { key: 'ai_broadcast_assist_enabled', label: 'Кнопка "Покращити текст" у формі розсилки', type: 'checkbox' },
            { key: 'ai_event_draft_enabled', label: 'Кнопка "Згенерувати" у формі нової події родини', type: 'checkbox' },
        ],
    },
    ai_prompts: {
        label: 'Інструкції AI',
        fields: [
            {
                key: 'ai_reports_analysis_instructions',
                label: 'Аналіз фото-доказів звіту',
                type: 'textarea',
                hint: 'Наприклад: де саме на скріні шукати дату й час, у якому вони форматі, на що ще звертати увагу. Порожнє поле — AI орієнтується лише на власний здоровий глузд.',
            },
            {
                key: 'ai_rejection_advice_instructions',
                label: 'Рекомендація при відхиленні звіту',
                type: 'textarea',
                hint: 'Додаткові правила для тексту-поради учаснику (тон, що обов\'язково згадати тощо).',
            },
            {
                key: 'ai_grade_advice_instructions',
                label: 'Рекомендація оцінки при затвердженні звіту',
                type: 'textarea',
                hint: 'Ваші критерії для оцінок S–G (наприклад, за яким відсотком перемог давати S, скільки контрактів вважати мало тощо). Без цього AI орієнтується лише на загальний здоровий глузд.',
            },
            {
                key: 'ai_applications_review_instructions',
                label: 'Оцінка якості анкети на вступ',
                type: 'textarea',
                hint: 'На що звертати увагу в анкеті кандидата понад стандартну перевірку на шаблонність.',
            },
            {
                key: 'ai_broadcast_assist_instructions',
                label: 'Покращення тексту розсилки',
                type: 'textarea',
                hint: 'Стиль, тон чи вимоги до тексту розсилок (наприклад, завжди звертатись на "ви").',
            },
            {
                key: 'ai_event_draft_instructions',
                label: 'Генерація назви й опису події родини',
                type: 'textarea',
                hint: 'Стиль і тон для назви/опису подій (наприклад, завжди згадувати дрес-код чи стандартну фразу про обов\'язковість присутності).',
            },
        ],
    },
    report_guides: {
        label: 'Пам\'ятки для звітів',
        fields: [
            {
                key: 'report_guide_bizwar',
                label: 'Бізвар',
                type: 'textarea',
                hint: 'Що писати й які скріни додавати учаснику при поданні звіту типу "Бізвар". Порожнє поле — покажеться стандартна пам\'ятка.',
            },
            {
                key: 'report_guide_contract',
                label: 'Контракт',
                type: 'textarea',
                hint: 'Що писати й які скріни додавати при поданні звіту типу "Контракт". Порожнє поле — покажеться стандартна пам\'ятка.',
            },
            {
                key: 'report_guide_investment',
                label: 'Інвестиції',
                type: 'textarea',
                hint: 'Що писати й які скріни додавати при поданні звіту типу "Інвестиції". Порожнє поле — покажеться стандартна пам\'ятка.',
            },
            {
                key: 'report_guide_other',
                label: 'Інше',
                type: 'textarea',
                hint: 'Що писати й які скріни додавати при поданні звіту типу "Інше". Порожнє поле — покажеться стандартна пам\'ятка.',
            },
        ],
    },
};

const activeTab = ref('general');

function makeForm(group) {
    const initial = {};
    for (const field of TABS[group].fields) {
        initial[field.key] = field.type === 'checkbox' ? !!props.values[field.key] : (props.values[field.key] || '');
    }
    return useForm(initial);
}

const forms = {
    general: makeForm('general'),
    telegram: makeForm('telegram'),
    discord: makeForm('discord'),
    ai: makeForm('ai'),
    ai_prompts: makeForm('ai_prompts'),
    report_guides: makeForm('report_guides'),
};

function submit(group) {
    forms[group].put(route('admin.settings.update', group), { preserveScroll: true });
}

/* ---------- перевірка підключення Mistral ---------- */
const aiTesting = ref(false);
const aiTestResult = ref(null);

async function testAi() {
    aiTesting.value = true;
    aiTestResult.value = null;
    try {
        // Шлемо те, що зараз у полі, навіть якщо ще не натиснули "Зберегти" —
        // інакше довелось би спершу зберегти, а вже потім тестувати.
        const { data } = await window.axios.post(route('admin.ai.test'), {
            api_key: forms.ai.mistral_api_key,
            proxy_url: forms.ai.mistral_proxy_url,
        });
        aiTestResult.value = { ok: data.ok, message: data.message };
    } catch (e) {
        aiTestResult.value = { ok: false, message: e.response?.data?.message || 'Помилка' };
    } finally {
        aiTesting.value = false;
    }
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
            <form v-reveal v-glow :key="activeTab" class="glass-panel max-w-xl space-y-5 p-8" @submit.prevent="submit(activeTab)">
                <div v-for="field in TABS[activeTab].fields" :key="field.key">
                    <label v-if="field.type === 'checkbox'" class="flex cursor-pointer items-center gap-3">
                        <Checkbox v-model:checked="forms[activeTab][field.key]" />
                        <span class="text-sm text-white/70">{{ field.label }}</span>
                    </label>
                    <template v-else-if="field.type === 'textarea'">
                        <InputLabel :value="field.label" />
                        <textarea
                            v-model="forms[activeTab][field.key]"
                            rows="4"
                            class="w-full rounded-lg border border-white/10 bg-obsidian-900 px-3 py-2 text-white"
                        ></textarea>
                        <p v-if="field.hint" class="mt-1 text-xs text-white/30">{{ field.hint }}</p>
                    </template>
                    <template v-else>
                        <InputLabel :value="field.label" />
                        <TextInput
                            v-model="forms[activeTab][field.key]"
                            :type="field.secret ? 'password' : 'text'"
                            autocomplete="off"
                        />
                        <p v-if="field.hint" class="mt-1 text-xs text-white/30">{{ field.hint }}</p>
                    </template>
                    <InputError :message="forms[activeTab].errors[field.key]" />
                </div>

                <div class="flex flex-wrap items-center gap-4 pt-2">
                    <PrimaryButton :disabled="forms[activeTab].processing">Зберегти</PrimaryButton>
                    <p v-if="forms[activeTab].recentlySuccessful" class="text-sm text-emerald-300">Збережено.</p>

                    <button
                        v-if="activeTab === 'ai'"
                        type="button"
                        :disabled="aiTesting"
                        class="rounded-full border border-white/15 px-4 py-2 text-xs uppercase tracking-widest text-white/60 transition-colors hover:border-gold-400/40 hover:text-white disabled:opacity-40"
                        @click="testAi"
                    >
                        {{ aiTesting ? 'Перевіряю…' : 'Перевірити підключення' }}
                    </button>
                    <p v-if="activeTab === 'ai' && aiTestResult" class="text-sm" :class="aiTestResult.ok ? 'text-emerald-300' : 'text-ember-500'">
                        {{ aiTestResult.message }}
                    </p>
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
