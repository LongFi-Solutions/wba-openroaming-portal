import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];

    connect() {
        console.log('[modal] connected', this.element);
    }

    open(event) {
        console.log('[modal] open() fired', event.type, event.target);
        event.stopPropagation();
        this.containerTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close(event) {
        console.log('[modal] close() fired');
        if (event) event.stopPropagation();
        this.containerTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    backdropClose(event) {
        if (event.target === this.containerTarget) {
            this.close(event);
        }
    }
}
