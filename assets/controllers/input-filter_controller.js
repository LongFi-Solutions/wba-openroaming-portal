import { Controller } from '@hotwired/stimulus';

/*
 * Replaces the inline `oninput` handlers that trimmed a field to its maxlength
 * and, on the verification code inputs, stripped everything but digits.
 * <input type="number"> ignores the maxlength attribute on its own, hence this.
 *
 *   <input {{ stimulus_controller('input-filter') }}
 *          {{ stimulus_action('input-filter', 'enforce', 'input') }} maxlength="6">
 *
 * Add the digitsOnly value to also drop non-numeric characters.
 */
export default class extends Controller {
    static values = { digitsOnly: Boolean };

    enforce() {
        let value = this.element.value;

        if (this.digitsOnlyValue) {
            value = value.replace(/[^0-9]/g, '');
        }

        const max = this.element.maxLength;

        if (max > 0 && value.length > max) {
            value = value.slice(0, max);
        }

        // Avoid a pointless write, which would move the caret to the end.
        if (value !== this.element.value) {
            this.element.value = value;
        }
    }
}
