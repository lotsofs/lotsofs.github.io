const hnsMapEl = document.getElementById("hnsMap");

const HNS_STYLE_URL = "/modules/hideandseek/json/mapStyle.json";
const HNS_MAPLIBRE_URL = "/modules/hideandseek/vendor/maplibre/maplibre-gl.js";
const HNS_MAPLIBRE_CSS_URL = "/modules/hideandseek/vendor/maplibre/maplibre-gl.css";
const HNS_DELAUNAY_URL = "/modules/hideandseek/vendor/d3-delaunay/d3-delaunay.min.js";

const HNS_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors &middot; tiles <a href="https://openfreemap.org/" target="_blank" rel="noopener">OpenFreeMap</a>';

let hnsMap = null;

// Plain {lat, lng} beside the marker object, so persistence later sends what is
// already here rather than reshaping anything.
const hnsMarkers = [];

function hnsMapCentre() {
	return {
		lat: Number(hnsMapEl.dataset.lat),
		lng: Number(hnsMapEl.dataset.lng),
		zoom: Number(hnsMapEl.dataset.zoom),
	};
}

/// Injected rather than shipped in the page: MapLibre is 803KB, and a visitor
/// who never opens the map should never pay for it.
function hnsLoadScript(url) {
	return new Promise((resolve, reject) => {
		const script = document.createElement("script");
		script.src = url;
		script.addEventListener("load", resolve);
		script.addEventListener("error", () => reject(new Error(url)));
		document.head.appendChild(script);
	});
}

function hnsLoadStylesheet(url) {
	const link = document.createElement("link");
	link.rel = "stylesheet";
	link.href = url;
	document.head.appendChild(link);
}

function hnsAddMarker(lat, lng) {
	const marker = new maplibregl.Marker({ color: "#0a7a57" }).setLngLat([lng, lat]).addTo(hnsMap);

	// Without stopping it the click reaches the canvas too, dropping a second
	// marker under the one just removed.
	marker.getElement().addEventListener("click", event => {
		event.stopPropagation();
		marker.remove();

		const at = hnsMarkers.findIndex(entry => entry.marker === marker);
		if (at !== -1) {
			hnsMarkers.splice(at, 1);
		}

		hnsUpdateOverlay();
	});

	hnsMarkers.push({ lat, lng, marker });
	hnsUpdateOverlay();
}

function hnsLoadMap() {
	hnsMapEl.textContent = t("map.loading");
	hnsMapEl.classList.add("hnsMapLoaded");

	hnsLoadStylesheet(HNS_MAPLIBRE_CSS_URL);

	hnsLoadScript(HNS_MAPLIBRE_URL)
		.then(() => {
			hnsMapEl.textContent = "";

			const centre = hnsMapCentre();

			hnsMap = new maplibregl.Map({
				container: hnsMapEl,
				style: HNS_STYLE_URL,
				center: [centre.lng, centre.lat],
				zoom: centre.zoom,
				attributionControl: { customAttribution: HNS_ATTRIBUTION },
			});

			hnsMap.addControl(new maplibregl.NavigationControl());

			hnsMap.on("click", event => hnsAddMarker(event.lngLat.lat, event.lngLat.lng));

			hnsMap.on("load", () => {
				hnsHideLabels();
				hnsAddImportedLayers();
				hnsMapReady = true;

				if (hnsPendingPoi) {
					hnsFocusPoi(hnsPendingPoi);
				}
			});
		})
		.catch(error => {
			hnsMapEl.classList.remove("hnsMapLoaded");
			hnsMapEl.textContent = t("map.loadFailed", { error: error.message });
		});
}

/* Switches off every layer that writes text on the map.

   Two tests, and both earn their keep.

   `type: symbol` rather than source layer, which is the only safe way:
   `waterway-name` shares the `waterway` source layer with the canals and the
   one-way arrows share `transportation` with the roads, so hiding those source
   layers wholesale would erase the geometry along with the labels. A symbol
   layer is never geometry, so this can only ever mute text.

   And it must *write text* - a symbol layer with no `text-field` draws an icon,
   which is not a label. In this style that is exactly the two one-way arrow
   layers, so the arrows survive without being named here.

   Together they mean a re-pasted style needs no list maintained here: whatever
   its label layers are called, they write text and they are off.

   Visibility rather than removeLayer, so nothing is destroyed. */
