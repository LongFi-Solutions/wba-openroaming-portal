import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['geometryJson', 'coverageList', 'emptyState'];

    static values = {
        tooltip: { type: String, default: 'Click to remove this polygon' },
        emptyLabel: { type: String, default: 'No coverage areas yet.' },
    };

    connect() {
        // Each shape: { id, type: 'polygon'|'square'|'circle'|'area', points, areaM2, layer, radius? }
        this.shapes = [];
        this.nextShapeId = 1;

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
            this.renderCoverageList();
        }, 150);
    }

    loadExistingPolygons() {
        if (!this.hasGeometryJsonTarget || !this.geometryJsonTarget.value) {
            return;
        }

        const rawValue = this.geometryJsonTarget.value.trim();
        if (rawValue === '' || rawValue === '[]' || rawValue === 'null') {
            return;
        }

        try {
            const geoJson = JSON.parse(rawValue);
            let polygonCoordsList = [];

            if (geoJson.type === 'Polygon') {
                polygonCoordsList = [geoJson.coordinates];
            } else if (geoJson.type === 'MultiPolygon') {
                polygonCoordsList = geoJson.coordinates;
            }

            polygonCoordsList.forEach((polygonCoords) => {
                const coordinates = polygonCoords[0]; // outer ring
                if (coordinates && coordinates.length > 0) {
                    const savedPoints = coordinates.slice(0, -1);
                    const leafletCoords = coordinates.map((p) => [p[1], p[0]]);

                    // GeoJSON alone doesn't tell us whether this was originally drawn
                    // as a polygon, rectangle, or circle - it's labelled generically
                    // as "Area". Persist the shape type server-side if you want the
                    // original label to survive a reload.
                    this.addShape('area', savedPoints, leafletCoords);
                }
            });

            this.map.invalidateSize();
            if (this.shapes.length > 0) {
                const group = new this.L.FeatureGroup(this.shapes.map((s) => s.layer));
                this.map.fitBounds(group.getBounds(), { padding: [40, 40], maxZoom: 16 });
            }
        } catch (error) {
            console.error('Error loading existing polygons', error);
        }
    }

    setMode(event) {
        this.drawMode = event.params.mode;
        this.cancelCurrentDrawing();

        const buttons = event.currentTarget.parentElement.querySelectorAll(
          'button[data-network-polygon-mode-param]'
        );
        buttons.forEach((btn) => {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200');
        });
        event.currentTarget.classList.remove('bg-gray-200');
        event.currentTarget.classList.add('bg-blue-600', 'text-white');
    }

    cancelCurrentDrawing() {
        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach((m) => this.map.removeLayer(m));
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
            fillColor: '#3b82f6',
        };

        if (this.drawMode === 'square') {
            const bounds = [this.shapeStartPoint, e.latlng];
            this.previewShapeLayer = this.L.rectangle(bounds, visualStyle).addTo(this.map);
        } else if (this.drawMode === 'circle') {
            const radius = this.map.distance(this.shapeStartPoint, e.latlng);
            this.previewShapeLayer = this.L.circle(this.shapeStartPoint, {
                ...visualStyle,
                radius: radius,
            }).addTo(this.map);
        }
    }

    handleMapClick(e) {
        if (this.drawMode !== 'polygon') {
            if (!this.shapeStartPoint) {
                this.shapeStartPoint = e.latlng;
            } else {
                let polygonPoints = [];
                let extra = {};

                if (this.drawMode === 'square') {
                    const lat1 = parseFloat(this.shapeStartPoint.lat.toFixed(7));
                    const lng1 = parseFloat(this.shapeStartPoint.lng.toFixed(7));
                    const lat2 = parseFloat(e.latlng.lat.toFixed(7));
                    const lng2 = parseFloat(e.latlng.lng.toFixed(7));

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
                this.updateGeometryJsonValue();
                this.renderCoverageList();
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
            weight: 2,
        };

        const marker = this.L.circleMarker([lat, lng], markerOptions).addTo(this.map);

        if (this.currentPoints.length === 1) {
            this.startMarker = marker;
            this.startMarker.setStyle({
                radius: 8,
                weight: 4,
                color: '#1d4ed8',
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
        const leafletCoords = this.currentPoints.map((p) => [p[1], p[0]]);

        if (this.currentPolyline) {
            this.currentPolyline.setLatLngs(leafletCoords);
        } else if (leafletCoords.length > 1) {
            this.currentPolyline = this.L.polyline(leafletCoords, {
                color: '#3b82f6',
                dashArray: '5, 10',
                weight: 3,
            }).addTo(this.map);
        }
    }

    finishPolygon() {
        if (this.currentPoints.length < 3) return;

        const closedPoints = [...this.currentPoints, this.currentPoints[0]];
        const leafletCoords = closedPoints.map((p) => [p[1], p[0]]);

        if (this.currentPolyline) this.map.removeLayer(this.currentPolyline);
        this.currentMarkers.forEach((m) => this.map.removeLayer(m));

        this.currentMarkers = [];
        this.currentPolyline = null;
        this.startMarker = null;

        const polygonPointsCopy = [...this.currentPoints];
        this.currentPoints = [];

        this.addShape('polygon', polygonPointsCopy, leafletCoords);
        this.updateGeometryJsonValue();
        this.renderCoverageList();
    }

    // --- shape bookkeeping -------------------------------------------------

    addShape(type, points, leafletCoords, extra = {}) {
        const shape = {
            id: this.nextShapeId++,
            type,
            points,
            ...extra,
        };

        shape.areaM2 = extra.radius
          ? Math.PI * extra.radius * extra.radius
          : this.geodesicArea(leafletCoords);

        shape.layer = this.drawShapeLayer(leafletCoords, shape);

        this.shapes.push(shape);
        return shape;
    }

    drawShapeLayer(leafletCoords, shape) {
        const polygon = this.L.polygon(leafletCoords, {
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.35,
            weight: 3,
        }).addTo(this.map);

        polygon.bindTooltip(this.tooltipValue, { sticky: true });

        polygon.on('click', (e) => {
            this.L.DomEvent.stopPropagation(e);
            this.removeShapeById(shape.id);
        });

        return polygon;
    }

    // Triggered by the trash icon on a Coverage Overview row
    removeShape(event) {
        const id = parseInt(event.params.shapeId, 10);
        this.removeShapeById(id);
    }

    removeShapeById(id) {
        const shape = this.shapes.find((s) => s.id === id);
        if (!shape) return;

        this.map.removeLayer(shape.layer);
        this.shapes = this.shapes.filter((s) => s.id !== id);

        this.updateGeometryJsonValue();
        this.renderCoverageList();
    }

    resetPolygon(e) {
        if (e) e.preventDefault();

        this.cancelCurrentDrawing();

        this.shapes.forEach((shape) => this.map.removeLayer(shape.layer));
        this.shapes = [];

        this.geometryJsonTarget.value = '';
        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.renderCoverageList();
    }

    updateGeometryJsonValue() {
        if (this.shapes.length === 0) {
            this.geometryJsonTarget.value = '';
            this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        const closedRings = this.shapes.map((shape) => {
            const closed = [...shape.points, shape.points[0]];
            return [closed]; // GeoJSON Polygon coordinates = array of rings
        });

        const geometry =
          closedRings.length === 1
            ? { type: 'Polygon', coordinates: closedRings[0] }
            : { type: 'MultiPolygon', coordinates: closedRings };

        this.geometryJsonTarget.value = JSON.stringify(geometry);
        this.geometryJsonTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // --- Coverage Overview list ---------------------------------------------

    renderCoverageList() {
        if (!this.hasCoverageListTarget) return;

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('hidden', this.shapes.length > 0);
        }

        if (this.shapes.length === 0) {
            this.coverageListTarget.className =
              'bg-gray-50 rounded-lg border border-dashed border-gray-200 p-6 text-center text-sm text-gray-400';
            this.coverageListTarget.innerHTML = this.emptyLabelValue;
            return;
        }

        const typeLabels = { polygon: 'Polygon', square: 'Rectangle', circle: 'Circle', area: 'Area' };

        const rows = this.shapes
          .map((shape, index) => {
              const typeLabel = typeLabels[shape.type] || 'Area';
              return `
                    <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white rounded-lg border border-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-gray-700">Area ${index + 1}</span>
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

        this.coverageListTarget.className = 'space-y-2';
        this.coverageListTarget.innerHTML = rows;
    }

    formatArea(m2) {
        if (m2 >= 1000000) {
            return `${(m2 / 1000000).toFixed(2)} km\u00b2`;
        }
        return `${Math.round(m2).toLocaleString()} m\u00b2`;
    }

    // Shoelace-on-a-sphere approximation (same formula Leaflet.GeometryUtil uses).
    // latlngs: array of [lat, lng]
    geodesicArea(latlngs) {
        const R = 6378137; // WGS84 mean radius, meters
        let area = 0;
        const len = latlngs.length;

        if (len > 2) {
            for (let i = 0; i < len; i++) {
                const p1 = latlngs[i];
                const p2 = latlngs[(i + 1) % len];
                area +=
                  ((p2[1] - p1[1]) * Math.PI) / 180 *
                  (2 + Math.sin((p1[0] * Math.PI) / 180) + Math.sin((p2[0] * Math.PI) / 180));
            }
            area = (area * R * R) / 2;
        }

        return Math.abs(area);
    }

    generateCirclePolygon(centerLatLng, radiusMeters, points = 36) {
        const coords = [];
        const lat = centerLatLng.lat;
        const lng = centerLatLng.lng;

        const radiusLat = radiusMeters / 111320;
        const radiusLng = radiusMeters / ((40075000 * Math.cos((lat * Math.PI) / 180)) / 360);

        for (let i = 0; i < points; i++) {
            const theta = (i / points) * (2 * Math.PI);
            const pLng = lng + radiusLng * Math.cos(theta);
            const pLat = lat + radiusLat * Math.sin(theta);
            coords.push([parseFloat(pLng.toFixed(7)), parseFloat(pLat.toFixed(7))]);
        }
        return coords;
    }
}
