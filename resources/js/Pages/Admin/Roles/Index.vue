<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: { type: Array, required: true },
    permissions: { type: Array, required: true },
});

const createForm = useForm({ name: '' });
function createRole() {
    createForm.post(route('admin.roles.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

const savingRole = ref(null);
async function togglePermission(role, permission) {
    const has = role.permissions.includes(permission);
    const next = has ? role.permissions.filter((p) => p !== permission) : [...role.permissions, permission];
    role.permissions = next;

    savingRole.value = role.id;
    try {
        await window.axios.put(route('admin.roles.update', role.id), { permissions: next });
    } finally {
        savingRole.value = null;
    }
}

function destroyRole(role) {
    if (!confirm(`Видалити роль «${role.name}»?`)) return;
    window.axios.delete(route('admin.roles.destroy', role.id)).then(() => window.location.reload());
}
</script>

<template>
    <Head title="Права доступу — Monsory Connect" />

    <AdminLayout title="Права доступу">
        <form v-reveal class="glass-panel mb-8 flex flex-wrap items-end gap-4 p-6" @submit.prevent="createRole">
            <div class="min-w-[220px]">
                <label class="mb-2 block text-xs font-medium uppercase tracking-widest text-white/40">Нова роль</label>
                <TextInput v-model="createForm.name" placeholder="напр. moderator" />
                <InputError :message="createForm.errors.name" />
            </div>
            <PrimaryButton :disabled="createForm.processing">Створити роль</PrimaryButton>
        </form>

        <div class="space-y-6">
            <div v-for="role in roles" :key="role.id" v-reveal v-glow class="glass-panel p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-display text-xl text-white">{{ role.name }}</h3>
                        <p class="text-xs text-white/40">{{ role.users_count }} учасник(ів) з цією роллю</p>
                    </div>
                    <button
                        v-if="role.name !== 'admin'"
                        class="rounded-full border border-ember-500/25 px-4 py-1.5 text-xs font-medium text-ember-500/80 transition-colors hover:bg-ember-600/10"
                        @click="destroyRole(role)"
                    >
                        Видалити
                    </button>
                </div>

                <div class="flex flex-wrap gap-2" :class="savingRole === role.id && 'opacity-60'">
                    <label
                        v-for="permission in permissions"
                        :key="permission"
                        class="cursor-pointer rounded-full border px-3 py-1.5 text-xs font-medium tracking-wide transition-colors"
                        :class="role.permissions.includes(permission)
                            ? 'border-gold-400/50 bg-gold-400/10 text-gold-200'
                            : 'border-white/10 text-white/40 hover:border-white/25 hover:text-white'"
                    >
                        <input type="checkbox" class="hidden" :checked="role.permissions.includes(permission)" @change="togglePermission(role, permission)" />
                        {{ permission }}
                    </label>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
