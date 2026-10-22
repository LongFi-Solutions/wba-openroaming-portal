import { Controller } from '@hotwired/stimulus';

/*
 * Replaces the inline onclick on the landing-page customization "Preview"
 * button, which a nonce-based Content-Security-Policy refuses to execute.
 *
 * Submits the surrounding form to the preview URL in a new tab, then puts the
 * form's own action and target back so "Save changes" still posts normally.
 *
 *   <button type="button"
 *           {{ stimulus_controller('form-preview', {url: path('…')}) }}
 *           {{ stimulus_action('form-preview', 'open', 'click') }}>
 */
export default class extends Controller {
    static values = { url: String };

    open(event) {
        event.preventDefault();

        const form = this.element.closest('form');
        if (!form) {
            return;
        }

        const originalAction = form.getAttribute('action');
        const originalTarget = form.getAttribute('target');

        form.setAttribute('action', this.urlValue);
        form.setAttribute('target', '_blank');

        // Bypasses submit listeners (such as the confirm controller), as the
        // original inline handler did.
        HTMLFormElement.prototype.submit.call(form);

        if (originalAction !== null) {
            form.setAttribute('action', originalAction);
        } else {
            form.removeAttribute('action');
        }

        if (originalTarget !== null) {
            form.setAttribute('target', originalTarget);
        } else {
            form.removeAttribute('target');
        }
    }
}
