const hnsMapEl = document.getElementById("hnsMap");

const HNS_TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
const HNS_TILE_MAX_ZOOM = 19;

// A licence term, not decoration. Leaflet renders it into the map corner.
const HNS_TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

let hnsMap = null;

// Kept as plain {lat, lng} beside the Leaflet object, so persistence later
// sends what is already here rather than reshaping anything.
const hnsMarkers = [];

function hnsAddMarker(lat, lng) {
	const marker = L.marker([lat, lng]).addTo(hnsMap);

	// Without stopping it, the click reaches the map too and drops a second
	// marker under the one just removed.
	marker.on("click", event => {
		L.DomEvent.stopPropagation(event);
		hnsRemoveMarker(marker);
	});

	hnsMarkers.push({ lat, lng, marker });

	return marker;
}

function hnsRemoveMarker(marker) {
	hnsMap.removeLayer(marker);

	const at = hnsMarkers.findIndex(entry => entry.marker === marker);
	if (at !== -1) {
		hnsMarkers.splice(at, 1);
	}
}

function hnsLoadMap() {
	hnsMapEl.textContent = "";
	hnsMapEl.classList.add("hnsMapLoaded");

	/// Leaflet works its image folder out from its own script src, which the
	/// asset() cache stamp defeats - without this the default pin is invisible.
	L.Icon.Default.imagePath = "/modules/hideandseek/vendor/leaflet/images/";

	hnsMap = L.map(hnsMapEl).setView(
		[Number(hnsMapEl.dataset.lat), Number(hnsMapEl.dataset.lng)],
		Number(hnsMapEl.dataset.zoom)
	);

	L.tileLayer(HNS_TILE_URL, {
		attribution: HNS_TILE_ATTRIBUTION,
		maxZoom: HNS_TILE_MAX_ZOOM,
	}).addTo(hnsMap);

	/// The container was a placeholder a moment ago, so Leaflet measured it
	/// before the loaded class gave it its real height.
	hnsMap.invalidateSize();

	hnsMap.on("click", event => hnsAddMarker(event.latlng.lat, event.latlng.lng));
}

/* Nothing reaches the tile server until this button is pressed. Loading tiles
   makes the visitor's browser contact the OpenStreetMap Foundation and hand
   over their IP, so it waits to be asked. */
function hnsRenderMapPlaceholder() {
	const note = document.createElement("p");
	note.className = "hnsMapNote";
	note.textContent = t("map.tileSource");

	const button = document.createElement("button");
	button.type = "button";
	button.className = "hnsMapLoad";
	button.textContent = t("map.load");
	button.addEventListener("click", hnsLoadMap);

	hnsMapEl.append(note, button);
}

if (hnsMapEl) {
	hnsRenderMapPlaceholder();
}
