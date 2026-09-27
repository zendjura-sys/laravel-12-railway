// v-glow — скло підсвічується туди, куди дивиться курсор (радіальний градієнт
// у ::before, позиція якого рухається за --glow-x/--glow-y). Обробник на елементі,
// не на document, тому не займає час на сторінках без жодної скляної панелі.

function onMove(e) {
    const rect = this.getBoundingClientRect();
    this.style.setProperty('--glow-x', `${e.clientX - rect.left}px`);
    this.style.setProperty('--glow-y', `${e.clientY - rect.top}px`);
}

export const glow = {
    mounted(el) {
        el.classList.add('glow-tracked');
        el.addEventListener('mousemove', onMove);
    },
    unmounted(el) {
        el.removeEventListener('mousemove', onMove);
    },
};
