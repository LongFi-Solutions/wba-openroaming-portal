import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    open() {
        this.element.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(() => {
            this.element.classList.remove('opacity-0');
            this.element.classList.add('opacity-100');
            this.panelTarget.classList.remove('opacity-0', 'scale-95');
            this.panelTarget.classList.add('opacity-100', 'scale-100');
        });
    }

    close() {
        this.element.classList.remove('opacity-100');
        this.element.classList.add('opacity-0');
        this.panelTarget.classList.remove('opacity-100', 'scale-100');
        this.panelTarget.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            this.element.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }, 200);
    }

    stopPropagation(event) {
        event.stopPropagation();
    }
}
