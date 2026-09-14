// Mappe OpenStreetMap: la mappa della scheda azienda e la scelta della
// posizione nei moduli. Serve Leaflet, caricato prima di questo file.
(() => {
    if (!window.L) {
        return;
    }

    const ITALY = [41.9, 12.5];

    const addTiles = (map, element) => {
        L.tileLayer(element.dataset.tiles, {
            maxZoom: 19,
            attribution: element.dataset.attribution,
        }).addTo(map);
    };

    // Scheda pubblica: solo guardare, e la rotella non ruba lo scorrimento della pagina.
    document.querySelectorAll('[data-company-map]').forEach((element) => {
        const point = [parseFloat(element.dataset.lat), parseFloat(element.dataset.lng)];
        const map = L.map(element, { scrollWheelZoom: false }).setView(point, 16);

        addTiles(map, element);
        L.marker(point, { title: element.dataset.name, alt: element.dataset.name }).addTo(map);
    });

    // Moduli: il segnaposto si mette cercando l'indirizzo, cliccando o trascinando.
    document.querySelectorAll('[data-location-picker]').forEach((picker) => {
        const latitude = picker.querySelector('[data-location-lat]');
        const longitude = picker.querySelector('[data-location-lng]');
        const status = picker.querySelector('[data-location-status]');
        const query = picker.querySelector('[data-location-query]');
        const hasPoint = latitude.value !== '' && longitude.value !== '';

        const map = L.map(picker.querySelector('[data-location-map]'))
            .setView(hasPoint ? [+latitude.value, +longitude.value] : ITALY, hasPoint ? 16 : 5);
        addTiles(map, picker);

        let marker = null;

        const store = (point) => {
            const position = L.latLng(point);
            latitude.value = position.lat.toFixed(7);
            longitude.value = position.lng.toFixed(7);
        };

        const place = (point, zoom) => {
            if (marker) {
                marker.setLatLng(point);
            } else {
                marker = L.marker(point, { draggable: true }).addTo(map);
                marker.on('dragend', () => store(marker.getLatLng()));
            }

            store(point);

            if (zoom) {
                map.setView(point, zoom);
            }
        };

        if (hasPoint) {
            place([+latitude.value, +longitude.value]);
        }

        map.on('click', (event) => place(event.latlng));

        picker.querySelector('[data-location-search]').addEventListener('click', async () => {
            const text = query.value.trim();

            if (!text) {
                status.textContent = 'Scrivi prima l\'indirizzo da cercare.';
                return;
            }

            status.textContent = 'Cerco…';

            try {
                const url = `${picker.dataset.geocoder}?format=jsonv2&limit=1&countrycodes=it&q=${encodeURIComponent(text)}`;
                const results = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();

                if (!results.length) {
                    status.textContent = 'Indirizzo non trovato: prova a scriverlo diversamente, oppure clicca sulla mappa.';
                    return;
                }

                place([parseFloat(results[0].lat), parseFloat(results[0].lon)], 17);
                status.textContent = 'Trovato. Se il segnaposto non è nel punto giusto, trascinalo.';
            } catch (error) {
                status.textContent = 'Ricerca non riuscita. Puoi cliccare sulla mappa per mettere il segnaposto.';
            }
        });
    });
})();
