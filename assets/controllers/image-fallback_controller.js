import { Controller } from '@hotwired/stimulus';

/*
 * Replaces inline `onerror="this.src='…'"` on an <img>.
 *
 *   <img {{ stimulus_controller('image-fallback', {src: '/resources/images/background.png'}) }}
 *        {{ stimulus_action('image-fallback', 'fallback', 'error') }}>
 */
export default class extends Controller {
    static values = { src: String };

    connect() {
        // The error event may already have fired before Stimulus got here,
        // which the inline handler never had to worry about. A finished image
        // with no intrinsic width is one that failed to load.
        if (this.element.complete && this.element.naturalWidth === 0) {
            this.fallback();
        }
    }

    fallback() {
        // Guard against a loop if the fallback image is itself missing.
        if (this.element.src.endsWith(this.srcValue)) {
            return;
        }

        this.element.src = this.srcValue;
    }
}
