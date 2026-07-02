import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["geometryJson"];

    static values = {
        tooltip: { type: String, default: 'Click to remove this polygon' }
    };

    connect() {
        this.allPolygons = []; // Formato: [[[lng, lat], ...], [[lng, lat], ...]]
        this.polygonsLayers = [];

        this.drawMode = 'polygon';

        this.currentPoints = [];
        this.currentMarkers = [];
        this.currentPolyline = null;
        this.startMarker = null;

        this.shapeStartPoint = null;
        this.previewShapeLayer = null;
    }

    _onConnect(event) {
        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;
        if (!map) return;

        this.map = map;
        this.L = L;

        this.map.on('click', this.handleMapClick.bind(this));
        this.map.on('mousemove', this.handleMouseMove.bind(this));

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

    setMode(event) {
        this.drawMode = event.params.mode;
        this.cancelCurrentDrawing();

        const buttons = event.currentTarget.parentElement.querySelectorAll('button[data-network-polygon-mode-param]');
        buttons.forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200');
        });
        event.currentTarget.classList.remove('bg-gray-200');
        event.currentTarget.classList.add('bg-blue-600', 'text-white');
    }

    cancelCurrentDrawing() {
        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach(m => this.map.removeLayer(m));
        if (this.previewShapeLayer) this.map.removeLayer(this.previewShapeLayer);

        this.currentPoints = [];
        this.currentMarkers = [];
        this.currentPolyline = null;
        this.startMarker = null;
        this.shapeStartPoint = null;
        this.previewShapeLayer = null;
    }

    handleMouseMove(e) {
        if (this.drawMode === 'polygon' || !this.shapeStartPoint) return;

        if (this.previewShapeLayer) {
            this.map.removeLayer(this.previewShapeLayer);
        }

        const visualStyle = {
            color: '#3b82f6',
            weight: 2,
            dashArray: '5, 10',
            fillOpacity: 0.15,
            fillColor: '#3b82f6'
        };

        if (this.drawMode === 'square') {
            const bounds = [this.shapeStartPoint, e.latlng];
            this.previewShapeLayer = this.L.rectangle(bounds, visualStyle).addTo(this.map);
        } else if (this.drawMode === 'circle') {
            const radius = this.map.distance(this.shapeStartPoint, e.latlng);
            this.previewShapeLayer = this.L.circle(this.shapeStartPoint, { ...visualStyle, radius: radius }).addTo(this.map);
        }
    }

    handleMapClick(e) {
        if (this.drawMode !== 'polygon') {
            if (!this.shapeStartPoint) {
                this.shapeStartPoint = e.latlng;
            } else {
                let polygonPoints = [];

                if (this.drawMode === 'square') {
                    const lat1 = parseFloat(this.shapeStartPoint.lat.toFixed(7));
                    const lng1 = parseFloat(this.shapeStartPoint.lng.toFixed(7));
                    const lat2 = parseFloat(e.latlng.lat.toFixed(7));
                    const lng2 = parseFloat(e.latlng.lng.toFixed(7));

                    polygonPoints = [
                        [lng1, lat1],
                        [lng2, lat1],
                        [lng2, lat2],
                        [lng1, lat2]
                    ];
                } else if (this.drawMode === 'circle') {
                    const radiusMeters = this.map.distance(this.shapeStartPoint, e.latlng);
                    polygonPoints = this.generateCirclePolygon(this.shapeStartPoint, radiusMeters);
                }

                this.cancelCurrentDrawing();

                const leafletCoords = polygonPoints.map(p => [p[1], p[0]]);
                this.allPolygons.push([...polygonPoints]);
                this.drawSavedPolygonLayer(leafletCoords, polygonPoints);
                this.updateGeometryJsonValue();
            }
            return;
        }

        if (this.currentPoints.length >= 3) {
            const firstPoint = this.currentPoints[0];
            const firstLatLng = this.L.latLng(firstPoint[1], firstPoint[0]);

            const clickPixel = this.map.latLngToContainerPoint(e.latlng);
            const startPixel = this.map.latLngToContainerPoint(firstLatLng);
            const distanceInPixels = clickPixel.distanceTo(startPixel);

            if (distanceInPixels < 20) {
                this.finishPolygon();
                return;
            }
        }

        const lat = parseFloat(e.latlng.lat.toFixed(7));
        const lng = parseFloat(e.latlng.lng.toFixed(7));

        this.currentPoints.push([lng, lat]);

        const markerOptions = {
            radius: 5,
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 1,
            weight: 2
        };

        const marker = this.L.circleMarker([lat, lng], markerOptions).addTo(this.map);

        if (this.currentPoints.length === 1) {
            this.startMarker = marker;
            this.startMarker.setStyle({
                radius: 8,
                weight: 4,
                color: '#1d4ed8'
            });

            this.startMarker.on('click', (event) => {
                this.L.DomEvent.stopPropagation(event);
                this.handleStartMarkerClick();
            });
        }

        this.currentMarkers.push(marker);
        this.drawProgressLines();
    }

    handleStartMarkerClick() {
        if (this.currentPoints.length >= 3) {
            this.finishPolygon();
        }
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

    finishPolygon() {
        if (this.currentPoints.length < 3) return;

        const closedPoints = [...this.currentPoints, this.currentPoints[0]];
        const leafletCoords = closedPoints.map(p => [p[1], p[0]]);

        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach(m => this.map.removeLayer(m));

        this.currentMarkers = [];
        this.currentPolyline = null;
        this.startMarker = null;

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

    generateCirclePolygon(centerLatLng, radiusMeters, points = 36) {
        const coords = [];
        const lat = centerLatLng.lat;
        const lng = centerLatLng.lng;

        const radiusLat = radiusMeters / 111320;
        const radiusLng = radiusMeters / (40075000 * Math.cos(lat * Math.PI / 180) / 360);

        for (let i = 0; i < points; i++) {
            const theta = (i / points) * (2 * Math.PI);
            const pLng = lng + (radiusLng * Math.cos(theta));
            const pLat = lat + (radiusLat * Math.sin(theta));
            coords.push([parseFloat(pLng.toFixed(7)), parseFloat(pLat.toFixed(7))]);
        }
        return coords;
    }

    resetPolygon(e) {
        if (e) e.preventDefault();

        this.cancelCurrentDrawing();

        this.polygonsLayers.forEach(layer => this.map.removeLayer(layer));
        this.polygonsLayers = [];
        this.allPolygons = [];

        this.geometryJsonTarget.value = "";
        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }
}