// v-reveal — плавное появление элемента при входе во вьюпорт через IntersectionObserver.
// Модификатор задаётся значением атрибута data-reveal (scale/left/right), стили — в app.css.
// Один общий observer на все элементы, чтобы не плодить сотни инстансов на длинной странице.

let observer;

function getObserver() {
    if (observer) return observer;

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -8% 0px' },
    );

    return observer;
}

export const reveal = {
    mounted(el, binding) {
        el.setAttribute('data-reveal', binding.value || 'up');
        if (binding.arg) {
            el.style.transitionDelay = `${binding.arg}ms`;
        }
        getObserver().observe(el);
    },
    unmounted(el) {
        observer?.unobserve(el);
    },
};