function hnsHideLabels() {
	for (const layer of hnsMap.getStyle().layers) {
		if (layer.type !== "symbol") {
			continue;
		}

		if (!(layer.layout && layer.layout["text-field"])) {
			continue;
		}

		try {
			hnsMap.setLayoutProperty(layer.id, "visibility", "none");
		}
		catch (error) {
			console.warn("hideandseek: could not hide layer " + layer.id, error);
		}
	}
}

function hnsPoiRow(poi, onActivate) {
	const row = document.createElement("tr");
	row.className = "hnsPoiRow";

	const name = document.createElement("td");
	if (poi.name) {
		name.textContent = poi.name;
	}
	else {
		name.textContent = poi.klass;
		name.className = "hnsPoiUnnamed";
	}

	row.append(name);

	row.tabIndex = 0;
	const go = () => onActivate(poi);
	row.addEventListener("click", go);
	row.addEventListener("keydown", event => {
		if (event.key === "Enter") {
			go();
		}
	});

	return row;
}

let hnsPoiRing = null;

function hnsRingPoi(poi) {
	if (hnsPoiRing) {
		hnsPoiRing.remove();
	}

	const ring = document.createElement("div");
	ring.className = "hnsPoiRing";

	hnsPoiRing = new maplibregl.Marker({ element: ring, anchor: "center" }).setLngLat([poi.lng, poi.lat]).addTo(hnsMap);
}

function hnsReadSetting(key) {
	try {
		return localStorage.getItem(key);
	}
	catch (error) {
		return null;
	}
}

function hnsWriteSetting(key, value) {
	try {
		localStorage.setItem(key, value);
	}
	catch (error) {
	}
}

function hnsStoredHiddenCategories() {
	try {
		const ids = JSON.parse(hnsReadSetting("hnsHiddenCategories") || "[]");
		return new Set(Array.isArray(ids) ? ids.filter(Number.isInteger) : []);
	}
	catch (error) {
		return new Set();
	}
}

const hnsHiddenCategories = hnsStoredHiddenCategories();
let hnsShowUnnamed = hnsReadSetting("hnsShowUnnamed") === "1";
const hnsOpenCategories = new Set();
let hnsOverlay = null;
let hnsDelaunayLoading = null;

const hnsImportedPoisEl = document.getElementById("hnsImportedPois");
const hnsImportedPoiTableEl = document.getElementById("hnsImportedPoiTable");

const hnsImportedData = hnsImportedPoisEl ? JSON.parse(hnsImportedPoisEl.textContent) : { categories: [], pois: [] };

const hnsImportedCategories = new Map(hnsImportedData.categories.map(category => [category.id, {
	id: category.id,
	name: category.name,
	colour: category.colour || getComputedStyle(document.documentElement).getPropertyValue("--imported").trim(),
	icon: category.icon || "",
}]));

const hnsImportedPois = hnsImportedData.pois
	.filter(poi => hnsImportedCategories.has(poi.category))
	.map(poi => ({
		name: poi.name,
		klass: hnsImportedCategories.get(poi.category).name,
		category: poi.category,
		lat: poi.lat,
		lng: poi.lng,
	}));

const HNS_POI_ICON_RATIO = 2;
const HNS_ICON_FONT = '"Segoe UI Symbol", "Noto Sans Symbols 2", "Noto Sans Symbols", "Apple Symbols", sans-serif';

let hnsPendingPoi = null;
let hnsMapReady = false;

function hnsFocusPoi(poi) {
	if (!hnsMapReady) {
		hnsPendingPoi = poi;
		return;
	}

	hnsPendingPoi = null;
	hnsRingPoi(poi);

	if (!hnsMap.getBounds().contains([poi.lng, poi.lat])) {
		hnsMap.easeTo({ center: [poi.lng, poi.lat] });
	}
}

