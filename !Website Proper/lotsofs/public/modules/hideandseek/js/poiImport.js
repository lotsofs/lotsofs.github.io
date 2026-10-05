const HNS_OSM_TYPES = ["node", "way", "relation"];
const HNS_EXPORT_FORMAT = "hideandseek-pois";

function hnsParsePaste(text) {
	text = text.trim();

	if (text.startsWith("{")) {
		let data;
		try {
			data = JSON.parse(text);
		}
		catch (error) {
			return null;
		}

		if (data && data.format === HNS_EXPORT_FORMAT) {
			return Array.isArray(data.categories) ? { kind: "export", categories: data.categories } : null;
		}

		return data && Array.isArray(data.elements) ? { kind: "overpass", ...hnsOverpassPois(data.elements) } : null;
	}

	if (text.startsWith("<")) {
		const doc = new DOMParser().parseFromString(text, "application/xml");
		const root = doc.documentElement;

		return root && root.nodeName === "osm" && !doc.querySelector("parsererror")
			? { kind: "overpass", ...hnsOverpassPois(hnsOverpassXmlElements(root)) }
			: null;
	}

	return null;
}

function hnsFormatExport(data) {
	const categories = data.categories.map(category => {
		const head = JSON.stringify({ name: category.name, colour: category.colour, icon: category.icon }).slice(0, -1);
		const pois = category.pois.map(poi => "\t\t\t" + JSON.stringify(poi)).join(",\n");

		return "\t\t" + head + ", \"pois\": [\n" + pois + "\n\t\t]}";
	});

	return "{\n"
		+ "\t\"format\": " + JSON.stringify(data.format) + ",\n"
		+ "\t\"version\": " + JSON.stringify(data.version) + ",\n"
		+ "\t\"map\": " + JSON.stringify(data.map) + ",\n"
		+ "\t\"categories\": [\n" + categories.join(",\n") + "\n\t]\n"
		+ "}\n";
}

function hnsInitPoiExport() {
	const button = document.getElementById("poiExportButton");

	if (!button) {
		return;
	}

	const status = document.getElementById("poiExportStatus");
	const output = document.getElementById("poiExportText");

	button.addEventListener("click", () => {
		button.disabled = true;

		postJson("/hideandseek/ajax/export-pois", { mapId: button.dataset.mapId })
			.then(result => {
				const text = hnsFormatExport(result.export);
				const count = result.export.categories.reduce((sum, category) => sum + category.pois.length, 0);

				output.value = text;
				output.hidden = false;
				output.select();

				const copied = t("poiImport.exportCopied", { count, categories: result.export.categories.length });

				return navigator.clipboard
					? navigator.clipboard.writeText(text).then(() => copied, () => t("poiImport.exportShown"))
					: t("poiImport.exportShown");
			})
			.then(message => {
				status.textContent = message;
				status.classList.remove("formError");
				status.hidden = false;
			})
			.catch(error => {
				status.textContent = t("status.submitFailed", { error: error.message });
				status.classList.add("formError");
				status.hidden = false;
			})
			.finally(() => {
				button.disabled = false;
			});
	});
}

function hnsOverpassXmlElements(root) {
	return Array.from(root.children).map(child => {
		const element = { type: child.nodeName, id: child.getAttribute("id") };

		if (child.hasAttribute("lat") && child.hasAttribute("lon")) {
			element.lat = child.getAttribute("lat");
			element.lon = child.getAttribute("lon");
		}

		const center = child.querySelector(":scope > center");
		if (center) {
			element.center = { lat: center.getAttribute("lat"), lon: center.getAttribute("lon") };
		}

		const bounds = child.querySelector(":scope > bounds");
		if (bounds) {
			element.bounds = {};
			for (const edge of ["minlat", "minlon", "maxlat", "maxlon"]) {
				element.bounds[edge] = bounds.getAttribute(edge);
			}
		}

		element.tags = {};
		for (const tag of child.querySelectorAll(":scope > tag")) {
			element.tags[tag.getAttribute("k")] = tag.getAttribute("v");
		}

		element.nodes = Array.from(child.querySelectorAll(":scope > nd"), nd => nd.getAttribute("ref"));
		element.members = Array.from(child.querySelectorAll(":scope > member"), member => ({
			type: member.getAttribute("type"),
			ref: member.getAttribute("ref"),
		}));

		return element;
	});
}

