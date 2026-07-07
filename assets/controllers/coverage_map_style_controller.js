import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.element.addEventListener('ux:map:polygon:before-create', this._onPolygonBeforeCreate);
  }

  disconnect() {
    this.element.removeEventListener('ux:map:polygon:before-create', this._onPolygonBeforeCreate);
  }

  _onPolygonBeforeCreate(event) {
    // Leaflet bridge: these become L.Polygon options
    event.detail.definition.bridgeOptions = {
      color: '#7DB928',
      weight: 2,
      fillColor: '#7DB928',
      fillOpacity: 0.25,
      ...event.detail.definition.bridgeOptions,
    };
  }
}