<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AmbientBackground from '@/Components/AmbientBackground.vue';
import ScrollProgress from '@/Components/ScrollProgress.vue';

const props = defineProps({
    canLogin: { type: Boolean, default: false },
    canRegister: { type: Boolean, default: false },
    memberCount: { type: Number, default: 0 },
    telegramBotUrl: { type: String, default: '#' },
});

/* ---------- шапка: прозора -> скляна під час скролу ---------- */
const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 24;
}

/* ---------- мобільне меню ----------
   Десктопна навігація ховається вже з планшета, тому без цього меню на
   телефоні й планшеті сторінка лишалась зовсім без навігації — тільки скрол. */
const mobileNavOpen = ref(false);

function setMobileNav(open) {
    mobileNavOpen.value = open;
    // Фон не має скролитись під відкритим оверлеєм — інакше при закритті
    // користувач опиняється зовсім не там, де відкривав меню. Знімати
    // блокування треба в КОЖНОМУ шляху закриття, тому це одна функція,
    // а не дві окремі, де легко забути про overflow.
    document.body.style.overflow = open ? 'hidden' : '';
}

function closeMobileNav() {
    setMobileNav(false);
}

function toggleMobileNav() {
    setMobileNav(!mobileNavOpen.value);
}

/* ---------- підсвічування активного пункту навігації ----------
   Без цього довга сторінка "губить" читача: він не розуміє, у якому розділі
   зараз перебуває. Спостерігаємо самі секції, а не рахуємо offset вручну —
   ресайз і зміна висоти контенту не ламають логіку. */
const activeSection = ref('');
let sectionObserver;

const navLinks = [
    { id: 'structure', label: 'Структура' },
    { id: 'family', label: 'Родина' },
    { id: 'showcase', label: 'Verba Online' },
    { id: 'goals', label: 'Цілі' },
    { id: 'legacy', label: 'Спадщина' },
];

/* ---------- лічильник учасників — рахує один раз, коли з'являється у в'юпорті ---------- */
const memberCountEl = ref(null);
const memberCountValue = ref(0);
let countAnimated = false;
let countObserver;

function animateMemberCount() {
    if (countAnimated) return;
    countAnimated = true;
    const target = props.memberCount;
    const duration = 1600;
    const start = performance.now();
    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 4);
        memberCountValue.value = Math.floor(eased * target);
        if (progress < 1) requestAnimationFrame(tick);
        else memberCountValue.value = target;
    }
    requestAnimationFrame(tick);
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });

    countObserver = new IntersectionObserver(
        (entries) => entries.forEach((e) => e.isIntersecting && animateMemberCount()),
        { threshold: 0.4 },
    );
    if (memberCountEl.value) countObserver.observe(memberCountEl.value);

    sectionObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) activeSection.value = e.target.id;
            });
        },
        // Вузька смуга по центру екрана: секція вважається активною лише коли
        // вона реально в фокусі погляду, а не щойно торкнулась краю вьюпорта.
        { rootMargin: '-45% 0px -45% 0px' },
    );
    navLinks.forEach(({ id }) => {
        const el = document.getElementById(id);
        if (el) sectionObserver.observe(el);
    });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    countObserver?.disconnect();
    sectionObserver?.disconnect();
    // Інакше сторінка, залишена з відкритим меню, «замерзає» без скролу
    // після переходу в кабінет.
    document.body.style.overflow = '';
});

const year = computed(() => new Date().getFullYear());

const pillars = [
    {
        title: 'Структура',
        text: 'Чітка ієрархія родини: ролі, зони відповідальності та внутрішній порядок — не хаотичний чат, а організована спільнота.',
    },
    {
        title: 'Рейтинги',
        text: 'Особистий прогрес і ранги учасників з’являться на порталі одразу, як тільки буде активовано модуль Progression — архітектура вже готова прийняти його.',
    },
    {
        title: 'Звітність',
        text: 'Прозора система звітів за операціями та контрактами — кожна дія фіксується і може бути перевірена, без "на слово".',
    },
    {
        title: 'Прогрес',
        text: 'Особисті цілі та цілі родини в одному місці, з реальним трекінгом виконання — не косметика, а робочий інструмент.',
    },
];