function hnsTextPresentation(icon) {
	return icon.replace(/\uFE0F/g, "") + "\uFE0E";
}

function hnsPoiIconImage(category) {
	const side = (category.icon ? 30 : 14) * HNS_POI_ICON_RATIO;
	const canvas = document.createElement("canvas");
	canvas.width = side;
	canvas.height = side;

	const ctx = canvas.getContext("2d");
	const centre = side / 2;

	if (!category.icon) {
		const stroke = 2 * HNS_POI_ICON_RATIO;
		ctx.beginPath();
		ctx.arc(centre, centre, centre - stroke / 2, 0, Math.PI * 2);
		ctx.fillStyle = category.colour;
		ctx.fill();
		ctx.lineWidth = stroke;
		ctx.strokeStyle = "#ffffff";
		ctx.stroke();

		return ctx.getImageData(0, 0, side, side);
	}

	const glyph = hnsTextPresentation(category.icon);

	ctx.font = "bold " + (22 * HNS_POI_ICON_RATIO) + "px " + HNS_ICON_FONT;
	ctx.textAlign = "center";
	ctx.textBaseline = "middle";
	ctx.lineJoin = "round";
	ctx.lineWidth = 4 * HNS_POI_ICON_RATIO;
	ctx.strokeStyle = category.colour;
	ctx.strokeText(glyph, centre, centre + HNS_POI_ICON_RATIO);
	ctx.fillStyle = hnsIsDark(category.colour) ? "#ffffff" : "#000000";
	ctx.fillText(glyph, centre, centre + HNS_POI_ICON_RATIO);

	return ctx.getImageData(0, 0, side, side);
}

function hnsLuminance(hex) {
	const match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);

	if (!match) {
		return null;
	}

	const [r, g, b] = match.slice(1).map(part => {
		const channel = parseInt(part, 16) / 255;
		return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
	});

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function hnsIsDark(hex) {
	const luminance = hnsLuminance(hex);

	return luminance !== null && (1.05 / (luminance + 0.05)) > ((luminance + 0.05) / 0.05);
}

function hnsLabelHalo(hex) {
	const luminance = hnsLuminance(hex);

	return luminance !== null && 1.05 / (luminance + 0.05) < 3 ? "#000000" : "#ffffff";
}

function hnsAddImportedLayers() {
	if (!hnsImportedPois.length) {
		return;
	}

	for (const category of hnsImportedCategories.values()) {
		hnsMap.addImage("hnsPoiIcon-" + category.id, hnsPoiIconImage(category), { pixelRatio: HNS_POI_ICON_RATIO });
	}

	hnsMap.addSource("hnsImported", {
		type: "geojson",
		data: {
			type: "FeatureCollection",
			features: hnsImportedPois.map(poi => {
				const category = hnsImportedCategories.get(poi.category);

				return {
					type: "Feature",
					geometry: { type: "Point", coordinates: [poi.lng, poi.lat] },
					properties: {
						name: poi.name,
						category: category.id,
						named: poi.name !== "",
						icon: "hnsPoiIcon-" + category.id,
						colour: category.colour,
						halo: hnsLabelHalo(category.colour),
						hasIcon: category.icon !== "",
					},
				};
			}),
		},
	});

	hnsMap.addLayer({
		id: "hnsImportedPoints",
		type: "symbol",
		source: "hnsImported",
		layout: {
			"icon-image": ["get", "icon"],
			"icon-size": ["interpolate", ["linear"], ["zoom"], 8, 0.6, 14, 1],
			"icon-allow-overlap": true,
			"icon-ignore-placement": true,
			"text-field": ["step", ["zoom"], "", 13, ["get", "name"]],
			"text-font": ["Noto Sans Bold"],
			"text-size": 12,
			"text-anchor": "top",
			"text-offset": ["case", ["get", "hasIcon"], ["literal", [0, 1.3]], ["literal", [0, 0.7]]],
			"text-optional": true,
		},
		paint: {
			"text-color": ["get", "colour"],
			"text-halo-color": ["get", "halo"],
			"text-halo-width": 1,
		},
	});

	hnsMap.addSource("hnsOverlay", { type: "geojson", data: { type: "FeatureCollection", features: [] } });

	hnsMap.addLayer({
		id: "hnsOverlayCasing",
		type: "line",
		source: "hnsOverlay",
		layout: { "line-join": "round", "line-cap": "round" },
		paint: { "line-color": ["get", "halo"], "line-width": 4, "line-opacity": 0.8 },
	}, "hnsImportedPoints");

	hnsMap.addLayer({
		id: "hnsOverlayLines",
		type: "line",
		source: "hnsOverlay",
		layout: { "line-join": "round", "line-cap": "round" },
		paint: { "line-color": ["get", "colour"], "line-width": 2 },
	}, "hnsImportedPoints");

	hnsApplyImportedFilter();
	hnsUpdateOverlay();
}

