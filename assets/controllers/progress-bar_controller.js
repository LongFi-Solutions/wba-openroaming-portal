import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `style="width: calc(…%)"` on the certificate/installation
 * step progress bars. See background-image_controller.js for why the value is
 * applied through the CSSOM rather than a style attribute.
 *
 *   <div {{ stimulus_controller('progress-bar', {percent: 40}) }}>
 */
export default class extends Controller {
    static values = { percent: Number };

    percentValueChanged() {
        this.element.style.width = `${this.percentValue}%`;
    }
}
