import Lenis from 'lenis';
import { decorationsAllowed } from './motion';

/**
 * Инерционный скролл — то, что сильнее всего отличает «дорогой» сайт от
 * обычного: страница не дёргается по строкам колёсика, а доезжает.
 *
 * Только для колеса мыши. Тач НЕ трогаем: у системы уже есть своя
 * инерция, и вторая поверх неё ощущается как залипание, а не как
 * плавность — на телефоне это ухудшение, а не улучшение.
 */

let lenis = null;
let rafId = null;

function loop(time) {
    lenis?.raf(time);
    rafId = requestAnimationFrame(loop);
}

export function startSmoothScroll() {
    // Только там, где есть мышь. На тач-экране у системы своя инерция,
    // и вторая поверх неё ощущается как залипание — на телефоне это
    // ухудшение, а не «премиальность».
    if (lenis || !decorationsAllowed({ requiresFinePointer: true })) {
        return null;
    }

    lenis = new Lenis({
        // Подобрано под «доезжает, но не уплывает»: выше — и страница
        // начинает жить своей жизнью, ниже — эффекта не видно.
        duration: 1.05,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        smoothWheel: true,
        // Родная инерция тача остаётся нетронутой.
        syncTouch: false,
        touchMultiplier: 1,
    });

    // CSS-плавность и Lenis дерутся: браузер уводит скролл своим
    // аниматором, Lenis — своим, и прокрутка дёргается. Пока Lenis жив,
    // за плавность отвечает он один.
    document.documentElement.classList.add('lenis-active');

    rafId = requestAnimationFrame(loop);

    return lenis;
}

export function stopSmoothScroll() {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }

    lenis?.destroy();
    lenis = null;
    document.documentElement.classList.remove('lenis-active');
}

/**
 * Якорные переходы. Без этого «Посади» в шапке прыгает мгновенно, мимо
 * всей плавности, ради которой Lenis и ставился.
 *
 * Отступ под фиксированную шапку НЕ задаём здесь: Lenis уже учитывает
 * scroll-padding-top из CSS (у нас 6rem). Проверено замером — со своим
 * дополнительным смещением секция вставала ровно вдвое ниже, чем нужно.
 * Пусть высота шапки живёт в одном месте, в CSS.
 */
export function handleAnchorClick(event) {
    if (!lenis) return;

    const link = event.target.closest?.('a[href^="#"]');
    if (!link) return;

    const hash = link.getAttribute('href');
    if (!hash || hash === '#') return;

    const target = document.querySelector(hash);
    if (!target) return;

    event.preventDefault();
    lenis.scrollTo(target);

    // Адрес всё равно должен меняться: ссылку на секцию люди копируют.
    history.replaceState(null, '', hash);
}

/** Inertia меняет страницу без перезагрузки — скролл надо вернуть наверх. */
export function resetScroll() {
    lenis?.scrollTo(0, { immediate: true });
}

export function isSmoothScrollActive() {
    return lenis !== null;
}
