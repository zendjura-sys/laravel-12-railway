<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    telegramBotUrl: { type: String, default: '#' },
});

/* ---------- шапка: прозрачная -> сплошная при скролле ---------- */
const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 24;
}

/* ---------- лёгкий параллакс в hero по движению курсора ---------- */
const heroLayer = ref(null);
function onMouseMove(e) {
    if (!heroLayer.value) return;
    const x = (e.clientX / window.innerWidth - 0.5) * 14;
    const y = (e.clientY / window.innerHeight - 0.5) * 14;
    heroLayer.value.style.transform = `translate3d(${x}px, ${y}px, 0) scale(1.08)`;
}

/* ---------- счётчики статистики, считают один раз при появлении ---------- */
const stats = ref([
    { label: 'игроков в проекте', target: 2860, value: 0, suffix: '+' },
    { label: 'одобренных анкет', target: 1140, value: 0, suffix: '+' },
    { label: 'активных фракций', target: 24, value: 0, suffix: '' },
    { label: 'лет на рынке RP', target: 3, value: 0, suffix: '' },
]);
const statsEl = ref(null);
let statsAnimated = false;
function animateStats() {
    if (statsAnimated) return;
    statsAnimated = true;
    stats.value.forEach((stat, i) => {
        const duration = 1800;
        const start = performance.now();
        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            stat.value = Math.floor(eased * stat.target);
            if (progress < 1) requestAnimationFrame(tick);
            else stat.value = stat.target;
        }
        setTimeout(() => requestAnimationFrame(tick), i * 120);
    });
}
let statsObserver;

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('mousemove', onMouseMove, { passive: true });

    statsObserver = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.isIntersecting && animateStats()),
        { threshold: 0.4 },
    );
    if (statsEl.value) statsObserver.observe(statsEl.value);
});
onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('mousemove', onMouseMove);
    statsObserver?.disconnect();
});

const features = [
    {
        title: 'Ручной отбор анкет',
        text: 'Каждую заявку читает живой человек — никакой автоматической штамповки. На сервере только те, кто пришёл играть, а не портить атмосферу.',
    },
    {
        title: 'Авторская экономика',
        text: 'Баланс собран вручную и проверен сотнями часов тестов: заработок соответствует риску, а не превращается в гонку ботов.',
    },
    {
        title: 'Фракции с историей',
        text: 'У каждой структуры — своя легенда, территория и внутренняя иерархия. Вступая, вы получаете не роль, а сюжет.',
    },
    {
        title: 'Техническая стабильность',
        text: 'Собственная инфраструктура, мониторинг 24/7 и античит — сервер работает без даунтаймов и абуза уязвимостей.',
    },
    {
        title: 'Модерация без произвола',
        text: 'Прозрачные правила, публичный реестр наказаний и апелляции — решения администрации всегда можно оспорить.',
    },
    {
        title: 'Закрытое сообщество',
        text: 'Вход через анкету и подтверждение в Telegram-боте отсекает случайных людей — на сервере устойчивый костяк игроков.',
    },
];
</script>

