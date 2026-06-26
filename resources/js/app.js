// Toasty: spójne powiadomienia w prawym górnym rogu. Renderowane z flashy
// serwera (x-toasts) i opcjonalnie z JS przez window.showToast(). ~30 s, pasek
// odliczający, zamykane krzyżykiem. Zero zależności.

const TOAST_VARIANTS = {
    success: { border: 'border-emerald-300', text: 'text-emerald-800', bar: 'bg-emerald-400' },
    error: { border: 'border-rose-300', text: 'text-rose-700', bar: 'bg-rose-400' },
    info: { border: 'border-amber-300', text: 'text-amber-800', bar: 'bg-amber-400' },
};

const TOAST_DURATION = 30000;

function dismissToast(el) {
    el.style.opacity = '0';
    el.style.transform = 'translateX(0.5rem)';
    setTimeout(() => el.remove(), 250);
}

function initToast(el) {
    if (el.hasAttribute('data-toast-ready')) {
        return;
    }
    el.setAttribute('data-toast-ready', '');

    // wejście
    el.style.transition = 'opacity .25s ease, transform .25s ease';
    el.style.opacity = '0';
    el.style.transform = 'translateX(0.5rem)';
    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateX(0)';
    });

    const duration = parseInt(el.dataset.toastDuration || String(TOAST_DURATION), 10);

    // pasek odliczający: od pełnej szerokości do zera w `duration`
    const bar = el.querySelector('[data-toast-bar]');
    if (bar) {
        requestAnimationFrame(() => {
            bar.style.transition = `width ${duration}ms linear`;
            bar.style.width = '0%';
        });
    }

    const timer = setTimeout(() => dismissToast(el), duration);

    const close = el.querySelector('[data-toast-close]');
    if (close) {
        close.addEventListener('click', () => {
            clearTimeout(timer);
            dismissToast(el);
        });
    }
}

function toastRegion() {
    let region = document.getElementById('toast-region');
    if (!region) {
        region = document.createElement('div');
        region.id = 'toast-region';
        region.className = 'pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[90vw] flex-col gap-3';
        region.setAttribute('aria-live', 'polite');
        document.body.appendChild(region);
    }
    return region;
}

// Programowe wywołanie toasta (np. po akcji AJAX/Livewire).
window.showToast = function (message, type = 'info', duration = TOAST_DURATION) {
    const v = TOAST_VARIANTS[type] || TOAST_VARIANTS.info;

    const el = document.createElement('div');
    el.setAttribute('data-toast', '');
    el.dataset.toastDuration = String(duration);
    el.className = `pointer-events-auto overflow-hidden rounded-2xl border bg-white shadow-lg shadow-stone-900/10 ${v.border}`;
    el.innerHTML = `
        <div class="flex items-start gap-3 px-4 py-3">
            <p class="flex-1 text-sm ${v.text}"></p>
            <button type="button" data-toast-close aria-label="Zamknij"
                class="-mr-1 -mt-0.5 shrink-0 rounded-md p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-600">✕</button>
        </div>
        <div class="h-1 w-full bg-stone-100">
            <div data-toast-bar class="h-full w-full ${v.bar}"></div>
        </div>`;
    el.querySelector('p').textContent = message;

    toastRegion().appendChild(el);
    initToast(el);
};

function initToasts(root = document) {
    root.querySelectorAll('[data-toast]').forEach(initToast);
}

document.addEventListener('DOMContentLoaded', () => initToasts());
