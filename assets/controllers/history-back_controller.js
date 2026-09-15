import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `onclick="window.history.back();"`.
 *
 *   <button {{ stimulus_controller('history-back') }}
 *           {{ stimulus_action('history-back', 'back') }}>
 */
export default class extends Controller {
    back(event) {
        event.preventDefault();
        window.history.back();
    }
}
