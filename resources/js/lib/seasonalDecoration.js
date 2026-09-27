import { decorationsAllowed } from './motion';

/**
 * Сезонні прикраси — легкий canvas-шар поверх сайту (сніжинки, пелюстки,
 * конфеті). Вмикається/вимикається вручну адміном (Дизайн → Оформлення),
 * без автоматики за датою.
 *
 * Той самий підхід, що й aurora-шейдер: 2D canvas замість DOM-вузлів на
 * кожну частинку — інакше сотня елементів з transition била б по layout
 * при кожному кадрі. Підпорядковується тим самим правилам, що й решта
 * decorations (effects=off, prefers-reduced-motion, /admin).
 */

const THEMES = {
    new_year: { kind: 'fall', colors: ['#ffffff', '#dceeff'], shape: 'flake' },
    christmas: { kind: 'fall', colors: ['#ffffff', '#f2f8ff'], shape: 'flake' },
    easter: { kind: 'fall', colors: ['#ffd9ec', '#ffffff', '#fff2c9'], shape: 'petal' },
    birthday: { kind: 'confetti', colors: ['#d4af37', '#f2d98a', '#ff8fb1', '#8fd1ff', '#ffffff'], shape: 'confetti' },
};

const MAX_DPR = 1.5;
const PARTICLE_COUNT = 42;

let teardown = null;

function makeParticle(theme, width, height, spawnAbove = false) {
    const color = theme.colors[Math.floor(Math.random() * theme.colors.length)];

    return {
        x: Math.random() * width,
        y: spawnAbove ? -20 - Math.random() * height : Math.random() * height,
        size: theme.shape === 'confetti' ? 4 + Math.random() * 4 : 3 + Math.random() * 4,
        speed: theme.kind === 'confetti' ? 0.6 + Math.random() * 1.2 : 0.35 + Math.random() * 0.65,
        drift: (Math.random() - 0.5) * 0.6,
        sway: Math.random() * Math.PI * 2,
        swaySpeed: 0.01 + Math.random() * 0.02,
        rotation: Math.random() * Math.PI * 2,
        rotationSpeed: (Math.random() - 0.5) * 0.05,
        color,
    };
}

function draw(ctx, p, shape) {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rotation);
    ctx.fillStyle = p.color;

    if (shape === 'flake') {
        ctx.globalAlpha = 0.85;
        ctx.beginPath();
        ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2);
        ctx.fill();
    } else if (shape === 'petal') {
        ctx.globalAlpha = 0.8;
        ctx.beginPath();
        ctx.ellipse(0, 0, p.size, p.size / 1.8, 0, 0, Math.PI * 2);
        ctx.fill();
    } else {
        ctx.globalAlpha = 0.9;
        ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
    }

    ctx.restore();
}

export function startSeasonalDecoration(themeKey) {
    const theme = THEMES[themeKey];

    if (teardown || !theme || !decorationsAllowed()) {
        return false;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'seasonal-decoration';
    canvas.setAttribute('aria-hidden', 'true');
    const ctx = canvas.getContext('2d');
    if (!ctx) return false;

    document.body.append(canvas);

    let width = 0;
    let height = 0;

    function resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    resize();

    const particles = Array.from({ length: PARTICLE_COUNT }, () => makeParticle(theme, width, height));

    let frame = null;

    function step() {
        ctx.clearRect(0, 0, width, height);

        for (const p of particles) {
            p.sway += p.swaySpeed;
            p.x += p.drift + Math.sin(p.sway) * 0.4;
            p.y += p.speed;
            p.rotation += p.rotationSpeed;

            if (p.y - p.size > height) {
                Object.assign(p, makeParticle(theme, width, height));
                p.y = -10;
            }
            if (p.x < -10) p.x = width + 10;
            if (p.x > width + 10) p.x = -10;

            draw(ctx, p, theme.shape);
        }

        frame = requestAnimationFrame(step);
    }

    frame = requestAnimationFrame(step);

    function onVisibility() {
        if (document.hidden) {
            cancelAnimationFrame(frame);
        } else {
            frame = requestAnimationFrame(step);
        }
    }

    window.addEventListener('resize', resize, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);

    teardown = () => {
        cancelAnimationFrame(frame);
        window.removeEventListener('resize', resize);
        document.removeEventListener('visibilitychange', onVisibility);
        canvas.remove();
        teardown = null;
    };

    return true;
}

export function stopSeasonalDecoration() {
    teardown?.();
}
