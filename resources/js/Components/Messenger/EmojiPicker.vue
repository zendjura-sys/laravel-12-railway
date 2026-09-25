<script setup>
defineEmits(['select']);

// Курований набір (не повний Unicode CLDR) — за категоріями, як у
// звичайних чатах, без пошуку: для родинного чату кількасот найвживаніших
// достатньо, а повний набір лише ускладнив би вибір.
const groups = [
    {
        label: 'Емоції',
        emoji: ['😀', '😁', '😂', '🤣', '😊', '😉', '😍', '🥰', '😘', '😎', '🤩', '🥳', '😇', '🙂', '😏', '😌', '🤔', '🤨', '😐', '😑', '😴', '🤤', '😪', '🙄', '😬', '🤗', '🤭', '🫡', '🤫', '🤐'],
    },
    {
        label: 'Емоції-2',
        emoji: ['😢', '😭', '😤', '😠', '😡', '🤬', '😱', '😨', '😰', '😓', '🥺', '😔', '😞', '😟', '😕', '🙁', '☹️', '😳', '🥴', '🤒', '🤕', '🤧', '🥵', '🥶', '😵', '🤯', '🫠', '🤪', '😜', '😝'],
    },
    {
        label: 'Жести',
        emoji: ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤙', '👏', '🙌', '🙏', '💪', '🤝', '👋', '🫶', '✊', '👊', '🫱', '🖐️', '🤘', '☝️'],
    },
    {
        label: 'Серця',
        emoji: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '✨', '🔥'],
    },
    {
        label: 'Інше',
        emoji: ['🎉', '🎊', '🏆', '🥇', '💰', '💵', '💎', '🚗', '🔫', '🍾', '🍻', '☕', '🍕', '⏰', '📸', '🎮', '⚠️', '✅', '❌', '❓'],
    },
];
</script>

<template>
    <div class="glass-panel absolute bottom-full left-0 mb-2 max-h-72 w-72 overflow-y-auto rounded-2xl p-3">
        <div v-for="g in groups" :key="g.label" class="mb-2 last:mb-0">
            <p class="mb-1 px-1 text-[11px] font-medium uppercase tracking-wide text-white/30">{{ g.label }}</p>
            <div class="grid grid-cols-8 gap-1">
                <button
                    v-for="e in g.emoji"
                    :key="e"
                    type="button"
                    class="rounded-lg py-1 text-xl leading-none transition hover:bg-white/10"
                    @click="$emit('select', e)"
                >
                    {{ e }}
                </button>
            </div>
        </div>
    </div>
</template>
