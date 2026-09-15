import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `onclick="document.getElementById('x').classList.toggle('hidden')"`.
 *
 *   <button {{ stimulus_controller('toggle-hidden', {id: 'resp-1-2'}) }}
 *           {{ stimulus_action('toggle-hidden', 'toggle') }}>
 */
export default class extends Controller {
    static values = { id: String };

    toggle() {
        document.getElementById(this.idValue)?.classList.toggle('hidden');
    }
}
