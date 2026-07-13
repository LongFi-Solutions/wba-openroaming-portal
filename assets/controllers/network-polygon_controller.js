import { Controller } from '@hotwired/stimulus';

// Extract visual configurations outside the class for easy maintenance
const STYLES = {
    preview: {
        color: '#3b82f6',
        weight: 2,
        dashArray: '5, 10',
        fillOpacity: 0.15,
        fillColor: '#3b82f6',
    },
    shape: { color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 0.35, weight: 3 },
    marker: { radius: 5, color: '#2563eb', fillColor: '#3b82f6', fillOpacity: 1, weight: 2 },
    startMarker: { radius: 8, weight: 4, color: '#1d4ed8' },
    progressLine: { color: '#3b82f6', dashArray: '5, 10', weight: 3 },
};

const EARTH_RADIUS = 6378137; // WGS84 mean radius in meters

export default class extends Controller {
    static targets = ['geometryJson', 'coverageList', 'emptyState'];
    static values = {
        emptyLabel: { type: String, default: 'No coverage areas yet.' },
        typeLabels: { type: Object, default: {} },
        areaItemLabel: { type: String, default: 'Area' },
    };

    connect() {
        this.shapes = [];
        this.nextShapeId = 1;
        this.drawMode = 'polygon';

        this.resetDrawingState();
    }

    disconnect() {
        // Crucial: Prevent memory leaks and zombie events on Turbo navigation
        if (this.map) {
            this.map.off('click', this.handleMapClick);
            this.map.off('mousemove', this.handleMouseMove);
            if (this.drawnItems) this.drawnItems.clearLayers();
        }
    }

    _onConnect(event) {
        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;
        if (!map || !L) return;

        this.map = map;
        this.L = L;

        // Use a FeatureGroup to easily manage, bounds-check, and clear shapes
        this.drawnItems = new this.L.FeatureGroup().addTo(this.map);
        this.previewItems = new this.L.FeatureGroup().addTo(this.map);

        this.map.on('click', this.handleMapClick);
        this.map.on('mousemove', this.handleMouseMove);

        setTimeout(() => {
            this.loadExistingPolygons();
            this.renderCoverageList();
        }, 150);
    }

    // Use arrow functions for callbacks to preserve 'this' context natively
    handleMapClick = (e) => {
        if (this.drawMode !== 'polygon') {
            this.handleFixedShapeClick(e);
            return;
        }

        this.handlePolygonClick(e);
    };

    handleMouseMove = (e) => {
        if (this.drawMode === 'polygon' || !this.shapeStartPoint) return;

        this.previewItems.clearLayers();

        if (this.drawMode === 'square') {
            const bounds = [this.shapeStartPoint, e.latlng];
            this.L.rectangle(bounds, STYLES.preview).addTo(this.previewItems);
        } else if (this.drawMode === 'circle') {
            const radius = this.map.distance(this.shapeStartPoint, e.latlng);
            this.L.circle(this.shapeStartPoint, { ...STYLES.preview, radius }).addTo(
                this.previewItems
            );
        }
    };

    handleFixedShapeClick(e) {
        if (!this.shapeStartPoint) {
            this.shapeStartPoint = e.latlng;
            return;
        }

        let polygonPoints = [];
        let extra = {};

        if (this.drawMode === 'square') {
            const lat1 = this.truncateCoord(this.shapeStartPoint.lat);
            const lng1 = this.truncateCoord(this.shapeStartPoint.lng);
            const lat2 = this.truncateCoord(e.latlng.lat);
            const lng2 = this.truncateCoord(e.latlng.lng);

            polygonPoints = [
                [lng1, lat1],
                [lng2, lat1],
                [lng2, lat2],
                [lng1, lat2],
            ];
        } else if (this.drawMode === 'circle') {
            const radiusMeters = this.map.distance(this.shapeStartPoint, e.latlng);
            polygonPoints = this.generateCirclePolygon(this.shapeStartPoint, radiusMeters);
            extra = { radius: radiusMeters };
        }

        this.cancelCurrentDrawing();

        const leafletCoords = polygonPoints.map((p) => [p[1], p[0]]);
        this.addShape(this.drawMode, polygonPoints, leafletCoords, extra);
        this.syncState();
    }

