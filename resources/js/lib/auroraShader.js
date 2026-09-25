import { accentRgbUnit, decorationsAllowed } from './motion';

/**
 * Живой фон на WebGL: медленно текущие световые полосы в акцентном цвете —
 * тот самый эффект «жидкого стекла и преломления света».
 *
 * Шейдер написан руками, БЕЗ Three.js. Three.js — это ~600 КБ ради сцены,
 * камеры и материалов, которых здесь нет: мы рисуем один прямоугольник во
 * весь экран. Вся разница между «дорогим» и обычным фоном живёт во
 * фрагментном шейдере, а он весит пару килобайт.
 *
 * Слой чисто декоративный: если WebGL недоступен, включён режим
 * «стримана»/«вимкнена», стоит prefers-reduced-motion или машина слабая —
 * ничего не запускается, и остаётся CSS-аврора, которая была раньше.
 */

const VERTEX = `
attribute vec2 position;
void main() { gl_Position = vec4(position, 0.0, 1.0); }
`;

const FRAGMENT = `
precision mediump float;

uniform vec2  u_resolution;
uniform float u_time;
uniform vec3  u_accent;

// Классический value-noise: дешевле градиентного и для мягких пятен
// света разницы не видно.
float hash(vec2 p) {
    return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453123);
}

float noise(vec2 p) {
    vec2 i = floor(p);
    vec2 f = fract(p);
    vec2 u = f * f * (3.0 - 2.0 * f);
    return mix(
        mix(hash(i + vec2(0.0, 0.0)), hash(i + vec2(1.0, 0.0)), u.x),
        mix(hash(i + vec2(0.0, 1.0)), hash(i + vec2(1.0, 1.0)), u.x),
        u.y
    );
}

// Четырёх октав хватает: пятая уже уходит в шум размером с пиксель,
// который на тёмном фоне не читается, но стоит кадров.
float fbm(vec2 p) {
    float value = 0.0;
    float amplitude = 0.5;
    for (int i = 0; i < 4; i++) {
        value += amplitude * noise(p);
        p *= 2.02;
        amplitude *= 0.5;
    }
    return value;
}

void main() {
    vec2 uv = gl_FragCoord.xy / u_resolution.xy;
    vec2 p = uv * vec2(u_resolution.x / u_resolution.y, 1.0);

    float t = u_time * 0.035;

    // Домен-варпинг: складки текут сами по себе, а не ползут по прямой —
    // именно из-за этого движение читается как жидкость, а не как слайд.
    vec2 q = vec2(fbm(p + vec2(0.0, t)), fbm(p + vec2(5.2, -t * 0.8)));
    vec2 r = vec2(fbm(p + 3.0 * q + vec2(1.7, 9.2) + t * 0.6),
                  fbm(p + 3.0 * q + vec2(8.3, 2.8) - t * 0.4));
    float f = fbm(p + 2.5 * r);

    // Узкие яркие полосы вместо сплошной заливки: так это выглядит
    // преломлением света, а не цветным туманом.
    float bands = smoothstep(0.42, 0.78, f);
    float glow = pow(bands, 2.2);

    vec3 cold = vec3(0.16, 0.20, 0.45);
    vec3 color = mix(cold * 0.55, u_accent, glow);
    color *= glow * 0.85;

    // Виньетка собирает свет к центру и гасит края, чтобы фон не спорил
    // с текстом поверх него.
    float vignette = smoothstep(1.25, 0.25, length(uv - 0.5) * 1.6);
    color *= vignette;

    gl_FragColor = vec4(color, glow * vignette * 0.55);
}
`;

// 30 кадров в секунду: фон течёт медленно, разницы с 60 не видно, а
// нагрев ноутбука и расход батареи вдвое меньше.
const FRAME_MS = 1000 / 30;
const MAX_DPR = 1.5;

let teardown = null;

function compile(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);

    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        gl.deleteShader(shader);

        return null;
    }

    return shader;
}

export function startAuroraShader() {
    if (teardown || !decorationsAllowed({ heavy: true })) {
        return false;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'aurora-shader';
    canvas.setAttribute('aria-hidden', 'true');

    const gl = canvas.getContext('webgl', {
        alpha: true,
        antialias: false,
        depth: false,
        stencil: false,
        // Фон не должен будить дискретную видеокарту на ноутбуке ради
        // светового пятна.
        powerPreference: 'low-power',
    });

    if (!gl) {
        return false;
    }

    const vs = compile(gl, gl.VERTEX_SHADER, VERTEX);
    const fs = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT);
    if (!vs || !fs) {
        return false;
    }

    const program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        return false;
    }

    gl.useProgram(program);

    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);

    const positionLocation = gl.getAttribLocation(program, 'position');
    gl.enableVertexAttribArray(positionLocation);
    gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

    const uResolution = gl.getUniformLocation(program, 'u_resolution');
    const uTime = gl.getUniformLocation(program, 'u_time');
    const uAccent = gl.getUniformLocation(program, 'u_accent');

    gl.uniform3fv(uAccent, accentRgbUnit());
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

    document.body.prepend(canvas);

    function resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);
        const width = Math.floor(window.innerWidth * dpr);
        const height = Math.floor(window.innerHeight * dpr);

        if (canvas.width === width && canvas.height === height) {
            return;
        }

        canvas.width = width;
        canvas.height = height;
        gl.viewport(0, 0, width, height);
        gl.uniform2f(uResolution, width, height);
    }

    resize();

    let frame = null;
    let lastDraw = 0;
    const started = performance.now();

    function render(now) {
        frame = requestAnimationFrame(render);

        if (now - lastDraw < FRAME_MS) {
            return;
        }
        lastDraw = now;

        gl.uniform1f(uTime, (now - started) / 1000);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
    }

    frame = requestAnimationFrame(render);

    // Во вкладке, которую не смотрят, рисовать незачем: браузер и сам
    // душит rAF, но не везде и не сразу.
    function onVisibility() {
        if (document.hidden) {
            cancelAnimationFrame(frame);
        } else {
            lastDraw = 0;
            frame = requestAnimationFrame(render);
        }
    }

    window.addEventListener('resize', resize, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);

    teardown = () => {
        cancelAnimationFrame(frame);
        window.removeEventListener('resize', resize);
        document.removeEventListener('visibilitychange', onVisibility);
        gl.getExtension('WEBGL_lose_context')?.loseContext();
        canvas.remove();
        teardown = null;
    };

    return true;
}

export function stopAuroraShader() {
    teardown?.();
}
