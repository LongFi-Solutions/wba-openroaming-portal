import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // ADD 'phoneCountry' AND 'phoneNumber' TO TARGETS
    static targets = [
        'providerType',
        'username',
        'userid',
        'handle',
        'from',
        'phoneCountry',
        'phoneNumber',
        'result',
        'button'
    ];

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

        // SEND 'country' AND 'number' EXPECTED BY YOUR PHP CONTROLLER
        payload.set('country', this.phoneCountryTarget.value);
        payload.set('number', this.phoneNumberTarget.value);

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: payload,
            });

            const data = await response.json();

            this.resultTarget.textContent = data.message;
            this.resultTarget.classList.add(data.success ? 'text-lightGreen' : 'text-red-500');
        } finally {
            this.buttonTarget.disabled = false;
        }
    }
}
