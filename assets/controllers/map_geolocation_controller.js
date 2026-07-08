import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';

export default class extends Controller {
    static values = {
        mapData: Array,
    };

    connect() {
        console.log('map-geolocation connected', this.mapDataValue);

        this.element.addEventListener('ux:map:connect', this.onMapConnect);

        const url = new URL(window.location.href);

        if (!url.searchParams.has('lat') || !url.searchParams.has('lng')) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((position) => {
                    url.searchParams.set('lat', position.coords.latitude);
                    url.searchParams.set('lng', position.coords.longitude);

                    window.location.replace(url.toString());
                });
            }
        }
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
    }

    onMapConnect = (event) => {
        console.log('ux map connected', event.detail);
        console.log('drawing mapData', this.mapDataValue);

        const leafletMap = event.detail.map;

        this.mapDataValue.forEach((network) => {
            if (network.geometry?.type === 'Polygon') {
                const rings = network.geometry.coordinates.map((ring) =>
                    ring.map(([lng, lat]) => [lat, lng])
                );

                L.polygon(rings, {
                    color: '#16a34a',
                    weight: 3,
                    fillColor: '#22c55e',
                    fillOpacity: 0.18,
                })
                    .addTo(leafletMap)
                    .bindPopup(network.name);
            }

            network.accessPoints.forEach((ap) => {
                if (ap.lat === null || ap.lng === null) {
                    return;
                }

                L.marker([ap.lat, ap.lng]).addTo(leafletMap).bindPopup(ap.name);
            });
        });
    };
}
