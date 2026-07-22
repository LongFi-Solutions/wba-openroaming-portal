import { Controller } from '@hotwired/stimulus';

/*
 * The visible radio-cards are purely a UI proxy — they carry no `name` of
 * their own. On change, this syncs the clicked radio's value into the real
 * (hidden) Symfony-bound select, then shows only the [data-provider-type]
 * block matching that value.
 */
export default class extends Controller {
    static targets = ['hiddenSelect'];

    connect() {
        this.applyVisibility();
    }

    toggle(event) {
        this.hiddenSelectTarget.value = event.target.value;
        this.applyVisibility();
    }

    applyVisibility() {
        const selected = this.hiddenSelectTarget.value;

        this.element.querySelectorAll('[data-provider-type]').forEach((block) => {
            block.classList.toggle('hidden', block.dataset.providerType !== selected);
        });
    }
}
