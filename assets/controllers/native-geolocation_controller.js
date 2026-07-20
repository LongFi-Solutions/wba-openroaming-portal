import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        zoom: { type: Number, default: 13 },
    };

    connect() {
        this.element.addEventListener('ux:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
    }

    onMapConnect = (event) => {
        this.map = event.detail.map;
        this.locateUser();
    };

    locateUser() {
        if (!('geolocation' in navigator)) {
            console.warn(
                '[NATIVE GPS] Geolocation not supported by this browser (or missing HTTPS).'
            );
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                this.map.flyTo([lat, lng], this.zoomValue, {
                    animate: true,
                    duration: 1.5,
                });
            },
            (error) => {
                console.info(
                    '[NATIVE GPS] User ignored, denied permission, or an error occurred:',
                    error.message
                );
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0,
            }
        );
    }
}
