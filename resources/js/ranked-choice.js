// Drag-to-reorder for RankedChoice ballots — progressive enhancement on top of the
// ↑/↓ buttons (which remain the accessible, keyboard/screen-reader path; WCAG 2.5.7).
// This module is dynamically imported only when a ranked question is on the page
// (see app.js), so SortableJS never loads on ballots that don't need it. Served from
// this origin — no CDN.
import Sortable from 'sortablejs';

const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

function wireId(el) {
    const host = el.closest('[wire\\:id]');
    return host ? host.getAttribute('wire:id') : null;
}

function init(list) {
    if (list._rcSortable) return; // already wired (survives Livewire morphs)
    list._rcSortable = Sortable.create(list, {
        handle: '.rc-grip',
        animation: reduceMotion ? 0 : 150,
        onEnd() {
            const order = Array.from(list.querySelectorAll('[data-name]'))
                .map((node) => node.getAttribute('data-name'));
            const id = wireId(list);
            const wire = id && window.Livewire ? window.Livewire.find(id) : null;
            if (wire) {
                // Livewire re-renders the list in this order; the DOM stays consistent.
                wire.setOrder(order);
            }
        },
    });
}

function scan(root = document) {
    root.querySelectorAll?.('[data-rc-sortable]')?.forEach(init);
}

// --- FLIP: animate button-driven reorders (up / down / move-to-top / remove) ---
// Capture row positions before Livewire morphs the component, then play the inverse
// transform after, so a button press slides rows instead of jumping. Drag already
// animates via SortableJS; this covers the keyboard/button path.
let firstRects = null;

function captureRects(scope) {
    if (reduceMotion || !scope.querySelector?.('[data-ranked-choice]')) return null;
    const rects = new Map();
    scope.querySelectorAll('[data-name]').forEach((el) => {
        rects.set(el.getAttribute('data-name'), el.getBoundingClientRect());
    });
    return rects;
}

function playFlip(scope, first) {
    if (!first) return;
    scope.querySelectorAll('[data-name]').forEach((el) => {
        const prev = first.get(el.getAttribute('data-name'));
        if (!prev) return;
        const now = el.getBoundingClientRect();
        const dy = prev.top - now.top;
        if (dy) {
            el.animate(
                [{ transform: `translateY(${dy}px)` }, { transform: 'none' }],
                { duration: 180, easing: 'ease' },
            );
        }
    });
}

function boot() {
    scan();
    // Re-wire any ranked list that (re)appears after a Livewire DOM morph (e.g. the
    // first time the voter ranks an option the sortable <ul> is rendered).
    window.Livewire.hook('morph', ({ el }) => {
        firstRects = captureRects(el);
    });
    window.Livewire.hook('morphed', ({ el }) => {
        scan(el);
        playFlip(el, firstRects);
        firstRects = null;
    });
}

if (window.Livewire) {
    boot();
} else {
    document.addEventListener('livewire:init', boot);
}
