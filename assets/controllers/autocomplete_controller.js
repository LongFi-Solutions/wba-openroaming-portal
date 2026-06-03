import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['input', 'suggestions'];

  connect() {
    // Close on click outside
    this.clickOutside = this.clickOutside.bind(this);
    document.addEventListener('click', this.clickOutside);
  }

  disconnect() {
    document.removeEventListener('click', this.clickOutside);
  }

  clickOutside(event) {
    if (!this.element.contains(event.target)) {
      this.close();
    }
  }

  selectSuggestion() {
    this.close();
  }

  keydown(event) {
    if (event.key === 'Enter' || event.key === 'Escape') {
      this.close();
    }
  }

  close() {
    if (this.hasSuggestionsTarget) {
      this.suggestionsTarget.classList.add('hidden');
    }
  }

  open() {
    if (this.hasSuggestionsTarget) {
      this.suggestionsTarget.classList.remove('hidden');
    }
  }
}
