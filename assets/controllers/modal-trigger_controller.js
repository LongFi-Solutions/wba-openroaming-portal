import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static values = { modalId: String };

  open() {
    const modalEl = document.getElementById(this.modalIdValue);
    if (!modalEl) return;

    const modalController = this.application.getControllerForElementAndIdentifier(
      modalEl,
      'modal'
    );
    if (modalController) {
      modalController.open();
    }
  }
}
