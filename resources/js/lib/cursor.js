import { decorationsAllowed } from './motion';

/**
 * Кастомный курсор: точка, которая идёт точно за мышью, и кольцо, которое
 * её догоняет. На интерактивном кольцо расширяется и подсвечивается.
 *
 * Только для точного указателя: на тач-экране курсора не существует
 * вовсе, и рисовать его там — значит гонять rAF ради невидимого слоя.
 *
 * Родной курсор НЕ убираем совсем: он остаётся на полях ввода и
 * прокручиваемых областях, где форма курсора несёт смысл (текстовая
 * каретка), — подменять его точкой значит отнимать у человека подсказку.
 */

const INTERACTIVE = 'a, button, [role="button"], input, textarea, select, label, summary, [data-cursor]';

let frame = null;
let teardown = null;

export function startCursor() {
    if (teardown || !decorationsAllowed({ requiresFinePointer: true })) {
        return;
    }

    const dot = document.createElement('div');
    dot.className = 'cursor-dot';
    const ring = document.createElement('div');
    ring.className = 'cursor-ring';
    document.body.append(dot, ring);

    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let ringX = mouseX;
    let ringY = mouseY;
    let visible = false;

    function onMove(event) {
        mouseX = event.clientX;
        mouseY = event.clientY;

        if (!visible) {
            visible = true;
            document.documentElement.classList.add('has-custom-cursor');
        }

        // Точка — без задержки: она заменяет собой указатель, и любое
        // отставание читается как подтормаживание всего сайта.
        dot.style.transform = `translate3d(${mouseX}px, ${mouseY}px, 0)`;

        const interactive = event.target.closest?.(INTERACTIVE);
        ring.classList.toggle('is-active', Boolean(interactive));
        dot.classList.toggle('is-active', Boolean(interactive));
    }

    function onLeave() {
        visible = false;
        document.documentElement.classList.remove('has-custom-cursor');
    }

    function onDown() { ring.classList.add('is-pressed'); }
    function onUp() { ring.classList.remove('is-pressed'); }

    function render() {
        // Кольцо догоняет точку — из этого отставания и складывается
        // ощущение веса, ради которого всё затевалось.
        ringX += (mouseX - ringX) * 0.16;
        ringY += (mouseY - ringY) * 0.16;
        ring.style.transform = `translate3d(${ringX}px, ${ringY}px, 0)`;
        frame = requestAnimationFrame(render);
    }

    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('mouseout', onLeave, { passive: true });
    window.addEventListener('mousedown', onDown, { passive: true });
    window.addEventListener('mouseup', onUp, { passive: true });
    frame = requestAnimationFrame(render);

    teardown = () => {
        cancelAnimationFrame(frame);
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseout', onLeave);
        window.removeEventListener('mousedown', onDown);
        window.removeEventListener('mouseup', onUp);
        dot.remove();
        ring.remove();
        document.documentElement.classList.remove('has-custom-cursor');
        teardown = null;
    };
}

export function stopCursor() {
    teardown?.();
}
