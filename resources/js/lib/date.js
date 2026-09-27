/**
 * Eloquent-каст `date`/`datetime` серіалізується у повний ISO-рядок
 * ("2026-09-19T00:00:00.000000Z"), а <input type="date"> приймає лише
 * "YYYY-MM-DD" — з будь-яким іншим форматом браузер мовчки показує поле
 * порожнім. Виглядає так, ніби значення "не зберіглося", хоча воно
 * коректно лежить у базі.
 */
export function toDateInputValue(value) {
    if (!value) return '';

    return String(value).slice(0, 10);
}