function hnsLoadDelaunay() {
	if (!hnsDelaunayLoading) {
		hnsDelaunayLoading = hnsLoadScript(HNS_DELAUNAY_URL).catch(error => {
			hnsDelaunayLoading = null;
			throw error;
		});
	}

	return hnsDelaunayLoading;
}

const HNS_RAY_LENGTH = 10 * Math.PI / 180;
const HNS_ARC_STEP = 0.25 * Math.PI / 180;

function hnsVec(lat, lng) {
	const phi = lat * Math.PI / 180;
	const lambda = lng * Math.PI / 180;
	return [Math.cos(phi) * Math.cos(lambda), Math.cos(phi) * Math.sin(lambda), Math.sin(phi)];
}

function hnsDot(a, b) {
	return a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
}

function hnsCross(a, b) {
	return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
}

function hnsSub(a, b) {
	return [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
}

function hnsScale(a, k) {
	return [a[0] * k, a[1] * k, a[2] * k];
}

function hnsUnit(a) {
	const length = Math.hypot(a[0], a[1], a[2]);
	return length < 1e-15 ? null : hnsScale(a, 1 / length);
}

function hnsLngLat(v) {
	return [Math.atan2(v[1], v[0]) * 180 / Math.PI, Math.asin(Math.max(-1, Math.min(1, v[2]))) * 180 / Math.PI];
}

function hnsArc(from, tangent, angle) {
	const steps = Math.max(1, Math.ceil(angle / HNS_ARC_STEP));
	const line = [];

	for (let i = 0; i <= steps; i++) {
		const theta = angle * i / steps;
		line.push(hnsLngLat([
			from[0] * Math.cos(theta) + tangent[0] * Math.sin(theta),
			from[1] * Math.cos(theta) + tangent[1] * Math.sin(theta),
			from[2] * Math.cos(theta) + tangent[2] * Math.sin(theta),
		]));
	}

	return line;
}

function hnsArcBetween(from, to) {
	const angle = Math.acos(Math.max(-1, Math.min(1, hnsDot(from, to))));
	const tangent = hnsUnit(hnsSub(to, hnsScale(from, hnsDot(from, to))));

	return tangent ? hnsArc(from, tangent, angle) : null;
}

function hnsCircumcentre(a, b, c) {
	const n = hnsUnit(hnsCross(hnsSub(b, a), hnsSub(c, a)));

	if (!n) {
		return null;
	}

	return hnsDot(n, a) < 0 ? hnsScale(n, -1) : n;
}

function hnsBisectorRay(centre, a, b, away) {
	let outward = hnsCross(a, b);

	if (hnsDot(outward, away) > 0) {
		outward = hnsScale(outward, -1);
	}

	let tangent = hnsUnit(hnsCross(hnsSub(a, b), centre));

	if (!tangent) {
		return null;
	}

	if (hnsDot(tangent, outward) < 0) {
		tangent = hnsScale(tangent, -1);
	}

	return hnsArc(centre, tangent, HNS_RAY_LENGTH);
}

function hnsNearestLines(pois) {
	const vectors = pois.map(poi => hnsVec(poi.lat, poi.lng));
	const middle = hnsUnit(vectors.reduce((sum, v) => [sum[0] + v[0], sum[1] + v[1], sum[2] + v[2]], [0, 0, 0]));
	const east = hnsUnit(hnsCross([0, 0, 1], middle)) || [0, 1, 0];
	const north = hnsCross(middle, east);

	const projected = vectors.map(v => {
		const k = 1 / (1 + hnsDot(v, middle));
		return [hnsDot(v, east) * k, hnsDot(v, north) * k];
	});

	const delaunay = d3.Delaunay.from(projected);
	const lines = [];
	const sameName = (i, j) => pois[i].name !== "" && pois[i].name === pois[j].name;

	if (delaunay.collinear || vectors.length < 3 || delaunay.triangles.length === 0) {
		const order = delaunay.collinear || delaunay.hull;

		for (let i = 1; i < order.length; i++) {
			if (sameName(order[i - 1], order[i])) {
				continue;
			}

			const a = vectors[order[i - 1]];
			const b = vectors[order[i]];
			const centre = hnsUnit([a[0] + b[0], a[1] + b[1], a[2] + b[2]]);
			const tangent = centre && hnsUnit(hnsCross(hnsSub(a, b), centre));

			if (tangent) {
				lines.push(hnsArc(centre, tangent, HNS_RAY_LENGTH));
				lines.push(hnsArc(centre, hnsScale(tangent, -1), HNS_RAY_LENGTH));
			}
		}

		return lines;
	}

	const { triangles, halfedges } = delaunay;
	const centres = [];

	for (let t = 0; t < triangles.length / 3; t++) {
		centres.push(hnsCircumcentre(vectors[triangles[3 * t]], vectors[triangles[3 * t + 1]], vectors[triangles[3 * t + 2]]));
	}

	for (let e = 0; e < triangles.length; e++) {
		const opposite = halfedges[e];
		const centre = centres[Math.floor(e / 3)];

		const next = e % 3 === 2 ? e - 2 : e + 1;

		if (!centre || (opposite !== -1 && opposite < e) || sameName(triangles[e], triangles[next])) {
			continue;
		}

		if (opposite !== -1) {
			const other = centres[Math.floor(opposite / 3)];
			const arc = other && hnsArcBetween(centre, other);

			if (arc) {
				lines.push(arc);
			}

			continue;
		}

		const third = e % 3 === 0 ? e + 2 : e - 1;
		const ray = hnsBisectorRay(centre, vectors[triangles[e]], vectors[triangles[next]], vectors[triangles[third]]);

		if (ray) {
			lines.push(ray);
		}
	}

	return lines;
}

const HNS_EARTH_RADIUS = 6371008.8;
const HNS_CIRCLE_SAMPLES = 128;

function hnsAngle(a, b) {
	const cross = hnsCross(a, b);
	return Math.atan2(Math.hypot(cross[0], cross[1], cross[2]), hnsDot(a, b));
}

function hnsCircleLines(pois, pin) {
	const unique = new Map();
	for (const poi of pois) {
		unique.set(poi.lat + "," + poi.lng, hnsVec(poi.lat, poi.lng));
	}

	const centres = [...unique.values()];
	const from = hnsVec(pin.lat, pin.lng);
	const rho = Math.min(...centres.map(centre => hnsAngle(from, centre)));

	if (!(rho > 0) || !Number.isFinite(rho)) {
		return { radius: centres.length ? 0 : null, lines: [] };
	}

	const cosRho = Math.cos(rho);
	const sinRho = Math.sin(rho);
	const cosReach = 2 * rho < Math.PI ? Math.cos(2 * rho) : -2;
	const lines = [];

	centres.forEach((centre, i) => {
		const neighbours = centres.filter((other, j) => j !== i && hnsDot(centre, other) > cosReach);
		const east = hnsUnit(hnsCross([0, 0, 1], centre)) || [0, 1, 0];
		const north = hnsCross(centre, east);

		const at = theta => [
			centre[0] * cosRho + (east[0] * Math.cos(theta) + north[0] * Math.sin(theta)) * sinRho,
			centre[1] * cosRho + (east[1] * Math.cos(theta) + north[1] * Math.sin(theta)) * sinRho,
			centre[2] * cosRho + (east[2] * Math.cos(theta) + north[2] * Math.sin(theta)) * sinRho,
		];
		const covered = theta => {
			const point = at(theta);
			return neighbours.some(other => hnsDot(point, other) > cosRho);
		};
		const edge = (inside, outside) => {
			for (let k = 0; k < 30; k++) {
				const middle = (inside + outside) / 2;
				if (covered(middle)) {
					inside = middle;
				}
				else {
					outside = middle;
				}
			}
			return hnsLngLat(at(outside));
		};

		const step = 2 * Math.PI / HNS_CIRCLE_SAMPLES;
		const states = [];
		for (let k = 0; k < HNS_CIRCLE_SAMPLES; k++) {
			states.push(covered(k * step));
		}

		const start = states.indexOf(true);

		if (start === -1) {
			const ring = [];
			for (let k = 0; k <= HNS_CIRCLE_SAMPLES; k++) {
				ring.push(hnsLngLat(at(k * step)));
			}
			lines.push(ring);
			return;
		}

		let run = null;

		for (let k = start + 1; k <= start + HNS_CIRCLE_SAMPLES; k++) {
			const theta = k * step;
			const isCovered = states[k % HNS_CIRCLE_SAMPLES];

			if (!isCovered && run === null) {
				run = [edge(theta - step, theta), hnsLngLat(at(theta))];
			}
			else if (!isCovered) {
				run.push(hnsLngLat(at(theta)));
			}
			else if (run !== null) {
				run.push(edge(theta, theta - step));
				lines.push(run);
				run = null;
			}
		}
	});

	return { radius: rho * HNS_EARTH_RADIUS, lines };
}

function hnsFormatDistance(metres) {
	if (metres < 1000) {
		return t("poi.metres", { n: Math.round(metres) });
	}

	return t("poi.kilometres", { n: (metres / 1000).toFixed(metres < 10000 ? 2 : 1) });
}

function hnsShowDistance(categoryId, text) {
	for (const span of document.querySelectorAll(".hnsPoiGroupDistance")) {
		span.textContent = Number(span.dataset.categoryId) === categoryId ? text : "";
	}
}

function hnsSetOverlayLines(category, lines) {
	const source = hnsMap && hnsMap.getSource("hnsOverlay");

	if (!source) {
		return;
	}

	source.setData({
		type: "FeatureCollection",
		features: category && lines.length ? [{
			type: "Feature",
			geometry: { type: "MultiLineString", coordinates: lines },
			properties: { colour: category.colour, halo: hnsLabelHalo(category.colour) },
		}] : [],
	});
}

function hnsUpdateOverlay() {
	const overlay = hnsOverlay;
	const category = overlay && hnsImportedCategories.get(overlay.category);
	const pois = category && !hnsHiddenCategories.has(category.id)
		? hnsImportedPois.filter(poi => poi.category === category.id && (hnsShowUnnamed || poi.name !== ""))
		: [];

	hnsShowDistance(null, "");

	if (!category) {
		hnsSetOverlayLines(null, []);
		return;
	}

	if (overlay.kind === "circles") {
		const pin = hnsMarkers[hnsMarkers.length - 1];

		if (!pin) {
			hnsShowDistance(category.id, t("poi.circlesNoPin"));
			hnsSetOverlayLines(null, []);
			return;
		}

		const result = hnsCircleLines(pois, pin);
		hnsShowDistance(category.id, result.radius === null ? "" : hnsFormatDistance(result.radius));
		hnsSetOverlayLines(category, result.lines);
		return;
	}

	if (pois.length < 2 || !hnsMap || !hnsMap.getSource("hnsOverlay")) {
		hnsSetOverlayLines(null, []);
		return;
	}

	hnsLoadDelaunay()
		.then(() => {
			if (hnsOverlay !== overlay) {
				return;
			}

			hnsSetOverlayLines(category, hnsNearestLines(pois));
		})
		.catch(error => console.warn("hideandseek: could not load the nearest-area library", error));
}

function hnsSetOverlay(kind, id) {
	hnsOverlay = hnsOverlay && hnsOverlay.kind === kind && hnsOverlay.category === id ? null : { kind, category: id };

	for (const button of document.querySelectorAll(".hnsPoiGroupOverlay")) {
		const pressed = hnsOverlay !== null
			&& button.dataset.overlayKind === hnsOverlay.kind
			&& Number(button.dataset.categoryId) === hnsOverlay.category;
		button.setAttribute("aria-pressed", String(pressed));
	}

	hnsUpdateOverlay();
}

function hnsOverlayButton(kind, glyph, category) {
	const button = document.createElement("button");
	button.type = "button";
	button.className = "hnsPoiGroupOverlay";
	button.dataset.overlayKind = kind;
	button.dataset.categoryId = category.id;
	button.textContent = glyph;
	button.setAttribute("aria-pressed", String(hnsOverlay !== null && hnsOverlay.kind === kind && hnsOverlay.category === category.id));

	const label = t(kind === "nearest" ? "poi.nearest" : "poi.circles", { class: category.name });
	button.setAttribute("aria-label", label);
	button.dataset.tooltip = label;
	button.addEventListener("click", event => {
		event.preventDefault();
		hnsSetOverlay(kind, category.id);
	});

	return button;
}

function hnsApplyImportedFilter() {
	if (!hnsMap || !hnsMap.getLayer("hnsImportedPoints")) {
		return;
	}

	hnsMap.setFilter("hnsImportedPoints", [
		"all",
		["!", ["in", ["get", "category"], ["literal", [...hnsHiddenCategories]]]],
		hnsShowUnnamed ? true : ["get", "named"],
	]);
}

function hnsIconCanvas(category) {
	const image = hnsPoiIconImage(category);
	const canvas = document.createElement("canvas");
	canvas.width = image.width;
	canvas.height = image.height;
	canvas.getContext("2d").putImageData(image, 0, 0);
	canvas.className = "hnsPoiGroupIcon";
	canvas.style.width = (image.width / HNS_POI_ICON_RATIO) + "px";
	canvas.style.height = (image.height / HNS_POI_ICON_RATIO) + "px";

	return canvas;
}

function hnsImportedGroup(category, pois) {
	const group = document.createElement("details");
	group.className = "hnsPoiGroup hnsPoiGroupImported";
	group.style.setProperty("--groupColour", category.colour);
	group.style.setProperty("--groupInk", hnsIsDark(category.colour) ? "#ffffff" : "#000000");
	group.open = hnsOpenCategories.has(category.id);
	group.classList.toggle("hnsPoiGroupHidden", hnsHiddenCategories.has(category.id));

	group.addEventListener("toggle", () => {
		if (group.open) {
			hnsOpenCategories.add(category.id);
		}
		else {
			hnsOpenCategories.delete(category.id);
		}
	});

	const summary = document.createElement("summary");
	summary.className = "hnsPoiGroupName";

	const toggle = document.createElement("input");
	toggle.type = "checkbox";
	toggle.className = "hnsPoiGroupToggle";
	toggle.checked = !hnsHiddenCategories.has(category.id);
	toggle.setAttribute("aria-label", t("poi.showCategory", { class: category.name }));
	toggle.addEventListener("change", () => {
		if (toggle.checked) {
			hnsHiddenCategories.delete(category.id);
		}
		else {
			hnsHiddenCategories.add(category.id);
		}

		group.classList.toggle("hnsPoiGroupHidden", !toggle.checked);
		hnsWriteSetting("hnsHiddenCategories", JSON.stringify([...hnsHiddenCategories]));
		hnsApplyImportedFilter();
		hnsUpdateOverlay();
		hnsUpdateAllToggle();
	});

	const distance = document.createElement("span");
	distance.className = "hnsPoiGroupDistance";
	distance.dataset.categoryId = category.id;

	const label = document.createElement("span");
	label.className = "hnsPoiGroupLabel";
	label.textContent = t("poi.group", { class: category.name, count: pois.length });

	const actions = document.createElement("span");
	actions.className = "hnsPoiGroupActions";
	actions.append(distance, hnsOverlayButton("circles", "\u25CE", category), hnsOverlayButton("nearest", "\u2B21", category));

	summary.append(toggle, hnsIconCanvas(category), label, actions);
	group.appendChild(summary);

	const table = document.createElement("table");
	table.className = "hnsPoiTable";

	const body = document.createElement("tbody");
	pois.forEach(poi => body.appendChild(hnsPoiRow(poi, hnsFocusPoi)));
	table.appendChild(body);
	group.appendChild(table);

	return group;
}

let hnsImportedCountEl = null;
let hnsAllToggleEl = null;
let hnsImportedGroupsEl = null;

function hnsRenderImportedPoiTable() {
	if (!hnsImportedPoiTableEl) {
		return;
	}

	if (!hnsImportedPois.length) {
		const empty = document.createElement("p");
		empty.className = "hnsPoiEmpty";
		empty.textContent = t("poi.empty");
		hnsImportedPoiTableEl.appendChild(empty);
		return;
	}

	hnsImportedCountEl = document.createElement("p");
	hnsImportedCountEl.className = "hnsPoiCount";

	const unnamed = document.createElement("input");
	unnamed.type = "checkbox";
	unnamed.checked = hnsShowUnnamed;
	unnamed.addEventListener("change", () => {
		hnsShowUnnamed = unnamed.checked;
		hnsWriteSetting("hnsShowUnnamed", hnsShowUnnamed ? "1" : "0");
		hnsApplyImportedFilter();
		hnsRenderImportedGroups();
		hnsUpdateOverlay();
	});

	const unnamedLabel = document.createElement("label");
	unnamedLabel.className = "hnsPoiFilter";
	unnamedLabel.append(unnamed, " " + t("poi.showUnnamed"));

	hnsAllToggleEl = document.createElement("button");
	hnsAllToggleEl.type = "button";
	hnsAllToggleEl.addEventListener("click", () => {
		const ids = [...hnsImportedCategories.keys()];
		const hide = ids.some(id => !hnsHiddenCategories.has(id));

		for (const id of ids) {
			if (hide) {
				hnsHiddenCategories.add(id);
			}
			else {
				hnsHiddenCategories.delete(id);
			}
		}

		hnsWriteSetting("hnsHiddenCategories", JSON.stringify([...hnsHiddenCategories]));
		hnsApplyImportedFilter();
		hnsRenderImportedGroups();
		hnsUpdateOverlay();
	});

	const filters = document.createElement("div");
	filters.className = "hnsPoiFilters";
	filters.append(unnamedLabel, hnsAllToggleEl);

	hnsImportedGroupsEl = document.createElement("div");

	hnsImportedPoiTableEl.append(hnsImportedCountEl, filters, hnsImportedGroupsEl);
	hnsRenderImportedGroups();
}

function hnsUpdateAllToggle() {
	if (!hnsAllToggleEl) {
		return;
	}

	const anyShown = [...hnsImportedCategories.keys()].some(id => !hnsHiddenCategories.has(id));
	hnsAllToggleEl.textContent = t(anyShown ? "poi.hideAll" : "poi.showAll");
}

function hnsRenderImportedGroups() {
	const byCategory = new Map();
	let listed = 0;

	for (const poi of hnsImportedPois) {
		if (!hnsShowUnnamed && poi.name === "") {
			continue;
		}

		const list = byCategory.get(poi.category) || [];
		list.push(poi);
		byCategory.set(poi.category, list);
		listed++;
	}

	hnsImportedCountEl.textContent = t("poi.importedCount", { places: listed, classes: byCategory.size });
	hnsImportedGroupsEl.textContent = "";

	for (const category of hnsImportedCategories.values()) {
		const pois = byCategory.get(category.id);

		if (pois) {
			hnsImportedGroupsEl.appendChild(hnsImportedGroup(category, pois));
		}
	}

	hnsUpdateAllToggle();
}

/* Nothing reaches a tile server until this button is pressed. Loading the map
   makes the visitor's browser contact OpenFreeMap and hand over their IP, so it
   waits to be asked - and the note beside it says so. */
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

hnsRenderImportedPoiTable();
hnsUpdateOverlay();
