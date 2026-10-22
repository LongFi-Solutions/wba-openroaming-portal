import { Controller } from '@hotwired/stimulus';

/*
 * Replaces the inline <script> that made the landing-page preview read-only,
 * which a nonce-based Content-Security-Policy refuses to execute.
 *
 * Listens on the document in the capture phase, so it runs before any
 * Stimulus action or other handler on the page can react.
 *
 *   <div {{ stimulus_controller('preview-guard') }}>
 */
export default class extends Controller {
    connect() {
        this.blockClick = (event) => {
            if (event.target.closest('a, button, input, select, form')) {
                event.preventDefault();
                event.stopPropagation();
            }
        };
        this.blockSubmit = (event) => {
            event.preventDefault();
            event.stopPropagation();
        };

        document.addEventListener('click', this.blockClick, true);
        document.addEventListener('submit', this.blockSubmit, true);
    }

    disconnect() {
        document.removeEventListener('click', this.blockClick, true);
        document.removeEventListener('submit', this.blockSubmit, true);
    }
}