<template>
    <Head title="Monsory RP — премиальный GTA V Roleplay" />

    <div class="bg-obsidian-950 font-sans text-white/80 antialiased">
        <!-- ================= NAV ================= -->
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-500"
            :class="scrolled ? 'bg-obsidian-950/85 backdrop-blur-md border-b border-white/5 py-3' : 'bg-transparent py-6'"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6">
                <span class="font-display text-2xl tracking-[0.3em] text-gradient-gold">MONSORY</span>

                <nav class="hidden items-center gap-10 text-sm tracking-wide text-white/60 md:flex">
                    <a href="#world" class="transition-colors hover:text-gold-300">О проекте</a>
                    <a href="#features" class="transition-colors hover:text-gold-300">Возможности</a>
                    <a href="#story" class="transition-colors hover:text-gold-300">История</a>
                    <a href="#join" class="transition-colors hover:text-gold-300">Вступить</a>
                </nav>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="canLogin && !$page.props.auth?.user"
                        :href="route('login')"
                        class="hidden text-sm text-white/60 transition-colors hover:text-white sm:block"
                    >
                        Вход
                    </Link>
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="hidden text-sm text-white/60 transition-colors hover:text-white sm:block"
                    >
                        Кабинет
                    </Link>
                    <a
                        :href="telegramBotUrl"
                        target="_blank"
                        rel="noopener"
                        class="group relative overflow-hidden rounded-full border border-gold-400/40 px-5 py-2 text-xs font-medium tracking-widest text-gold-200 transition-all hover:border-gold-300 hover:shadow-gold"
                    >
                        <span class="relative z-10">ПОДАТЬ ЗАЯВКУ</span>
                        <span class="absolute inset-0 -translate-x-full bg-gold-400/10 transition-transform duration-500 group-hover:translate-x-0"></span>
                    </a>
                </div>
            </div>
        </header>

        <!-- ================= HERO ================= -->
        <section class="relative flex min-h-screen items-end overflow-hidden">
            <div
                ref="heroLayer"
                class="absolute inset-0 -z-20 bg-cover bg-center transition-transform duration-300 ease-out"
                style="background-image: url('/images/hero/chase.jpg'); transform: scale(1.08)"
            ></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-obsidian-950/70 to-obsidian-950/20"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-obsidian-950/90 via-transparent to-obsidian-950/60"></div>
            <div class="bg-noise absolute inset-0 -z-10"></div>

            <!-- едва заметные плавающие частицы для глубины -->
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
                <span
                    v-for="n in 14"
                    :key="n"
                    class="absolute block h-1 w-1 rounded-full bg-gold-300/40 animate-float-slow"
                    :style="{
                        left: `${(n * 137) % 100}%`,
                        top: `${(n * 71) % 100}%`,
                        animationDelay: `${(n % 7) * 0.6}s`,
                        animationDuration: `${6 + (n % 5)}s`,
                    }"
                ></span>
            </div>

            <div class="relative mx-auto w-full max-w-7xl px-6 pb-28 pt-40">
                <p
                    v-reveal
                    class="mb-5 text-sm font-medium uppercase tracking-[0.45em] text-gold-300/90"
                >
                    Los Santos Roleplay · GTA V
                </p>
                <h1
                    v-reveal:150
                    class="font-display max-w-4xl text-5xl font-semibold leading-[1.05] text-white sm:text-6xl lg:text-7xl"
                >
                    История, которую
                    <span class="text-gradient-gold italic">пишете вы</span>
                </h1>
                <p
                    v-reveal:300
                    class="mt-7 max-w-xl text-lg leading-relaxed text-white/60"
                >
                    Закрытый ролплей-проект с ручным отбором, авторскими фракциями
                    и экономикой, в которую хочется возвращаться каждый вечер.
                </p>

                <div v-reveal:450 class="mt-10 flex flex-wrap items-center gap-5">
                    <a
                        :href="telegramBotUrl"
                        target="_blank"
                        rel="noopener"
                        class="animate-shimmer group relative overflow-hidden rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 bg-[length:200%_auto] px-8 py-4 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                    >
                        Подать анкету в Telegram
                    </a>
                    <a
                        href="#world"
                        class="text-sm font-medium uppercase tracking-widest text-white/50 transition-colors hover:text-white"
                    >
                        Узнать о проекте ↓
                    </a>
                </div>
            </div>

            <div class="absolute bottom-8 left-1/2 hidden -translate-x-1/2 flex-col items-center gap-2 text-white/30 sm:flex">
                <span class="h-10 w-px animate-pulse-glow bg-gradient-to-b from-gold-300 to-transparent"></span>
            </div>
        </section>

        <!-- ================= STATS ================= -->
        <section ref="statsEl" class="relative z-10 -mt-16 px-6">
            <div class="mx-auto grid max-w-6xl grid-cols-2 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-md sm:grid-cols-4">
                <div
                    v-for="(stat, i) in stats"
                    :key="stat.label"
                    v-reveal="'scale'"
                    :style="{ transitionDelay: `${i * 90}ms` }"
                    class="bg-obsidian-950/60 px-6 py-10 text-center"
                >
                    <div class="font-display text-4xl font-semibold text-gold-300">
                        {{ stat.value.toLocaleString('ru-RU') }}{{ stat.suffix }}
                    </div>
                    <div class="mt-2 text-xs uppercase tracking-widest text-white/40">
                        {{ stat.label }}
                    </div>
                </div>
            </div>
        </section>

        <!-- бегущая строка-разделитель -->
        <div class="my-28 overflow-hidden border-y border-white/5 py-4 opacity-60">
            <div class="animate-marquee flex w-max gap-10 whitespace-nowrap text-sm uppercase tracking-[0.4em] text-white/30">
                <span v-for="n in 2" :key="n" class="flex gap-10">
                    <span>Los Santos Roleplay</span>
                    <span class="text-gold-400">•</span>
                    <span>Monsory</span>
                    <span class="text-gold-400">•</span>
                    <span>Закрытый набор</span>
                    <span class="text-gold-400">•</span>
                    <span>Авторские фракции</span>
                    <span class="text-gold-400">•</span>
                </span>
            </div>
        </div>

        <!-- ================= МИР ПРОЕКТА ================= -->
        <section id="world" class="relative mx-auto max-w-7xl px-6 py-10">
            <div class="grid items-center gap-16 lg:grid-cols-2">
                <div v-reveal="'left'" class="relative">
                    <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-gold-400/20 via-transparent to-transparent blur-2xl"></div>
                    <img
                        src="/images/hero/city-sunset.jpg"
                        alt="Los Santos на закате"
                        class="relative rounded-2xl border border-white/10 shadow-2xl shadow-black/60"
                    />
                </div>
                <div v-reveal="'right'">
                    <p class="mb-4 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">О проекте</p>
                    <h2 class="font-display text-4xl font-semibold leading-tight text-white sm:text-5xl">
                        Мир, в который
                        <span class="text-gradient-gold italic">хочется возвращаться</span>
                    </h2>
                    <p class="mt-6 leading-relaxed text-white/60">
                        Monsory RP — это не очередной сервер с шаблонными фракциями и
                        накрученной статистикой. Мы строим город, где каждое решение
                        игрока имеет вес: от выбора первой работы до места во
                        внутренней иерархии структуры.
                    </p>
                    <ul class="mt-8 space-y-4">
                        <li
                            v-for="(item, i) in [
                                'Живая экономика без пэй-ту-вин механик',
                                'Кастомные локации и авторские сюжетные линии',
                                'Регулярные городские события с реальными последствиями',
                            ]"
                            :key="item"
                            v-reveal
                            :style="{ transitionDelay: `${i * 100}ms` }"
                            class="flex items-start gap-3 text-white/70"
                        >
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold-400"></span>
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- ================= FEATURES ================= -->
        <section id="features" class="relative mx-auto max-w-7xl px-6 py-32">
            <div v-reveal class="mx-auto mb-16 max-w-2xl text-center">
                <p class="mb-4 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">Возможности</p>
                <h2 class="font-display text-4xl font-semibold text-white sm:text-5xl">
                    Почему выбирают <span class="text-gradient-gold italic">Monsory</span>
                </h2>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(feature, i) in features"
                    :key="feature.title"
                    v-reveal="'scale'"
                    :style="{ transitionDelay: `${(i % 3) * 120}ms` }"
                    class="group relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] p-8 transition-all duration-500 hover:-translate-y-1.5 hover:border-gold-400/30 hover:bg-white/[0.05] hover:shadow-gold"
                >
                    <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-gold-400/10 blur-2xl transition-opacity duration-500 group-hover:opacity-100 opacity-0"></div>
                    <div class="font-display mb-4 text-3xl text-gold-400/80">{{ String(i + 1).padStart(2, '0') }}</div>
                    <h3 class="mb-3 text-lg font-semibold text-white">{{ feature.title }}</h3>
                    <p class="text-sm leading-relaxed text-white/55">{{ feature.text }}</p>
                </div>
            </div>
        </section>

        <!-- ================= ИСТОРИЯ / ЦИТАТА ================= -->
        <section id="story" class="relative overflow-hidden py-40">
            <div
                class="absolute inset-0 -z-20 bg-cover bg-center"
                style="background-image: url('/images/hero/trio.jpg')"
            ></div>
            <div class="absolute inset-0 -z-10 bg-obsidian-950/85"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-transparent to-obsidian-950/60"></div>

            <div class="relative mx-auto max-w-4xl px-6 text-center">
                <p v-reveal class="mb-6 text-sm font-medium uppercase tracking-[0.4em] text-gold-300/90">
                    Три года истории
                </p>
                <blockquote v-reveal:150 class="font-display text-3xl italic leading-snug text-white/90 sm:text-4xl">
                    «Мы начинали как небольшая команда энтузиастов — сегодня Monsory
                    это устойчивое сообщество с собственными традициями, легендами
                    и игроками, которые прошли путь от новичка до легенды города».
                </blockquote>
                <p v-reveal:300 class="mt-8 text-sm uppercase tracking-widest text-white/40">
                    — команда разработки Monsory RP
                </p>
            </div>
        </section>

        <!-- ================= CTA ================= -->
        <section id="join" class="relative overflow-hidden py-36">
            <div
                class="absolute inset-0 -z-20 bg-cover bg-center"
                style="background-image: url('/images/hero/cta-sunset.jpg')"
            ></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-obsidian-950/80 to-obsidian-950/40"></div>

            <div class="relative mx-auto max-w-3xl px-6 text-center">
                <h2 v-reveal class="font-display text-4xl font-semibold text-white sm:text-5xl">
                    Готовы войти
                    <span class="text-gradient-gold italic">в город?</span>
                </h2>
                <p v-reveal:150 class="mx-auto mt-6 max-w-xl text-white/60">
                    Заполните анкету в нашем Telegram-боте — рассмотрение занимает
                    от нескольких часов до суток. Мы отвечаем каждому.
                </p>
                <div v-reveal:300 class="mt-10 flex flex-wrap items-center justify-center gap-5">
                    <a
                        :href="telegramBotUrl"
                        target="_blank"
                        rel="noopener"
                        class="animate-shimmer rounded-full bg-gradient-to-r from-gold-500 via-gold-300 to-gold-500 bg-[length:200%_auto] px-10 py-4 text-sm font-semibold uppercase tracking-widest text-obsidian-950 shadow-gold transition-transform hover:scale-[1.03]"
                    >
                        Открыть бота и подать заявку
                    </a>
                    <Link
                        v-if="canRegister && !$page.props.auth?.user"
                        :href="route('register')"
                        class="rounded-full border border-white/15 px-10 py-4 text-sm font-medium uppercase tracking-widest text-white/70 transition-colors hover:border-white/30 hover:text-white"
                    >
                        Создать аккаунт на сайте
                    </Link>
                </div>
            </div>
        </section>

        <!-- ================= FOOTER ================= -->
        <footer class="border-t border-white/5 px-6 py-14">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 text-sm text-white/40 sm:flex-row">
                <span class="font-display text-lg tracking-[0.3em] text-gold-300/70">MONSORY</span>
                <p>© {{ new Date().getFullYear() }} Monsory RP. Проект не связан с Rockstar Games.</p>
                <a :href="telegramBotUrl" target="_blank" rel="noopener" class="transition-colors hover:text-gold-300">
                    Telegram
                </a>
            </div>
        </footer>
    </div>
</template>
