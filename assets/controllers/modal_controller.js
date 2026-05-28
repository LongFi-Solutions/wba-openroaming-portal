import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];

    open() {
        this.containerTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close() {
        this.containerTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    backdropClose(event) {
        if (event.target === this.containerTarget) {
          this.close();
        }
    }
}
