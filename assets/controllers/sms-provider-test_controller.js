import { Controller } from '@hotwired/stimulus';

/*
 * Reads the currently entered provider-type + credential fields and POSTs
 * them to the test-connection endpoint, without needing anything saved
 * first. Displays the result (a real message from the provider's own API,
 * translated from their error code where possible) inline next to the button.
 */
export default class extends Controller {
    static targets = ['providerType', 'username', 'userid', 'handle', 'from', 'result', 'button'];
    static values = {
        url: String,
        csrfToken: String,
    };

    async test(event) {
        event.preventDefault();

        this.buttonTarget.disabled = true;
        this.resultTarget.textContent = '…';
        this.resultTarget.classList.remove('hidden', 'text-red-500', 'text-lightGreen');

        const payload = new URLSearchParams();
          payload.set('_token', this.csrfTokenValue);
          payload.set('smsProviderType', this.providerTypeTarget.value);
          payload.set('username', this.usernameTarget.value);
          payload.set('userid', this.useridTarget.value);
          payload.set('handle', this.handleTarget.value);
          payload.set('from', this.fromTarget.value);

        try {
          const response = await fetch(this.urlValue, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: payload,
          });

            const data = await response.json();

            this.resultTarget.textContent = data.message;
            this.resultTarget.classList.add(data.success ? 'text-lightGreen' : 'text-red-500');
        } catch (error) {
            this.resultTarget.textContent = 'Could not reach the server to test these credentials.';
            this.resultTarget.classList.add('text-red-500');
        } finally {
            this.buttonTarget.disabled = false;
        }
    }
}
