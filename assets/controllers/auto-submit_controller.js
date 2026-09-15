import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `onchange="this.form.submit()"` on a <select>.
 *
 *   <select {{ stimulus_controller('auto-submit') }}
 *           {{ stimulus_action('auto-submit', 'submit', 'change') }}>
 */
export default class extends Controller {
    submit() {
        this.element.form?.submit();
    }
}