function hnsOverpassPois(elements) {
	const memberNodes = new Set();

	for (const element of elements) {
		for (const ref of element.nodes ?? []) {
			memberNodes.add(String(ref));
		}
		for (const member of element.members ?? []) {
			if (member.type === "node") {
				memberNodes.add(String(member.ref));
			}
		}
	}

	const pois = new Map();
	let skipped = 0;

	for (const element of elements) {
		const type = element.type;
		const id = String(element.id ?? "");

		if (!HNS_OSM_TYPES.includes(type) || !/^\d+$/.test(id)) {
			continue;
		}

		const tags = element.tags && typeof element.tags === "object" ? element.tags : {};

		if (type === "node" && Object.keys(tags).length === 0 && memberNodes.has(id)) {
			continue;
		}

		const position = hnsOverpassPosition(element);

		if (position === null) {
			skipped++;
			continue;
		}

		const name = typeof tags.name === "string" ? tags.name.trim() : "";

		pois.set(type + "/" + id, {
			osmType: type,
			osmId: id,
			name: name === "" ? null : name,
			lat: position[0],
			lon: position[1],
		});
	}

	return { pois: Array.from(pois.values()), skipped };
}

function hnsOverpassPosition(element) {
	let lat;
	let lon;

	if (element.lat != null && element.lon != null) {
		lat = Number(element.lat);
		lon = Number(element.lon);
	}
	else if (element.center && element.center.lat != null && element.center.lon != null) {
		lat = Number(element.center.lat);
		lon = Number(element.center.lon);
	}
	else if (element.bounds) {
		const b = element.bounds;
		lat = (Number(b.minlat) + Number(b.maxlat)) / 2;
		lon = (Number(b.minlon) + Number(b.maxlon)) / 2;
	}
	else {
		return null;
	}

	if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) {
		return null;
	}

	return [lat, lon];
}

function hnsDefaultPoiColour() {
	return getComputedStyle(document.documentElement).getPropertyValue("--imported").trim();
}

function hnsFillUnsetColours() {
	for (const input of document.querySelectorAll(".hnsColourInput")) {
		if (!input.getAttribute("value")) {
			input.value = hnsDefaultPoiColour();
		}
	}
}

function hnsPrefillFromCategory(form) {
	const typed = form.elements.category.value.trim().toLowerCase();
	const match = Array.from(document.querySelectorAll("#poiCategoryNames option"))
		.find(option => option.value.toLowerCase() === typed);

	if (match) {
		form.elements.colour.value = match.dataset.colour || hnsDefaultPoiColour();
		form.elements.icon.value = match.dataset.icon;
	}
}

function hnsInitPoiImport() {
	hnsFillUnsetColours();

	const form = document.getElementById("poiImportForm");

	if (!form) {
		return;
	}

	form.elements.category.addEventListener("change", () => hnsPrefillFromCategory(form));

	const status = document.getElementById("poiImportStatus");
	const submit = form.querySelector('button[type="submit"]');

	function report(text, isError) {
		status.textContent = text;
		status.classList.toggle("formError", isError);
		status.hidden = text === "";
	}

	form.addEventListener("submit", event => {
		event.preventDefault();

		const mapId = form.dataset.mapId;
		const name = form.elements.category.value.trim();
		const data = form.elements.data.value;

		if (data.trim() === "") {
			report(t("poiImport.error.noData"), true);
			return;
		}

		const parsed = hnsParsePaste(data);

		if (parsed === null) {
			report(t("poiImport.error.unreadable"), true);
			return;
		}

		let categories;

		if (parsed.kind === "export") {
			categories = parsed.categories;
		}
		else {
			if (name === "") {
				report(t("poiImport.error.noCategory"), true);
				return;
			}

			categories = [{
				name,
				colour: form.elements.colour.value,
				icon: form.elements.icon.value.trim(),
				pois: parsed.pois,
			}];
		}

		if (!categories.some(category => Array.isArray(category.pois) && category.pois.length)) {
			report(t("poiImport.error.nothingFound"), true);
			return;
		}

		report(t("status.submitting"), false);
		submit.disabled = true;

		postJson("/hideandseek/ajax/import-pois", { mapId, categories })
			.then(result => {
				const query = new URLSearchParams({
					map: mapId,
					imported: result.imported,
					skipped: parsed.kind === "overpass" ? parsed.skipped : 0,
				});

				if (result.categories.length === 1) {
					query.set("category", result.categories[0].id);
				}
				else {
					query.set("categories", result.categories.length);
				}

				window.location.href = "/hideandseek/import-pois?" + query;
			})
			.catch(error => {
				report(t("status.submitFailed", { error: error.message }), true);
				submit.disabled = false;
			});
	});
}

hnsInitPoiImport();
hnsInitPoiExport();
