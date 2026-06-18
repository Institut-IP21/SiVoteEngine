import Alpine from 'alpinejs';

// Self-hosted brand fonts (bundled by Vite, served from this origin — no external CDN).
// Only the latin + latin-ext subsets (latin-ext covers Slovenian č/š/ž); avoids
// shipping the other subsets (e.g. Poppins devanagari) we never use.
import '@fontsource/karla/latin-400.css';
import '@fontsource/karla/latin-ext-400.css';
import '@fontsource/karla/latin-600.css';
import '@fontsource/karla/latin-ext-600.css';
import '@fontsource/karla/latin-700.css';
import '@fontsource/karla/latin-ext-700.css';
import '@fontsource/poppins/latin-600.css';
import '@fontsource/poppins/latin-ext-600.css';
import '@fontsource/poppins/latin-700.css';
import '@fontsource/poppins/latin-ext-700.css';
import '@fontsource/poppins/latin-800.css';
import '@fontsource/poppins/latin-ext-800.css';

window.Alpine = Alpine;

Alpine.start();

// Load the RankedChoice drag enhancement only on ballots that actually have a ranked
// question — code-split into its own chunk so SortableJS never ships to other ballots.
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-ranked-choice]')) {
        import('./ranked-choice.js');
    }
});
