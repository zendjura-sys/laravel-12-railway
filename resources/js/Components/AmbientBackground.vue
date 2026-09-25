<script setup>
// Единая атмосфера для всех экранов: цветной свет за стеклом + плёночное
// зерно. Раньше орбы копипастились в каждый layout со своими координатами,
// из-за чего свет на главной, в кабинете и в админке падал по-разному и
// сайт не читался как одна система.
defineProps({
    // На узких экранах орбы дают заметную нагрузку на композитинг, а пользы
    // от них мало — площадь стекла слишком маленькая, чтобы свет читался.
    mobile: { type: Boolean, default: false },
    intensity: {
        type: String,
        default: 'normal',
        validator: (v) => ['soft', 'normal', 'rich'].includes(v),
    },
});
</script>

<template>
    <div class="pointer-events-none fixed inset-0 -z-30 overflow-hidden" :class="!mobile && 'hidden lg:block'">
        <div
            class="aurora-orb animate-aurora -left-32 top-24 h-[32rem] w-[32rem]"
            :class="{
                'bg-gold-500/10': intensity === 'soft',
                'bg-gold-500/20': intensity === 'normal',
                'bg-gold-500/30': intensity === 'rich',
            }"
        ></div>
        <div
            class="aurora-orb animate-aurora right-[-10rem] top-[38rem] h-[36rem] w-[36rem]"
            :class="{
                'bg-aurora-500/15': intensity === 'soft',
                'bg-aurora-500/25': intensity === 'normal',
                'bg-aurora-500/30': intensity === 'rich',
            }"
            style="animation-delay: -8s"
        ></div>
        <div
            v-if="intensity === 'rich'"
            class="aurora-orb animate-aurora left-1/4 top-[90rem] h-[30rem] w-[30rem] bg-gold-400/15"
            style="animation-delay: -14s"
        ></div>
    </div>

    <div class="film-grain"></div>
</template>