const paths = [
    {
        title: 'Виконавець',
        text: 'Операції, контракти, робота "в полі". Результат вимірюється закритими задачами, а не словами в чаті.',
        image: '/images/roles/franklin.webp',
    },
    {
        title: 'Організатор',
        text: 'Координація складу, розподіл ролей і контроль строків. Тримає структуру родини в робочому стані.',
        image: '/images/roles/michael-rifle.webp',
    },
    {
        title: 'Стратег',
        text: 'Довгі цілі, репутація родини та відносини із зовнішнім світом Verba Online.',
        image: '/images/roles/michael-pistol.webp',
    },
];
</script>

<template>
    <Head title="Monsory Family — Verba Online" />

    <!-- Умышленно БЕЗ bg-obsidian-950: непрозрачный фон на этой обёртке
         закрашивал собой все слои с отрицательным z-index — фото героя,
         кинематографические фоны секций и аврора-подсветку. Цвет страницы
         задан на body в app.css, поэтому здесь он не нужен. -->
    <div class="relative font-sans text-white/80 antialiased">
        <ScrollProgress />
        <AmbientBackground mobile intensity="rich" />

        <!-- ================= NAV ================= -->
        <header
            class="fixed inset-x-0 top-0 z-50 transition-all duration-700"
            :class="scrolled ? 'py-3' : 'py-6'"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div
                    class="flex items-center justify-between rounded-full px-5 py-3 transition-all duration-700 sm:px-6"
                    :class="scrolled ? 'glass-pill' : ''"
                >
                    <!-- На вузьких телефонах (360px) повний лого + кнопка
                         входу + бургер не вміщались в один рядок: напис
                         "УВІЙТИ" ламався на два рядки. Тому тут і трекінг,
                         і розмір, і другий рядок лого — адаптивні. -->
                    <a href="#top" class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <ApplicationLogo mark class="h-8 w-8 shrink-0 text-base sm:h-9 sm:w-9 sm:text-lg" />
                        <div class="min-w-0 leading-tight">
                            <div class="font-display text-base tracking-[0.18em] text-white sm:text-lg sm:tracking-[0.28em]">MONSORY</div>
                            <div class="hidden text-[10px] tracking-[0.38em] text-gold-300/70 xs:block">CONNECT</div>
                        </div>
                    </a>

                    <!-- На планшеті (md) лишаємо скорочену навігацію, на ноуті
                         й ПК — повну: ховати все до 1024px означало б віддати
                         половині пристроїв найгірший варіант без потреби. -->
                    <nav class="hidden items-center gap-7 text-[13px] tracking-wide text-white/55 md:flex lg:gap-9">
                        <a
                            v-for="(link, i) in navLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            class="link-underline py-1 transition-colors duration-300 hover:text-white"
                            :class="[
                                activeSection === link.id && 'text-white',
                                i > 2 && 'hidden lg:inline',
                            ]"
                            :data-active="activeSection === link.id"
                        >
                            {{ link.label }}
                        </a>
                    </nav>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="glass-pill btn-ghost whitespace-nowrap px-3.5 py-2 text-[10px] font-medium tracking-[0.15em] text-white/80 xs:text-[11px] xs:tracking-[0.2em] sm:px-5"
                        >
                            КАБІНЕТ
                        </Link>
                        <Link
                            v-else-if="canLogin"
                            :href="route('login')"
                            class="glass-pill btn-ghost whitespace-nowrap px-3.5 py-2 text-[10px] font-medium tracking-[0.15em] text-gold-200 xs:text-[11px] xs:tracking-[0.2em] sm:px-5"
                        >
                            УВІЙТИ ↗
                        </Link>

                        <button
                            class="glass-pill flex h-9 w-9 items-center justify-center text-white/70 transition-colors hover:text-white md:hidden"
                            :aria-expanded="mobileNavOpen"
                            aria-label="Меню"
                            @click="toggleMobileNav"
                        >
                            <span class="relative block h-3 w-4">
                                <span
                                    class="absolute left-0 block h-px w-full bg-current transition-all duration-500"
                                    :class="mobileNavOpen ? 'top-1.5 rotate-45' : 'top-0'"
                                ></span>
                                <span
                                    class="absolute left-0 top-1.5 block h-px w-full bg-current transition-opacity duration-300"
                                    :class="mobileNavOpen && 'opacity-0'"
                                ></span>
                                <span
                                    class="absolute left-0 block h-px w-full bg-current transition-all duration-500"
                                    :class="mobileNavOpen ? 'top-1.5 -rotate-45' : 'top-3'"
                                ></span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ================= МОБІЛЬНЕ МЕНЮ ================= -->
        <Transition
            enter-active-class="transition-opacity duration-500"
            leave-active-class="transition-opacity duration-400"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 md:hidden">
                <div class="absolute inset-0 bg-obsidian-950/95 backdrop-blur-2xl" @click="toggleMobileNav"></div>

                <nav class="relative flex h-full flex-col justify-center gap-2 px-8">
                    <a
                        v-for="(link, i) in navLinks"
                        :key="link.id"
                        :href="`#${link.id}`"
                        class="animate-fade-in-up border-b border-white/5 py-5 font-display text-3xl text-white/80 transition-colors active:text-gold-300"
                        :style="{ animationDelay: `${i * 70}ms` }"
                        @click="toggleMobileNav"
                    >
                        <span class="mr-4 align-middle font-sans text-[11px] tracking-[0.3em] text-gold-400/60">
                            0{{ i + 1 }}
                        </span>
                        {{ link.label }}
                    </a>

                    <a
                        :href="telegramBotUrl"
                        target="_blank"
                        rel="noopener"
                        class="btn-gold animate-fade-in-up mt-10 py-4 text-center text-[13px] font-semibold uppercase tracking-[0.18em]"
                        :style="{ animationDelay: `${navLinks.length * 70}ms` }"
                        @click="closeMobileNav"
                    >
                        Подати заявку
                    </a>
                </nav>
            </div>
        </Transition>

        <!-- ================= HERO ================= -->
        <section id="top" class="relative flex min-h-[100svh] items-center overflow-hidden">
            <!-- Фон їде повільніше за контент — сцена читається як кадр із
                 глибиною, а не як пласка підкладка під текстом. Шкала трохи
                 більша за 1, щоб зсув не оголив край зображення. -->
            <div class="absolute inset-0 -z-20 scale-110 overflow-hidden">
                <!-- В герое — самый кинематографичный кадр из всех: закат,
                     пальмы, силуэт города. Арт с персонажами сюда не годится:
                     в широком кропе у них срезаются лица, а сами фигуры
                     дерутся с текстом за один и тот же центр внимания. Их
                     место — вырезом в секции «Родина» и фоном в «Цілі». -->
                <div
                    v-parallax="0.12"
                    class="absolute inset-0 bg-cover bg-center"
                    style="background-image: url('/images/hero/city-sunset.jpg')"
                ></div>
            </div>

            <!-- Три градієнти замість одного: бічний тримає читабельність
                 тексту, нижній зшиває кадр із наступною секцією, верхній
                 прибирає конфлікт зображення з навігацією. -->
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-obsidian-950 via-obsidian-950/90 to-obsidian-950/45"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-transparent to-obsidian-950/60"></div>
            <div class="cine-veil -z-10"></div>
            <div class="bg-noise absolute inset-0 -z-10 opacity-60"></div>

            <div class="relative mx-auto w-full max-w-7xl px-4 py-32 sm:px-6">
                <div v-glow class="glass-panel max-w-2xl px-7 py-9 sm:px-11 sm:py-14">
                    <p v-reveal class="mb-6 text-[10px] font-medium uppercase tracking-[0.3em] text-gold-300/90 sm:text-xs sm:tracking-[0.5em]">
                        Monsory Family · Verba Online
                    </p>

                    <h1 v-reveal:150 class="text-balance font-display text-[2.75rem] font-semibold leading-[1.02] text-white sm:text-6xl lg:text-7xl">
                        Сила. Порядок.
                        <span class="text-gradient-gold italic">Спадщина.</span>
                    </h1>

                    <p v-reveal:300 class="mt-7 max-w-xl text-base leading-relaxed text-white/60 sm:text-lg">
                        Закрита сім'я, де статус підтверджують діями, розвиток
                        вимірюється результатом, а сайт і Telegram працюють як
                        одна система.
                    </p>

                    <div v-reveal:450 class="mt-10 flex flex-wrap items-center gap-4 sm:gap-6">
                        <a
                            :href="telegramBotUrl"
                            target="_blank"
                            rel="noopener"
                            v-magnetic
                            class="btn-gold px-8 py-4 text-[13px] font-semibold uppercase tracking-[0.18em]"
                        >
                            Подати заявку
                        </a>
                        <a
                            href="#structure"
                            class="link-underline text-[13px] font-medium uppercase tracking-[0.18em] text-white/50 transition-colors hover:text-white"
                        >
                            Дізнатись більше
                        </a>
                    </div>

                    <!-- Реальна цифра з БД, без вигаданих KPI поруч -->
                    <div ref="memberCountEl" v-reveal:600 class="mt-12 flex items-baseline gap-3 border-t border-white/10 pt-6">
                        <span class="font-display text-4xl text-gold-300">{{ memberCountValue }}</span>
                        <span class="text-[11px] uppercase tracking-[0.25em] text-white/40">активних учасників</span>
                    </div>
                </div>
            </div>

            <!-- Підказка скролу: дихаюча вертикальна нитка. -->
            <div class="pointer-events-none absolute inset-x-0 bottom-8 hidden justify-center lg:flex">
                <div class="flex flex-col items-center gap-3">
                    <span class="text-[10px] uppercase tracking-[0.4em] text-white/25">Скрол</span>
                    <span class="animate-pulse-glow h-12 w-px bg-gradient-to-b from-gold-300/70 to-transparent"></span>
                </div>
            </div>
        </section>

        <!-- бегущая строка-разделитель -->
        <div class="relative overflow-hidden border-y border-white/5 py-4">
            <div class="animate-marquee flex w-max gap-10 whitespace-nowrap text-xs uppercase tracking-[0.45em] text-white/25">
                <span v-for="n in 2" :key="n" class="flex gap-10">
                    <span>Verba Online</span>
                    <span class="text-gold-400/70">◆</span>
                    <span>Monsory Family</span>
                    <span class="text-gold-400/70">◆</span>
                    <span>Est. Legacy</span>
                    <span class="text-gold-400/70">◆</span>
                    <span>Закритий набір</span>
                    <span class="text-gold-400/70">◆</span>
                </span>
            </div>
            <!-- Краї стрічки гаснуть у фон: різкий обрив тексту на межі екрана
                 одразу видає «крутилку», а не елемент дизайну. -->
            <div class="pointer-events-none absolute inset-y-0 left-0 w-24 bg-gradient-to-r from-obsidian-950 to-transparent"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-24 bg-gradient-to-l from-obsidian-950 to-transparent"></div>
        </div>

        <!-- ================= СТРУКТУРА ================= -->
        <section id="structure" class="relative mx-auto max-w-7xl px-4 py-24 sm:px-6 sm:py-28">
            <div v-reveal class="mx-auto mb-16 max-w-2xl text-center">
                <p class="mb-4 text-[11px] font-medium uppercase tracking-[0.45em] text-gold-300/90">Як влаштована родина</p>
                <h2 class="text-balance font-display text-4xl font-semibold text-white sm:text-5xl">
                    Чотири опори <span class="text-gradient-gold italic">Monsory</span>
                </h2>
            </div>

            <!-- 4 колонки тільки з 1280px: на ноуті 1024–1280 чотири картки
                 стискаються до ~220px і текст розсипається на 8 рядків,
                 тому там 2×2. -->
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="(pillar, i) in pillars"
                    :key="pillar.title"
                    v-reveal="'scale'"
                    :style="{ transitionDelay: `${i * 110}ms` }"
                    v-glow
                    class="group glass-panel relative overflow-hidden p-7 transition-all duration-700 hover:-translate-y-2"
                >
                    <div class="glass-sheen"></div>
                    <div class="absolute -right-10 -top-10 h-28 w-28 rounded-full bg-gold-400/20 blur-2xl opacity-0 transition-opacity duration-700 group-hover:opacity-100"></div>

                    <div class="text-outline-gold font-display relative mb-5 text-4xl leading-none transition-colors duration-500 group-hover:text-gold-400/25">
                        {{ String(i + 1).padStart(2, '0') }}
                    </div>
                    <h3 class="relative mb-3 text-lg font-semibold text-white">{{ pillar.title }}</h3>
                    <p class="relative text-sm leading-relaxed text-white/55">{{ pillar.text }}</p>
                </div>
            </div>
        </section>

        <!-- ================= РОДИНА: ТРИ РОЛІ ================= -->
        <!-- Вирізані фігури, а не фонові кадри: кожна роль отримує власного
             персонажа, який стоїть "у кімнаті" сторінки — зі своїм світлом
             позаду і відображенням під ногами, а не наклеєний на темряву. -->
        <section id="family" class="relative overflow-hidden py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6">
                <div v-reveal class="mx-auto mb-16 max-w-2xl text-center">
                    <p class="mb-4 text-[11px] font-medium uppercase tracking-[0.45em] text-gold-300/90">Склад родини</p>
                    <h2 class="text-balance font-display text-4xl font-semibold text-white sm:text-5xl">
                        Три ролі. <span class="text-gradient-gold italic">Одна родина.</span>
                    </h2>
                    <p class="mx-auto mt-6 max-w-xl leading-relaxed text-white/55">
                        Всередині Monsory немає "просто учасників". Кожен заходить
                        у родину зі своєю сильною стороною — і саме під неї
                        отримує зону відповідальності.
                    </p>
                </div>

                <!-- На телефоні — одна колонка, з планшета — три: ужимати три
                     фігури в 390px означало б зробити їх нечитабельними. -->
                <div class="grid gap-6 sm:grid-cols-3 sm:gap-5 lg:gap-6">
                    <div
                        v-for="(path, i) in paths"
                        :key="path.title"
                        v-reveal="'scale'"
                        :style="{ transitionDelay: `${i * 130}ms` }"
                        v-glow
                        class="group glass-panel relative flex flex-col overflow-hidden px-6 pb-7 pt-8 transition-all duration-700 hover:-translate-y-2"
                    >
                        <div class="glass-sheen"></div>

                        <!-- Сцена персонажа: контрове світло + сама фігура +
                             відображення. Фіксована висота тримає нижні краї
                             карток на одній лінії, навіть коли вирізи мають
                             різні пропорції. -->
                        <div class="relative mb-6 h-56 sm:h-52 lg:h-64">
                            <div
                                class="pointer-events-none absolute left-1/2 top-1/4 h-40 w-40 -translate-x-1/2 rounded-full bg-gold-500/20 blur-[60px] transition-all duration-700 group-hover:bg-gold-400/30 lg:h-48 lg:w-48"
                            ></div>
                            <div
                                class="pointer-events-none absolute left-1/2 top-1/2 h-28 w-28 -translate-x-1/2 rounded-full bg-aurora-500/20 blur-[55px]"
                            ></div>

                            <img
                                :src="path.image"
                                :alt="`Роль у родині: ${path.title}`"
                                loading="lazy"
                                decoding="async"
                                class="img-cine relative mx-auto h-full w-auto object-contain drop-shadow-[0_28px_45px_rgba(0,0,0,0.8)] transition-transform duration-700 group-hover:-translate-y-1.5"
                            />

                            <!-- Відображення "підлоги": фігура отримує опору,
                                 а не висить у порожнечі. -->
                            <img
                                :src="path.image"
                                alt=""
                                aria-hidden="true"
                                loading="lazy"
                                decoding="async"
                                class="pointer-events-none absolute inset-x-0 top-full mx-auto h-16 w-auto -scale-y-100 object-contain opacity-20 blur-[2px] [mask-image:linear-gradient(to_top,transparent_15%,rgba(0,0,0,0.8))]"
                            />

                            <div class="pointer-events-none absolute inset-x-6 top-full h-px bg-gradient-to-r from-transparent via-gold-400/40 to-transparent"></div>
                        </div>

                        <!-- Без mt-auto: інакше у картки з коротшим описом
                             заголовок з'їжджає вниз і три назви ролей стоять
                             на різних лініях. Висота блока з фігурою фіксована,
                             тому заголовки й так вирівняні. -->
                        <div class="relative">
                            <div class="mb-3 flex items-center gap-3">
                                <span class="font-display text-sm text-gold-400/70">0{{ i + 1 }}</span>
                                <span class="h-px flex-1 bg-gradient-to-r from-gold-400/30 to-transparent"></span>
                            </div>
                            <h3 class="text-lg font-semibold text-white">{{ path.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-white/50">{{ path.text }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= SHOWCASE: VERBA ONLINE ================= -->
        <section id="showcase" class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div v-reveal class="mx-auto mb-14 max-w-2xl text-center">
                <p class="mb-4 text-[11px] font-medium uppercase tracking-[0.45em] text-gold-300/90">Атмосфера</p>
                <h2 class="text-balance font-display text-4xl font-semibold text-white sm:text-5xl">
                    Життя всередині <span class="text-gradient-gold italic">Verba Online</span>
                </h2>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <div v-reveal="'left'" v-glow class="group glass-panel relative aspect-[4/3] overflow-hidden sm:aspect-[16/10]">
                    <img
                        src="/images/hero/ferrari-daylight.jpg"
                        alt="Verba Online — вулиці міста на світанку"
                        loading="lazy"
                        decoding="async"
                        class="img-cine h-full w-full object-cover group-hover:scale-[1.05]"
                    />
                    <div class="cine-veil"></div>
                    <div class="glass-sheen z-10"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6">
                        <p class="text-[10px] font-medium uppercase tracking-[0.4em] text-gold-300/90">Стиль</p>
                        <p class="mt-1.5 font-display text-xl text-white">Своя естетика, свій темп</p>
                    </div>
                </div>

                <div v-reveal="'right'" v-glow class="group glass-panel relative aspect-[4/3] overflow-hidden sm:aspect-[16/10]">
                    <img
                        src="/images/hero/chase.jpg"
                        alt="Verba Online — нічна погоня вулицями міста"
                        loading="lazy"
                        decoding="async"
                        class="img-cine h-full w-full object-cover group-hover:scale-[1.05]"
                    />
                    <div class="cine-veil"></div>
                    <div class="glass-sheen z-10"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6">
                        <p class="text-[10px] font-medium uppercase tracking-[0.4em] text-gold-300/90">Темп</p>
                        <p class="mt-1.5 font-display text-xl text-white">Ніч, у якій вирішує швидкість</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= CINEMATIC: РІЗНІ ЦІЛІ ================= -->
        <section id="goals" class="relative overflow-hidden py-32 sm:py-40">
            <div class="absolute inset-0 -z-20 scale-110 overflow-hidden">
                <!-- Арт с персонажами уместен именно здесь: под плотной
                     затемняющей заливкой его выцветшие цвета не читаются как
                     дефект, а силуэты поддерживают мысль «разные цели». -->
                <div
                    v-parallax="0.1"
                    class="absolute inset-0 bg-cover bg-center"
                    style="background-image: url('/images/hero/trio.jpg')"
                ></div>
            </div>
            <div class="absolute inset-0 -z-10 bg-obsidian-950/85"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-transparent to-obsidian-950/70"></div>
            <div class="cine-veil -z-10"></div>

            <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6">
                <div v-glow class="glass-panel mx-auto inline-block px-7 py-10 sm:px-14 sm:py-16">
                    <p v-reveal class="mb-6 text-[11px] font-medium uppercase tracking-[0.45em] text-gold-300/90">
                        Ціль родини
                    </p>
                    <blockquote v-reveal:150 class="text-balance font-display text-3xl italic leading-snug text-white/90 sm:text-4xl">
                        Різні цілі.
                        <span class="text-gradient-gold">Спільний результат.</span>
                    </blockquote>
                    <p v-reveal:300 class="mx-auto mt-8 max-w-xl leading-relaxed text-white/50">
                        У кожного учасника свій шлях і свої задачі всередині Verba
                        Online — але результат родини складається із суми дій
                        кожного, а не з обіцянок.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================= LEGACY CTA ================= -->
        <section id="legacy" class="relative overflow-hidden py-28 sm:py-36">
            <div class="absolute inset-0 -z-20 scale-110 overflow-hidden">
                <div
                    v-parallax="0.08"
                    class="absolute inset-0 bg-cover bg-center"
                    style="background-image: url('/images/hero/cta-sunset.jpg')"
                ></div>
            </div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-obsidian-950 via-obsidian-950/80 to-obsidian-950/45"></div>
            <div class="cine-veil -z-10"></div>

            <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6">
                <!-- Родина «виходить» із заходу сонця прямо над закликом
                     вступити: фігури дають фіналу обличчя, а не просто ще
                     одну кнопку на градієнті. -->
                <div v-reveal="'scale'" class="relative mx-auto mb-[-3rem] w-full max-w-sm sm:mb-[-4rem] sm:max-w-md">
                    <div class="pointer-events-none absolute left-1/2 top-1/3 h-56 w-56 -translate-x-1/2 rounded-full bg-gold-500/25 blur-[70px] sm:h-72 sm:w-72"></div>
                    <img
                        src="/images/roles/trio-wide.webp"
                        alt="Склад родини Monsory"
                        loading="lazy"
                        decoding="async"
                        class="animate-float-slow img-cine relative w-full drop-shadow-[0_30px_55px_rgba(0,0,0,0.85)]"
                    />
                </div>

                <div v-glow class="glass-panel-gold glass-panel relative mx-auto inline-block px-7 py-10 sm:px-14 sm:py-16">
                    <h2 v-reveal class="text-balance font-display text-4xl font-semibold text-white sm:text-5xl">
                        Кожна дія
                        <span class="text-gradient-gold italic">залишає слід.</span>
                    </h2>
                    <p v-reveal:150 class="mx-auto mt-6 max-w-xl leading-relaxed text-white/60">
                        Заповніть заявку в Telegram-боті — ми розглядаємо кожну
                        особисто, а не масово.
                    </p>
                    <div v-reveal:300 class="mt-10 flex flex-wrap items-center justify-center gap-4 sm:gap-5">
                        <a
                            :href="telegramBotUrl"
                            target="_blank"
                            rel="noopener"
                            v-magnetic
                            class="btn-gold px-9 py-4 text-[13px] font-semibold uppercase tracking-[0.18em]"
                        >
                            Подати заявку в Telegram
                        </a>
                        <Link
                            v-if="canRegister && !$page.props.auth?.user"
                            :href="route('register')"
                            class="glass-pill btn-ghost px-9 py-4 text-[13px] font-medium uppercase tracking-[0.18em] text-white/70"
                        >
                            Створити акаунт
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= FOOTER ================= -->
        <footer class="relative px-4 pb-12 pt-16 sm:px-6">
            <div class="mx-auto max-w-7xl">
                <hr class="hairline mb-12" />

                <div class="flex flex-col items-center gap-10 sm:flex-row sm:items-start sm:justify-between">
                    <div class="text-center sm:text-left">
                        <div class="flex items-center justify-center gap-3 sm:justify-start">
                            <ApplicationLogo mark class="h-8 w-8 text-base" />
                            <span class="font-display text-lg tracking-[0.3em] text-white">MONSORY</span>
                        </div>
                        <p class="mt-4 max-w-xs text-sm leading-relaxed text-white/35">
                            Закрита родина Verba Online. Статус підтверджують
                            діями, а не словами.
                        </p>
                    </div>

                    <nav class="flex flex-col items-center gap-3 text-sm text-white/40 sm:items-start">
                        <span class="text-[10px] uppercase tracking-[0.35em] text-gold-300/60">Навігація</span>
                        <a
                            v-for="link in navLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            class="transition-colors duration-300 hover:text-gold-300"
                        >
                            {{ link.label }}
                        </a>
                    </nav>

                    <div class="flex flex-col items-center gap-3 text-sm text-white/40 sm:items-start">
                        <span class="text-[10px] uppercase tracking-[0.35em] text-gold-300/60">Звʼязок</span>
                        <a :href="telegramBotUrl" target="_blank" rel="noopener" class="transition-colors duration-300 hover:text-gold-300">
                            Telegram-бот
                        </a>
                        <Link v-if="canLogin" :href="route('login')" class="transition-colors duration-300 hover:text-gold-300">
                            Вхід у кабінет
                        </Link>
                    </div>
                </div>

                <hr class="hairline my-10" />

                <p class="text-center text-xs tracking-wide text-white/25">
                    © {{ year }} Monsory Family · Verba Online RP
                </p>
            </div>
        </footer>
    </div>
</template>