    handlePolygonClick(e) {
        if (this.currentPoints.length >= 3) {
            const firstPoint = this.currentPoints[0];
            const firstLatLng = this.L.latLng(firstPoint[1], firstPoint[0]);

            const distanceInPixels = this.map
                .latLngToContainerPoint(e.latlng)
                .distanceTo(this.map.latLngToContainerPoint(firstLatLng));

            if (distanceInPixels < 20) {
                this.finishPolygon();
                return;
            }
        }

        const lat = this.truncateCoord(e.latlng.lat);
        const lng = this.truncateCoord(e.latlng.lng);
        this.currentPoints.push([lng, lat]);

        const marker = this.L.circleMarker([lat, lng], STYLES.marker).addTo(this.previewItems);

        if (this.currentPoints.length === 1) {
            this.startMarker = marker;
            this.startMarker.setStyle(STYLES.startMarker);
            this.startMarker.on('click', (event) => {
                this.L.DomEvent.stopPropagation(event);
                if (this.currentPoints.length >= 3) this.finishPolygon();
            });
        }

        this.currentMarkers.push(marker);
        this.drawProgressLines();
    }

    drawProgressLines() {
        const leafletCoords = this.currentPoints.map((p) => [p[1], p[0]]);

        if (this.currentPolyline) {
            this.currentPolyline.setLatLngs(leafletCoords);
        } else if (leafletCoords.length > 1) {
            this.currentPolyline = this.L.polyline(leafletCoords, STYLES.progressLine).addTo(
                this.previewItems
            );
        }
    }

    finishPolygon() {
        if (this.currentPoints.length < 3) return;

        const closedPoints = [...this.currentPoints, this.currentPoints[0]];
        const leafletCoords = closedPoints.map((p) => [p[1], p[0]]);
        const polygonPointsCopy = [...this.currentPoints];

        this.cancelCurrentDrawing();
        this.addShape('polygon', polygonPointsCopy, leafletCoords);
        this.syncState();
    }

    // --- Shape Bookkeeping -------------------------------------------------

    addShape(type, points, leafletCoords, extra = {}) {
        const areaM2 = extra.radius
            ? Math.PI * extra.radius * extra.radius
            : this.geodesicArea(leafletCoords);

        const shapeLayer = this.L.polygon(leafletCoords, STYLES.shape);
        const labelMarker = this.createShapeLabel(shapeLayer.getBounds().getCenter());

        // Group the layer and marker into a layer group for easy removal
        const compositeLayer = this.L.layerGroup([shapeLayer, labelMarker]).addTo(this.drawnItems);

        const shape = {
            id: this.nextShapeId++,
            type,
            points,
            areaM2,
            layer: compositeLayer,
            labelMarker,
            ...extra,
        };

        this.shapes.push(shape);
        this.renumberShapeLabels();
        return shape;
    }

