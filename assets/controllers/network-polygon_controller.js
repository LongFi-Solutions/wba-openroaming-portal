import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["geometryJson"];

    connect() {
        this.points = [];
        this.markers = [];
        this.polyline = null;
        this.polygon = null;
    }

    _onConnect(event) {
        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;
        if (!map) return;

        this.map = map;
        this.L = L;

        this.map.on('click', this.handleMapClick.bind(this));

        setTimeout(() => {
            this.loadExistingPolygon();
        }, 150);
    }

    loadExistingPolygon() {
        if (!this.hasGeometryJsonTarget || !this.geometryJsonTarget.value) {
            return;
        }

        const rawValue = this.geometryJsonTarget.value.trim();
        if (rawValue === "" || rawValue === "[]" || rawValue === "null") {
            return;
        }

        try {
            const geoJson = JSON.parse(rawValue);
            let coordinates = null;

            if (geoJson.type === "FeatureCollection" && geoJson.features && geoJson.features[0]) {
                coordinates = geoJson.features[0].geometry.coordinates[0];
            } else if (geoJson.type === "Polygon" && geoJson.coordinates) {
                coordinates = geoJson.coordinates[0];
            }

            if (!coordinates || coordinates.length === 0) return;

            this.points = coordinates.slice(0, -1);

            const leafletCoords = coordinates.map(p => [p[1], p[0]]);

            if (this.polygon) this.map.removeLayer(this.polygon);

            this.polygon = this.L.polygon(leafletCoords, {
                color: '#2563eb',
                fillColor: '#3b82f6',
                fillOpacity: 0.35,
                weight: 3
            }).addTo(this.map);

            this.map.invalidateSize();
            this.map.fitBounds(this.polygon.getBounds(), { padding: [40, 40], maxZoom: 16 });

            console.log("Polígono existente carregado na edição com sucesso!");

        } catch (error) {
            console.error("Erro ao ler a geometria na edição:", error);
        }
    }

    handleMapClick(e) {
        if (this.polygon) {
            this.resetPolygon();
        }

        const lat = parseFloat(e.latlng.lat.toFixed(7));
        const lng = parseFloat(e.latlng.lng.toFixed(7));

        this.points.push([lng, lat]);

        const marker = this.L.circleMarker([lat, lng], {
            radius: 5,
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 1,
            weight: 2
        }).addTo(this.map);

        this.markers.push(marker);
        this.drawProgressLines();
    }

    drawProgressLines() {
        const leafletCoords = this.points.map(p => [p[1], p[0]]);

        if (this.polyline) {
            this.polyline.setLatLngs(leafletCoords);
        } else if (leafletCoords.length > 1) {
            this.polyline = this.L.polyline(leafletCoords, {
                color: '#3b82f6',
                dashArray: '5, 10',
                weight: 3
            }).addTo(this.map);
        }
    }

    finishPolygon(e) {
        if (e) e.preventDefault();

        if (this.points.length < 3) {
            alert("Precisa de marcar pelo menos 3 pontos no mapa para delimitar uma área!");
            return;
        }

        const closedPoints = [...this.points, this.points[0]];
        const leafletCoords = closedPoints.map(p => [p[1], p[0]]);

        if (this.polyline) this.map.removeLayer(this.polyline);
        this.markers.forEach(m => this.map.removeLayer(m));

        this.markers = [];
        this.polyline = null;

        this.polygon = this.L.polygon(leafletCoords, {
            color: '#2563eb',
            fillColor: '#3b82f6',
            fillOpacity: 0.35,
            weight: 3
        }).addTo(this.map);

        this.map.fitBounds(this.polygon.getBounds(), { padding: [20, 20] });

        const geoJsonData = {
            type: "FeatureCollection",
            features: [
                {
                    type: "Feature",
                    properties: {},
                    geometry: {
                        type: "Polygon",
                        coordinates: [closedPoints]
                    }
                }
            ]
        };

        this.geometryJsonTarget.value = JSON.stringify(geoJsonData);
        console.log("GeoJSON gravado no formulário com sucesso:", this.geometryJsonTarget.value);
    }

    resetPolygon(e) {
        if (e) e.preventDefault();

        if (this.polygon) this.map.removeLayer(this.polygon);
        if (this.polyline) this.map.removeLayer(this.polyline);
        this.markers.forEach(m => this.map.removeLayer(m));

        this.points = [];
        this.markers = [];
        this.polyline = null;
        this.polygon = null;

        this.geometryJsonTarget.value = "";
    }
}