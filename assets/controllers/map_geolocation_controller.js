import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';

export default class extends Controller {
    static values = {
        mapData: Array,
    };

    connect() {
        this.element.addEventListener('ux:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
    }

    onMapConnect = (event) => {
        const leafletMap = event.detail.map;

        // Converts a GeoJSON ring [[lng, lat], ...] into Leaflet's [lat, lng] order
        const toLatLngRing = (ring) => ring.map(([lng, lat]) => [lat, lng]);

        this.mapDataValue.forEach((network) => {
            const geometry = network.geometry;

            if (geometry?.type === 'Polygon') {
                // coordinates: Ring[]  (first ring = outer boundary, rest = holes)
                const latlngs = geometry.coordinates.map(toLatLngRing);

                L.polygon(latlngs, {
                    color: '#16a34a',
                    weight: 3,
                    fillColor: '#22c55e',
                    fillOpacity: 0.18,
                })
                    .addTo(leafletMap)
                    .bindPopup(network.name);
            } else if (geometry?.type === 'MultiPolygon') {
                // coordinates: Polygon[]  where each Polygon is Ring[]
                const latlngs = geometry.coordinates.map((polygonRings) =>
                    polygonRings.map(toLatLngRing)
                );

                L.polygon(latlngs, {
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
