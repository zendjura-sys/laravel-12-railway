// v-magnetic — кнопка слегка тянется к курсору и мягко возвращается, когда
// он уходит. Именно «слегка»: смещение ограничено 8px, иначе элемент начинает
// убегать от клика и раздражает вместо того, чтобы выглядеть дорого.
//
// Только для устройств с настоящим курсором: на тач-экране hover залипает, и
// кнопка осталась бы смещённой после тапа.

const MAX_SHIFT = 8;

function onMove(e) {
    const rect = this.getBoundingClientRect();
    const dx = (e.clientX - (rect.left + rect.width / 2)) / (rect.width / 2);
    const dy = (e.clientY - (rect.top + rect.height / 2)) / (rect.height / 2);

    this.style.transform = `translate3d(${(dx * MAX_SHIFT).toFixed(2)}px, ${(dy * MAX_SHIFT).toFixed(2)}px, 0)`;
}

function onLeave() {
    this.style.transform = '';
}

export const magnetic = {
    mounted(el) {
        if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        // Возврат мягче, чем следование: уход курсора должен читаться как
        // «отпустило», а не как рывок обратно.
        el.style.transition = 'transform 0.7s cubic-bezier(0.16, 1, 0.3, 1)';
        el.addEventListener('mousemove', onMove);
        el.addEventListener('mouseleave', onLeave);
    },
    unmounted(el) {
        el.removeEventListener('mousemove', onMove);
        el.removeEventListener('mouseleave', onLeave);
    },
};
