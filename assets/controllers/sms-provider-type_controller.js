import { Controller } from '@hotwired/stimulus';

/*
 * Shows only the [data-provider-type="X"] block matching the currently
 * selected smsProviderType, hiding the rest. Runs on connect (so the right
 * block is visible on page load / after a failed validation re-render) and
 * again whenever the select changes.
 */
export default class extends Controller {
    static targets = ['select'];

    connect() {
        this.toggle();
    }

    toggle() {
        const selected = this.selectTarget.value;

        this.element.querySelectorAll('[data-provider-type]').forEach((block) => {
            block.classList.toggle('hidden', block.dataset.providerType !== selected);
        });
    }
}
