import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        polygonsUrl: String,
        showAccessPoints: { type: Boolean, default: false },
    };

    connect() {
        this.element.addEventListener('ux:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
        if (this.map && this.moveEndHandler) {
            this.map.off('moveend', this.moveEndHandler);
        }
    }

    onMapConnect = (event) => {
        this.map = event.detail.map;
        this.L = event.detail.L;
        this.layerGroup = this.L.layerGroup().addTo(this.map);

        this.moveEndHandler = () => this.fetchAndRender();
        this.map.on('moveend', this.moveEndHandler);

        this.fetchAndRender();
    };

    async fetchAndRender() {
        const bounds = this.map.getBounds();
        const params = new URLSearchParams({
            minLat: bounds.getSouth(),
            minLng: bounds.getWest(),
            maxLat: bounds.getNorth(),
            maxLng: bounds.getEast(),
        });

        let data;
        try {
            const response = await fetch(`${this.polygonsUrlValue}?${params}`);
            if (!response.ok) {
                return;
            }
            data = await response.json();
        } catch {
            return;
        }

        this.layerGroup.clearLayers();

        (data.networks ?? []).forEach((network) => this.drawNetwork(network));

        if (this.showAccessPointsValue) {
            (data.accessPoints ?? []).forEach((ap) => this.drawAccessPoint(ap));
        }
    }

    drawNetwork(network) {
        const geometry = network.geometry;
        if (!geometry) {
            return;
        }

        const toLatLngRing = (ring) => ring.map(([lng, lat]) => [lat, lng]);

        let latlngs;
        if (geometry.type === 'Polygon') {
            latlngs = geometry.coordinates.map(toLatLngRing);
        } else if (geometry.type === 'MultiPolygon') {
            latlngs = geometry.coordinates.map((polygonRings) => polygonRings.map(toLatLngRing));
        } else {
            return;
        }

        const polygon = this.L.polygon(latlngs, {
            color: '#8AB742',
            weight: 2,
            dashArray: '6, 8',
            fillColor: '#8AB742',
            fillOpacity: 0.18,
        })
          .addTo(this.layerGroup)
          .bindPopup(network.name);
    }

    drawAccessPoint(ap) {
        if (ap.lat === null || ap.lng === null) {
            return;
        }

        this.L.marker([ap.lat, ap.lng])
          .addTo(this.layerGroup)
          .bindPopup(ap.name);
    }
}
