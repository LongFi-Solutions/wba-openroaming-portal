import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `onclick="return confirm('…')"` / `onsubmit="return confirm('…')"`,
 * which a nonce-based Content-Security-Policy refuses to execute.
 *
 * On a button:
 *   <button {{ stimulus_controller('confirm', {message: '…'}) }}
 *           {{ stimulus_action('confirm', 'ask') }}>
 *
 * On a form, listen for the submit event instead:
 *   <form {{ stimulus_controller('confirm', {message: '…'}) }}
 *         {{ stimulus_action('confirm', 'ask', 'submit') }}>
 */
export default class extends Controller {
    static values = { message: String };

    ask(event) {
        if (!window.confirm(this.messageValue)) {
            // Cancels the submit (or the navigation) exactly like returning
            // false from the old inline handler did.
            event.preventDefault();
            event.stopPropagation();
        }
    }
}
