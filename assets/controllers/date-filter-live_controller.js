import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this._onApplied = this.onApplied.bind(this);
        this._onCleared = this.onCleared.bind(this);
        this.element.addEventListener('date-filter:applied', this._onApplied);
        this.element.addEventListener('date-filter:cleared', this._onCleared);
    }

    disconnect() {
        this.element.removeEventListener('date-filter:applied', this._onApplied);
        this.element.removeEventListener('date-filter:cleared', this._onCleared);
    }

    onApplied(event) {
        const { startDate, endDate } = event.detail;
        console.log('[date-filter-live] applied', startDate, endDate);
        this.#updateLive('startDate', startDate);
        this.#updateLive('endDate', endDate);
    }

    onCleared() {
        console.log('[date-filter-live] cleared');
        this.#updateLive('startDate', '');
        this.#updateLive('endDate', '');
    }

    #updateLive(model, value) {
        const liveEl = this.element.closest('[data-controller~="live"]');
        if (!liveEl) {
            console.warn('[date-filter-live] no live element found');
            return;
        }

        // Find or create a hidden input bound to the live model
        let input = liveEl.querySelector(`input[data-model="${model}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.setAttribute('data-model', model);
            liveEl.appendChild(input);
        }

        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
