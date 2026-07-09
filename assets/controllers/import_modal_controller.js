import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.openButton = document.getElementById('btn-open-import');
        if (this.openButton) {
            this.openHandler = this.open.bind(this);
            this.openButton.addEventListener('click', this.openHandler);
        }

        this.keydownHandler = this.handleKeydown.bind(this);
        document.addEventListener('keydown', this.keydownHandler);
    }

    disconnect() {
        if (this.openButton) {
            this.openButton.removeEventListener('click', this.openHandler);
        }
        document.removeEventListener('keydown', this.keydownHandler);
    }

    open() {
        this.element.classList.remove('hidden');
    }

    close() {
        this.element.classList.add('hidden');
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && !this.element.classList.contains('hidden')) {
            this.close();
        }
    }
}