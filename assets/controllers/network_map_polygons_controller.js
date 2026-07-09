import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        polygonsUrl: String,
        showAccessPoints: { type: Boolean, default: false },
    };

    connect() {
        this.layers = [];
        this.debounceTimer = null;

        this.element.addEventListener('ux:map:connect', (event) => {
            this.leafletMap = event.detail.map;
            this.L = event.detail.L;
            this.leafletMap.on('moveend', () => this.debouncedLoad());
            this.loadPolygons();
        });
    }

    disconnect() {
        clearTimeout(this.debounceTimer);
    }

    debouncedLoad() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.loadPolygons(), 300);
    }

    async loadPolygons() {
        const bounds = this.leafletMap.getBounds();
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

        this.layers.forEach((layer) => this.leafletMap.removeLayer(layer));
        this.layers = [];

        const networks = this.showAccessPointsValue ? data.networks : data;

        networks.forEach((feature) => {
            const layer = this.L.geoJSON(feature.geometry, {
                style: { color: '#8AB742', weight: 1, fillColor: '#8AB742', fillOpacity: 0.3 },
            }).bindPopup(feature.name);
            layer.addTo(this.leafletMap);
            this.layers.push(layer);
        });

        if (this.showAccessPointsValue) {
            data.accessPoints.forEach((ap) => {
                const marker = this.L.marker([ap.lat, ap.lng]).bindPopup(ap.ssid ?? ap.name);
                marker.addTo(this.leafletMap);
                this.layers.push(marker);
            });
        }
    }
}
