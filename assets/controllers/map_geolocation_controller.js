import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const url = new URL(window.location.href);

        if (url.searchParams.has('lat') && url.searchParams.has('lng')) {
            return;
        }

        if (!navigator.geolocation) {
            return;
        }

        navigator.geolocation.getCurrentPosition((position) => {
            url.searchParams.set('lat', position.coords.latitude);
            url.searchParams.set('lng', position.coords.longitude);

            window.location.replace(url.toString());
        });
    }
}
