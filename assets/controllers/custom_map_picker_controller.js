import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["latitude", "longitude"]

    connect() {
        // Mudamos para ux:map:connect para garantir que o mapa já está instanciado e pronto a ouvir cliques
        window.addEventListener('ux:map:connect', this._onConnect.bind(this));
    }

    disconnect() {
        window.removeEventListener('ux:map:connect', this._onConnect.bind(this));
    }

    _onConnect(event) {
        if (!this.element.contains(event.target)) return;

        const map = event.detail.leafletMap || event.detail.map;
        const L = window.L || event.detail.L;

        if (!map) return;

        this.map = map;
        this.L = L;

        // 1. LER OS VALORES DIRETAMENTE DOS INPUTS HTML (Garante que lê as strings com 7 casas)
        let latRaw = this.latitudeTarget.value ? this.latitudeTarget.value.toString().replace(',', '.') : '';
        let lngRaw = this.longitudeTarget.value ? this.longitudeTarget.value.toString().replace(',', '.') : '';

        console.log("Valores puros lidos pelo JS no HTML:", latRaw, lngRaw);

        const savedLat = parseFloat(latRaw);
        const savedLng = parseFloat(lngRaw);

        console.log("Valores convertidos para Float no JS:", savedLat, savedLng);

        this.marker = null;

        // 2. VERIFICAÇÃO RIGOROSA: Se existirem coordenadas válidas nos inputs, move o mapa para lá
        if (!isNaN(savedLat) && !isNaN(savedLng) && savedLat !== 0 && savedLng !== 0) {
            console.log("Modo Edição detetado. A posicionar o mapa em:", savedLat, savedLng);

            // Criar o marcador no sítio exato guardado
            this.marker = this.L.marker([savedLat, savedLng]).addTo(this.map);

            // Forçar o mapa a focar nas coordenadas guardadas com um zoom alto (ex: 17)
            this.map.setView([savedLat, savedLng], 17);
        }

        // 3. Forçar o Leaflet a recalcular o tamanho para renderizar os blocos sem falhas cinzentas
        setTimeout(() => {
            this.map.invalidateSize();
        }, 200);

        // 4. Teu ouvinte de cliques já existente...
        this.map.on('click', (e) => {
            const latString = e.latlng.lat.toFixed(7);
            const lngString = e.latlng.lng.toFixed(7);

            this.latitudeTarget.value = latString;
            this.longitudeTarget.value = lngString;

            const markerLatLng = new this.L.LatLng(parseFloat(latString), parseFloat(lngString));
            if (this.marker) {
                this.marker.setLatLng(markerLatLng);
            } else {
                this.marker = this.L.marker(markerLatLng).addTo(this.map);
            }
        });
    }

    // Sincronização se o utilizador digitar as coordenadas à mão
    syncMap() {
        const lat = parseFloat(this.latitudeTarget.value);
        const lng = parseFloat(this.longitudeTarget.value);

        if (!isNaN(lat) && !isNaN(lng) && this.map && this.L) {
            const newLatLng = new this.L.LatLng(lat, lng);
            this.map.setView(newLatLng, 16);

            if (this.marker) {
                this.marker.setLatLng(newLatLng);
            } else {
                this.marker = this.L.marker(newLatLng).addTo(this.map);
            }
        }
    }
}