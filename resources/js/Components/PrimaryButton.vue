<template>
    <!-- btn-gold вместо постоянно бегущего shimmer: непрерывная анимация на
         кнопке читается как рекламная плашка, а не как статус. Металлическая
         фаска статична, движение появляется только в ответ на курсор. -->
    <button
        class="btn-gold inline-flex items-center justify-center px-5 py-2.5 text-xs font-semibold uppercase tracking-[0.18em] disabled:pointer-events-none disabled:opacity-40"
    >
        <slot />
    </button>
</template>
