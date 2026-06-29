import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["geometryJson"];

    static values = {
        tooltip: { type: String, default: 'Click to remove this polygon' }
    };

    connect() {
        this.allPolygons = []; // Format: [[[lng, lat], ...], [[lng, lat], ...]]

        this.currentPoints = [];
        this.currentMarkers = [];
        this.currentPolyline = null;

        this.polygonsLayers = [];
    }

    _onConnect(event) {
        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;
        if (!map) return;

        this.map = map;
        this.L = L;

        this.map.on('click', this.handleMapClick.bind(this));

        setTimeout(() => {
            this.loadExistingPolygons();
        }, 150);
    }

    loadExistingPolygons() {
        if (!this.hasGeometryJsonTarget || !this.geometryJsonTarget.value) {
            return;
        }

        const rawValue = this.geometryJsonTarget.value.trim();
        if (rawValue === "" || rawValue === "[]" || rawValue === "null") {
            return;
        }

        try {
            const geoJson = JSON.parse(rawValue);

            if (geoJson.type === "FeatureCollection" && geoJson.features) {
                geoJson.features.forEach(feature => {
                    if (feature.geometry && feature.geometry.type === "Polygon") {
                        const coordinates = feature.geometry.coordinates[0];
                        if (coordinates && coordinates.length > 0) {
                            const savedPoints = coordinates.slice(0, -1);
                            this.allPolygons.push(savedPoints);

                            const leafletCoords = coordinates.map(p => [p[1], p[0]]);
                            this.drawSavedPolygonLayer(leafletCoords, savedPoints);
                        }
                    }
                });

                this.map.invalidateSize();
                if (this.polygonsLayers.length > 0) {
                    const group = new this.L.FeatureGroup(this.polygonsLayers);
                    this.map.fitBounds(group.getBounds(), { padding: [40, 40], maxZoom: 16 });
                }
            }
        } catch (error) {
            console.error("Error loading existing polygons", error);
        }
    }

    handleMapClick(e) {
        const lat = parseFloat(e.latlng.lat.toFixed(7));
        const lng = parseFloat(e.latlng.lng.toFixed(7));

        this.currentPoints.push([lng, lat]);

        const marker = this.L.circleMarker([lat, lng], {
            radius: 5,
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 1,
            weight: 2
        }).addTo(this.map);

        this.currentMarkers.push(marker);
        this.drawProgressLines();
    }

    drawProgressLines() {
        const leafletCoords = this.currentPoints.map(p => [p[1], p[0]]);

        if (this.currentPolyline) {
            this.currentPolyline.setLatLngs(leafletCoords);
        } else if (leafletCoords.length > 1) {
            this.currentPolyline = this.L.polyline(leafletCoords, {
                color: '#3b82f6',
                dashArray: '5, 10',
                weight: 3
            }).addTo(this.map);
        }
    }

    finishPolygon(e) {
        if (e) e.preventDefault();

        if (this.currentPoints.length < 3) {
            return;
        }

        const closedPoints = [...this.currentPoints, this.currentPoints[0]];
        const leafletCoords = closedPoints.map(p => [p[1], p[0]]);

        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach(m => this.map.removeLayer(m));
        this.currentMarkers = [];
        this.currentPolyline = null;

        const polygonPointsCopy = [...this.currentPoints];
        this.allPolygons.push(polygonPointsCopy);

        this.drawSavedPolygonLayer(leafletCoords, polygonPointsCopy);

        this.currentPoints = [];

        this.updateGeometryJsonValue();
    }

    drawSavedPolygonLayer(leafletCoords, originalPoints) {
        const polygon = this.L.polygon(leafletCoords, {
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.35,
            weight: 3
        }).addTo(this.map);

        polygon.bindTooltip(this.tooltipValue, { sticky: true });

        polygon.on('click', (e) => {
            this.L.DomEvent.stopPropagation(e);

            this.map.removeLayer(polygon);

            this.polygonsLayers = this.polygonsLayers.filter(layer => layer !== polygon);

            this.allPolygons = this.allPolygons.filter(points => points !== originalPoints);

            this.updateGeometryJsonValue();
        });

        this.polygonsLayers.push(polygon);
    }

    updateGeometryJsonValue() {
        const features = this.allPolygons.map(polygonPoints => {
            const closed = [...polygonPoints, polygonPoints[0]];
            return {
                type: "Feature",
                properties: {},
                geometry: {
                    type: "Polygon",
                    coordinates: [closed]
                }
            };
        });

        const geoJsonData = {
            type: "FeatureCollection",
            features: features
        };

        this.geometryJsonTarget.value = features.length > 0 ? JSON.stringify(geoJsonData) : "";

        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    resetPolygon(e) {
        if (e) e.preventDefault();

        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach(m => this.map.removeLayer(m));
        this.currentPoints = [];
        this.currentMarkers = [];
        this.currentPolyline = null;

        this.polygonsLayers.forEach(layer => this.map.removeLayer(layer));
        this.polygonsLayers = [];
        this.allPolygons = [];

        this.geometryJsonTarget.value = "";
        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }
}