import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'row'];
    static values = {
        prototype: String,
        index: Number,
    };

    addParam(event) {
        event.preventDefault();

        const newRow = this.prototypeValue.replace(/__name__/g, this.indexValue);
        this.listTarget.insertAdjacentHTML('beforeend', newRow);
        this.indexValue++;
    }

    removeParam(event) {
        event.preventDefault();

        const row = event.target.closest('[data-sms-provider-params-target="row"]');
        if (row) {
            row.remove();
        }
    }
}
