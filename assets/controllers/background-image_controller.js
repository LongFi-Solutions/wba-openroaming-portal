import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `style="background-image: url('…')"` on elements whose image
 * comes from a configurable setting.
 *
 * A style attribute in the markup needs style-src-attr 'unsafe-inline'; writing
 * the same declaration through the CSSOM does not, because CSP governs the
 * style attribute and <style> elements, not property assignment.
 *
 *   <div {{ stimulus_controller('background-image', {url: bgUrl}) }}>
 */
export default class extends Controller {
    static values = { url: String };

    // Stimulus calls this on connect as well as on any later change.
    urlValueChanged() {
        if (!this.urlValue) {
            return;
        }

        // Escape what would otherwise terminate the url("…") token.
        const url = this.urlValue.replace(/["\\]/g, '\\$&');

        this.element.style.backgroundImage = `url("${url}")`;
    }
}
