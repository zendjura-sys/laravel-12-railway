<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, required: true },
    search: { type: String, default: '' },
});

const q = ref(props.search);
function search() {
    router.get(route('admin.users.index'), { q: q.value }, { preserveState: true, replace: true });
}

const savingUser = ref(null);
async function toggleRole(user, role) {
    const has = user.roles.includes(role);
    const next = has ? user.roles.filter((r) => r !== role) : [...user.roles, role];
    user.roles = next;

    savingUser.value = user.id;
    try {
        await window.axios.put(route('admin.users.roles', user.id), { roles: next });
    } catch (e) {
        alert(e.response?.data?.errors?.roles?.[0] || 'Не вдалося оновити ролі');
        user.roles = has ? [...next, role] : next.filter((r) => r !== role);
    } finally {
        savingUser.value = null;
    }
}
</script>

<template>
    <Head title="Учасники — Monsory Connect" />

    <AdminLayout title="Учасники">
        <div class="mb-6 max-w-sm">
            <input
                v-model="q"
                type="search"
                placeholder="Пошук за іменем або email…"
                class="w-full rounded-lg border border-white/10 bg-obsidian-900/60 px-3 py-2 text-white placeholder:text-white/30 focus:border-gold-400/50 focus:outline-none focus:ring-1 focus:ring-gold-400/40"
                @keyup.enter="search"
            />
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.02]">
            <div
                v-for="user in users.data"
                :key="user.id"
                class="flex flex-wrap items-center justify-between gap-4 border-b border-white/5 px-6 py-4 last:border-0"
                :class="savingUser === user.id && 'opacity-60'"
            >
                <div>
                    <p class="font-medium text-white">{{ user.name }}</p>
                    <p class="text-xs text-white/40">{{ user.email }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="role in roles"
                        :key="role"
                        class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium tracking-wide transition-colors"
                        :class="user.roles.includes(role)
                            ? 'border-gold-400/50 bg-gold-400/10 text-gold-200'
                            : 'border-white/10 text-white/40 hover:border-white/25 hover:text-white'"
                    >
                        <input type="checkbox" class="hidden" :checked="user.roles.includes(role)" @change="toggleRole(user, role)" />
                        {{ role }}
                    </label>
                </div>
            </div>
            <div v-if="users.data.length === 0" class="px-6 py-12 text-center text-white/30">
                Нікого не знайдено
            </div>
        </div>

        <div v-if="users.links?.length > 3" class="mt-6 flex flex-wrap gap-2">
            <Link
                v-for="link in users.links"
                :key="link.label"
                :href="link.url || ''"
                v-html="link.label"
                class="rounded-full border px-3 py-1.5 text-xs"
                :class="[
                    link.active ? 'border-gold-400/50 bg-gold-400/10 text-gold-200' : 'border-white/10 text-white/40 hover:text-white',
                    !link.url && 'pointer-events-none opacity-30',
                ]"
            />
        </div>
    </AdminLayout>
</template>
