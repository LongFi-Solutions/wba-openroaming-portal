import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        polygonsUrl: String,
        showAccessPoints: { type: Boolean, default: false },
        markerIcon: { type: String, default: '' },
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

        const drawSinglePolygon = (rings) => {
            this.L.polygon(rings, {
                weight: 2,
                dashArray: '6, 8',
                color: '#7c3aed',
                fillColor: '#8b5cf6',
                fillOpacity: 0.18,
                fillRule: 'nonzero',
            })
                .addTo(this.layerGroup)
                .bindPopup(network.name);
        };

        if (geometry.type === 'Polygon') {
            const rings = geometry.coordinates.map(toLatLngRing);
            drawSinglePolygon(rings);
        } else if (geometry.type === 'MultiPolygon') {
            geometry.coordinates.forEach((polygonRings) => {
                const rings = polygonRings.map(toLatLngRing);
                drawSinglePolygon(rings);
            });
        }
    }

    drawAccessPoint(ap) {
        if (ap.lat === null || ap.lng === null) return;
        this.L.marker([ap.lat, ap.lng], { icon: this._getIcon() })
            .addTo(this.layerGroup)
            .bindPopup(ap.name);
    }

    _getIcon() {
        if (this._icon) return this._icon;
        this._icon = this.L.divIcon({
            html: this.hasMarkerIconValue ? this.markerIconValue : '',
            className: 'custom-pin-icon coverage-network-marker',
            iconSize: [33, 40],
            iconAnchor: [16, 28],
            popupAnchor: [0, -24],
        });
        return this._icon;
    }
}