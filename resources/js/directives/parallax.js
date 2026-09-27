// v-parallax="0.18" — фон едет медленнее контента, из-за чего сцена читается
// объёмной, а не как наклейка. Значение — доля высоты вьюпорта, на которую
// элемент смещается за полный проход через экран.
//
// Один общий scroll-обработчик и один rAF на все элементы: отдельный
// слушатель на каждом блоке — главная причина «резинового» скролла на слабых
// телефонах, когда параллакса на странице больше одного.

const items = new Map();
let ticking = false;
let listening = false;

const reduceMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function update() {
    ticking = false;
    const vh = window.innerHeight;

    items.forEach((speed, el) => {
        const rect = el.getBoundingClientRect();

        // Считаем только то, что видно (плюс запас) — за пределами экрана
        // вычисления и запись в style ничего не дают, кроме нагрузки.
        if (rect.bottom < -vh || rect.top > vh * 2) return;

        // -1 (элемент ниже экрана) ... 0 (по центру) ... 1 (выше экрана)
        const progress =
            (rect.top + rect.height / 2 - vh / 2) / (vh / 2 + rect.height / 2);

        el.style.transform = `translate3d(0, ${(progress * speed * vh).toFixed(2)}px, 0)`;
    });
}

function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(update);
}

function ensureListening() {
    if (listening) return;
    listening = true;
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
}

export const parallax = {
    mounted(el, binding) {
        if (reduceMotion()) return;

        el.style.willChange = 'transform';
        items.set(el, Number(binding.value) || 0.15);
        ensureListening();
        onScroll();
    },
    unmounted(el) {
        items.delete(el);
        if (items.size === 0 && listening) {
            listening = false;
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        }
    },
};