    createShapeLabel(centerLatLng) {
        const icon = this.L.divIcon({
            className: 'coverage-network-marker network-polygon-shape-label',
            html: '<span></span>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });
        return this.L.marker(centerLatLng, { icon, interactive: false });
    }

    renumberShapeLabels() {
        this.shapes.forEach((shape, index) => {
            const span = shape.labelMarker?.getElement()?.querySelector('span');
            if (span) span.textContent = index + 1;
        });
    }

    removeShape(event) {
        const id = parseInt(event.params.shapeId, 10);
        const shapeIndex = this.shapes.findIndex((s) => s.id === id);

        if (shapeIndex === -1) return;

        // Remove from map via FeatureGroup, then remove from state
        this.drawnItems.removeLayer(this.shapes[shapeIndex].layer);
        this.shapes.splice(shapeIndex, 1);

        this.renumberShapeLabels();
        this.syncState();
    }

    resetDrawingState() {
        this.currentPoints = [];
        this.currentMarkers = [];
        this.currentPolyline = null;
        this.startMarker = null;
        this.shapeStartPoint = null;
    }

    cancelCurrentDrawing() {
        if (this.previewItems) this.previewItems.clearLayers();
        this.resetDrawingState();
    }

    resetPolygon(e) {
        if (e) e.preventDefault();

        this.cancelCurrentDrawing();
        this.drawnItems.clearLayers(); // Clears all shapes in one go
        this.shapes = [];

        this.syncState();
    }

    syncState() {
        this.updateGeometryJsonValue();
        this.renderCoverageList();
    }

    // --- Mode & GeoJSON Handling --------------------------------------------

    setMode(event) {
        this.drawMode = event.params.mode;
        this.cancelCurrentDrawing();

        const activeClasses = ['border-blue-500', 'bg-blue-50', 'text-blue-600'];
        const inactiveClasses = ['bg-white', 'border-gray-200', 'text-gray-500'];

        const buttons = event.currentTarget.parentElement.querySelectorAll(
            'button[data-network-polygon-mode-param]'
        );

        buttons.forEach((btn) => {
            btn.classList.remove(...activeClasses);
            btn.classList.add(...inactiveClasses);
        });

        event.currentTarget.classList.remove(...inactiveClasses);
        event.currentTarget.classList.add(...activeClasses);
    }

    updateGeometryJsonValue() {
        if (this.shapes.length === 0) {
            this.geometryJsonTarget.value = '';
        } else {
            const closedRings = this.shapes.map((shape) => [[...shape.points, shape.points[0]]]);

            const geometry =
                closedRings.length === 1
                    ? { type: 'Polygon', coordinates: closedRings[0] }
                    : { type: 'MultiPolygon', coordinates: closedRings };

            this.geometryJsonTarget.value = JSON.stringify(geometry);
        }

        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    loadExistingPolygons() {
        if (!this.hasGeometryJsonTarget || !this.geometryJsonTarget.value) return;

        const rawValue = this.geometryJsonTarget.value.trim();
        if (['', '[]', 'null'].includes(rawValue)) return;

        try {
            const geoJson = JSON.parse(rawValue);
            const polygonCoordsList =
                geoJson.type === 'MultiPolygon' ? geoJson.coordinates : [geoJson.coordinates];

            polygonCoordsList.forEach((polygonCoords) => {
                const coordinates = polygonCoords[0];
                if (coordinates && coordinates.length > 0) {
                    const savedPoints = coordinates.slice(0, -1);
                    const leafletCoords = coordinates.map((p) => [p[1], p[0]]);
                    this.addShape('area', savedPoints, leafletCoords);
                }
            });

            this.map.invalidateSize();
            if (this.shapes.length > 0) {
                this.map.fitBounds(this.drawnItems.getBounds(), { padding: [40, 40], maxZoom: 16 });
            }
        } catch (error) {
            console.error('Error loading existing polygons', error);
        }
    }

    // --- UI Rendering ------------------------------------------------------

    renderCoverageList() {
        if (!this.hasCoverageListTarget) return;

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('hidden', this.shapes.length > 0);
        }

        if (this.shapes.length === 0) {
            this.coverageListTarget.innerHTML = '';
            return;
        }

        this.coverageListTarget.innerHTML = this.shapes
            .map((shape, index) => {
                const typeLabel =
                    this.typeLabelsValue[shape.type] || this.typeLabelsValue.area || shape.type;
                return `
                <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white rounded-lg border border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700">${this.areaItemLabelValue} ${index + 1}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-600">${typeLabel}</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-500">${this.formatArea(shape.areaM2)}</span>
                        <button type="button" 
                                class="text-red-400 hover:text-red-600"
                                data-action="click->network-polygon#removeShape"
                                data-network-polygon-shape-id-param="${shape.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m2 0v12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V7h12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            })
            .join('');
    }

    // --- Math Utilities -----------------------------------------------------

    truncateCoord(coord) {
        return parseFloat(coord.toFixed(7));
    }

    formatArea(m2) {
        return m2 >= 1000000
            ? `${(m2 / 1000000).toFixed(2)} km\u00b2`
            : `${Math.round(m2).toLocaleString()} m\u00b2`;
    }

    geodesicArea(latlngs) {
        let area = 0;
        const len = latlngs.length;

        if (len > 2) {
            for (let i = 0; i < len; i++) {
                const p1 = latlngs[i];
                const p2 = latlngs[(i + 1) % len];
                area +=
                    (((p2[1] - p1[1]) * Math.PI) / 180) *
                    (2 + Math.sin((p1[0] * Math.PI) / 180) + Math.sin((p2[0] * Math.PI) / 180));
            }
            area = (area * EARTH_RADIUS * EARTH_RADIUS) / 2;
        }

        return Math.abs(area);
    }

    generateCirclePolygon(centerLatLng, radiusMeters, points = 36) {
        const coords = [];
        const { lat, lng } = centerLatLng;

        const radiusLat = radiusMeters / 111320;
        const radiusLng = radiusMeters / ((40075000 * Math.cos((lat * Math.PI) / 180)) / 360);

        for (let i = 0; i < points; i++) {
            const theta = (i / points) * (2 * Math.PI);
            const pLng = lng + radiusLng * Math.cos(theta);
            const pLat = lat + radiusLat * Math.sin(theta);
            coords.push([this.truncateCoord(pLng), this.truncateCoord(pLat)]);
        }
        return coords;
    }
}
