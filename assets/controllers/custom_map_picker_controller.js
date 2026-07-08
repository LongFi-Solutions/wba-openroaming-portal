import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['latitude', 'longitude'];

    static values = {
        networkGeometry: { type: String, default: '' },
    };

    connect() {
        this.marker = null;
    }

    _applyColorFilter() {
        if (!this.marker) return;

        const iconElement = this.marker._icon;
        if (iconElement) {
            iconElement.style.filter = 'hue-rotate(140deg) saturate(140%)';
        }
    }

    _onConnect(event) {
        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;

        if (!map || !L) return;

        this.map = map;
        this.L = L;

        if (this.hasNetworkGeometryValue && this.networkGeometryValue) {
            try {
                const geoJson = JSON.parse(this.networkGeometryValue);
                let polygonCoordsList = [];

                if (geoJson.type === 'Polygon') {
                    polygonCoordsList = [geoJson.coordinates];
                } else if (geoJson.type === 'MultiPolygon') {
                    polygonCoordsList = geoJson.coordinates;
                }

                const layers = [];
                polygonCoordsList.forEach((polygonCoords) => {
                    const coordinates = polygonCoords[0]; // outer ring
                    if (coordinates && coordinates.length > 0) {
                        const leafletCoords = coordinates.map((p) => [p[1], p[0]]);
                        const layer = this.L.polygon(leafletCoords, {
                            color: '#2563eb',
                            fillColor: '#3b82f6',
                            fillOpacity: 0.35,
                            weight: 3,
                            interactive: false,
                        }).addTo(this.map);
                        layers.push(layer);
                    }
                });

                if (layers.length > 0) {
                    const group = new this.L.FeatureGroup(layers);
                    this.map.fitBounds(group.getBounds(), { padding: [40, 40] });
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        let latRaw = this.latitudeTarget.value
            ? this.latitudeTarget.value.toString().replace(',', '.')
            : '';
        let lngRaw = this.longitudeTarget.value
            ? this.longitudeTarget.value.toString().replace(',', '.')
            : '';

        const savedLat = parseFloat(latRaw);
        const savedLng = parseFloat(lngRaw);

        if (!isNaN(savedLat) && !isNaN(savedLng) && savedLat !== 0 && savedLng !== 0) {
            this.marker = this.L.marker([savedLat, savedLng]).addTo(this.map);
            this._applyColorFilter();
            this.map.setView([savedLat, savedLng], 17);
        }

        setTimeout(() => {
            this.map.invalidateSize();
        }, 200);

        this.map.on('click', (e) => {
            const latString = e.latlng.lat.toFixed(7);
            const lngString = e.latlng.lng.toFixed(7);

            this.latitudeTarget.value = latString;
            this.longitudeTarget.value = lngString;

            this.latitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));
            this.longitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));

            const markerLatLng = new this.L.LatLng(parseFloat(latString), parseFloat(lngString));
            if (this.marker) {
                this.marker.setLatLng(markerLatLng);
            } else {
                this.marker = this.L.marker(markerLatLng).addTo(this.map);
            }
            this._applyColorFilter();
        });
    }

    syncMap() {
        if (!this.map || !this.L) return;

        let latRaw = this.latitudeTarget.value.toString().replace(',', '.');
        let lngRaw = this.longitudeTarget.value.toString().replace(',', '.');

        const lat = parseFloat(latRaw);
        const lng = parseFloat(lngRaw);

        if (!isNaN(lat) && !isNaN(lng)) {
            const newLatLng = new this.L.LatLng(lat, lng);

            const currentZoom = this.map.getZoom();
            this.map.setView(newLatLng, currentZoom);

            if (this.marker) {
                this.marker.setLatLng(newLatLng);
            } else {
                this.marker = this.L.marker(newLatLng).addTo(this.map);
            }
            this._applyColorFilter();
        }
    }

    clearCoordinates(event) {
        if (event) event.preventDefault();

        if (this.hasLatitudeTarget) {
            this.latitudeTarget.value = '';
            this.latitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (this.hasLongitudeTarget) {
            this.longitudeTarget.value = '';
            this.longitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (this.map && this.marker) {
            this.marker.remove();
            this.marker = null;
        }
    }
}
