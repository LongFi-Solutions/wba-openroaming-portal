import { Controller } from '@hotwired/stimulus';

const ICON_SIZE = [33, 40];
const ICON_ANCHOR = [16, 28]; // matches this icon's actual pin-tip position, not the viewBox bottom
const POPUP_ANCHOR = [0, -24];

export default class extends Controller {
    static targets = ['latitude', 'longitude'];

    static values = {
        networkGeometry: { type: String, default: '' },
        markerIcon: { type: String, default: '' },
        otherMarkerIcon: { type: String, default: '' },
        otherAccessPoints: { type: Array, default: [] },
    };

    connect() {
        this.marker = null;
        this._divIcon = null; // built lazily once Leaflet (this.L) is available, then cached
        this._boundHandleMapClick = this._handleMapClick.bind(this);
    }

    disconnect() {
        this.map?.off('click', this._boundHandleMapClick);
    }

    _onConnect(event) {
        const map = event.detail.map;
        const L = event.detail.L || window.L;

        if (!map || !L) return;

        this.map = map;
        this.L = L;

        this._renderNetworkGeometry();
        this._renderOtherAccessPoints();
        this._renderInitialMarker();

        setTimeout(() => this.map.invalidateSize(), 200);

        // Defensive: avoids stacking a second click handler if _onConnect ever fires twice.
        this.map.off('click', this._boundHandleMapClick);
        this.map.on('click', this._boundHandleMapClick);
    }

    syncMap() {
        if (!this.map || !this.L) return;

        const coords = this._readCoordsFromInputs();
        if (!coords) return;

        this.map.setView(coords, this.map.getZoom());
        this._placeMarker(coords);
    }

    clearCoordinates(event) {
        event?.preventDefault();

        for (const target of [this.latitudeTarget, this.longitudeTarget]) {
            if (target) {
                target.value = '';
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        this.marker?.remove();
        this.marker = null;
    }

    // --- internal helpers -------------------------------------------------

    _handleMapClick(e) {
        const { lat, lng } = e.latlng;
        const latString = lat.toFixed(7);
        const lngString = lng.toFixed(7);

        this.latitudeTarget.value = latString;
        this.longitudeTarget.value = lngString;
        this.latitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.longitudeTarget.dispatchEvent(new Event('change', { bubbles: true }));

        this._placeMarker(new this.L.LatLng(parseFloat(latString), parseFloat(lngString)));
    }

    _renderNetworkGeometry() {
        if (!this.hasNetworkGeometryValue || !this.networkGeometryValue) return;

        let geoJson;
        try {
            geoJson = JSON.parse(this.networkGeometryValue);
        } catch (error) {
            console.error('Invalid network geometry JSON:', error);
            return;
        }

        const polygonCoordsList =
          geoJson.type === 'Polygon' ? [geoJson.coordinates]
            : geoJson.type === 'MultiPolygon' ? geoJson.coordinates
              : [];

        const layers = polygonCoordsList
          .map((polygonCoords) => polygonCoords[0]) // outer ring only
          .filter((ring) => ring?.length > 0)
          .map((ring) => {
              const leafletCoords = ring.map(([lng, lat]) => [lat, lng]);
              return this.L.polygon(leafletCoords, {
                  color: '#2563eb',
                  fillColor: '#3b82f6',
                  fillOpacity: 0.35,
                  weight: 3,
                  interactive: false,
              }).addTo(this.map);
          });

        if (layers.length > 0) {
            const group = this.L.featureGroup(layers);
            this.map.fitBounds(group.getBounds(), { padding: [40, 40] });
        }
    }

    // Shows every OTHER access point already placed on this network, as a
    // muted, non-draggable marker with a popup — so the user can see which
    // spots are already taken and avoid placing a duplicate on top of one.
    _renderOtherAccessPoints() {
        if (!this.hasOtherAccessPointsValue) return;

        const icon = this._getOtherIcon();

        this.otherAccessPointsValue.forEach((ap) => {
            if (ap.lat == null || ap.lng == null) return;

            this.L.marker([ap.lat, ap.lng], { icon })
              .bindPopup(ap.name || 'Access Point')
              .addTo(this.map);
        });
    }

    _renderInitialMarker() {
        const coords = this._readCoordsFromInputs();
        if (!coords) return;

        this._placeMarker(coords);
        this.map.setView(coords, 17);
    }

    _readCoordsFromInputs() {
        const lat = this._parseCoord(this.latitudeTarget.value);
        const lng = this._parseCoord(this.longitudeTarget.value);

        if (lat === null || lng === null || (lat === 0 && lng === 0)) return null;

        return new this.L.LatLng(lat, lng);
    }

    _parseCoord(rawValue) {
        if (!rawValue) return null;
        const value = parseFloat(rawValue.toString().replace(',', '.'));
        return Number.isFinite(value) ? value : null;
    }

    _placeMarker(latlng) {
        if (this.marker) {
            this.marker.setLatLng(latlng);
        } else {
            this.marker = this.L.marker(latlng, { icon: this._getIcon() }).addTo(this.map);
        }
    }

    // Lazily built (needs this.L to exist) and cached — the glyph never
    // changes mid-session, so there's no reason to rebuild the divIcon per click.
    _getIcon() {
        if (this._divIcon) return this._divIcon;

        this._divIcon = this.L.divIcon({
            html: this.hasMarkerIconValue ? this.markerIconValue : '',
            className: 'custom-pin-icon coverage-network-marker',
            iconSize: ICON_SIZE,
            iconAnchor: ICON_ANCHOR,
            popupAnchor: POPUP_ANCHOR,
        });

        return this._divIcon;
    }

    _getOtherIcon() {
        if (this._otherDivIcon) return this._otherDivIcon;

        this._otherDivIcon = this.L.divIcon({
            html: this.hasOtherMarkerIconValue ? this.otherMarkerIconValue : '',
            className: 'custom-pin-icon-other coverage-network-marker',
            iconSize: [26, 26],
            iconAnchor: [13, 13],
            popupAnchor: [0, -16],
        });

        return this._otherDivIcon;
    }
}
