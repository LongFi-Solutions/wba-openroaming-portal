import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('ux:map:polygon:before-create', this._onPolygonBeforeCreate);
    }

    disconnect() {
        this.element.removeEventListener(
            'ux:map:polygon:before-create',
            this._onPolygonBeforeCreate
        );
    }

    _onPolygonBeforeCreate(event) {
        // Leaflet bridge: these become L.Polygon options
        event.detail.definition.bridgeOptions = {
            weight: 2,
            color: '#7c3aed',
            fillColor: '#8b5cf6',
            fillOpacity: 0.18,
            ...event.detail.definition.bridgeOptions,
        };
    }
}
