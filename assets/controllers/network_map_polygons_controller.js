import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        polygonsUrl: String,
    };

    connect() {
        this.polygonLayers = [];
        this.debounceTimer = null;

        // ux-map dispatches this on its own wrapper element, nested inside
        // ours — it bubbles, so listening here on the outer element works.
        this.element.addEventListener('ux:map:leaflet:connect', (event) => {
            this.leafletMap = event.detail.map;
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

        let features;
        try {
            const response = await fetch(`${this.polygonsUrlValue}?${params}`);
            if (!response.ok) {
                return;
            }
            features = await response.json();
        } catch {
            return;
        }

        this.polygonLayers.forEach((layer) => this.leafletMap.removeLayer(layer));
        this.polygonLayers = [];

        features.forEach((feature) => {
            const layer = window.L.geoJSON(feature.geometry, {
                style: { color: '#2e7d32', weight: 1, fillOpacity: 0.3 },
            }).bindPopup(feature.name);

            layer.addTo(this.leafletMap);
            this.polygonLayers.push(layer);
        });
    }
}
