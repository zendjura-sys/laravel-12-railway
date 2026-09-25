/**
 * Общие правила для «тяжёлых» украшений: плавного скролла, курсора и
 * шейдерного фона.
 *
 * Уровень оформления задаётся в админке (Дизайн → Оформлення) и приезжает
 * атрибутом data-effects на <html>. Всё, что тут перечислено, — украшения:
 * ни один из этих слоёв не несёт содержимого, поэтому при «вимкнена», при
 * prefers-reduced-motion и на слабых машинах они просто не запускаются,
 * а сайт остаётся полностью рабочим.
 */

export function effectsLevel() {
    return document.documentElement.dataset.effects || 'full';
}

export function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * Мышь с точным указателем. Именно этим отличается ПК от телефона:
 * кастомный курсор и плавный скролл колёсика там уместны, на тач-экране
 * первого не существует, а второй дерётся с родной инерцией системы.
 */
export function hasFinePointer() {
    return window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false;
}

/**
 * Грубая, но честная оценка слабой машины: на бюджетном Android шейдерный
 * фон греет телефон и жрёт батарею ради эффекта, который там почти не
 * виден. deviceMemory есть не везде, поэтому отсутствие данных трактуем
 * в пользу запуска — иначе на Firefox и Safari эффекты не включались бы
 * вообще.
 */
export function isLowPoweredDevice() {
    const memory = navigator.deviceMemory;
    const cores = navigator.hardwareConcurrency;

    // Порог намеренно низкий: четырёхъядерных настольных машин полно, и
    // отсекать их — значит выключить эффект у половины тех, для кого он и
    // делался. Отсекаем только совсем слабое железо; основной фильтр для
    // тяжёлых слоёв — не число ядер, а наличие мыши (то есть ПК).
    return (memory !== undefined && memory <= 2) || (cores !== undefined && cores <= 2);
}

/** Можно ли вообще запускать украшения этого уровня. */
export function decorationsAllowed({ requiresFinePointer = false, heavy = false } = {}) {
    if (prefersReducedMotion()) return false;

    const level = effectsLevel();
    if (level === 'off') return false;

    // Тяжёлое — только для ПК и только на полной атмосфере: телефон
    // должен остаться таким же быстрым, каким был.
    if (heavy && (level !== 'full' || !hasFinePointer() || isLowPoweredDevice())) return false;
    if (requiresFinePointer && !hasFinePointer()) return false;

    return true;
}

/** Акцентный цвет как [r, g, b] в 0..1 — для передачи в шейдер. */
export function accentRgbUnit() {
    const raw = getComputedStyle(document.documentElement)
        .getPropertyValue('--gold-400')
        .trim();

    const parts = raw.split(/\s+/).map(Number);

    return parts.length === 3 && parts.every((n) => Number.isFinite(n))
        ? parts.map((n) => n / 255)
        : [0.83, 0.69, 0.22];
}
