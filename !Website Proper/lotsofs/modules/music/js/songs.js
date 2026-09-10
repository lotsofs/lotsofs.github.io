const songListTable = document.getElementById("songListTable");
const songListBody = songListTable.querySelector("tbody");
const songListHeaders = Array.from(songListTable.querySelectorAll("th[data-sort-key]"));

let songSort = new URLSearchParams(location.search).get("sort") || "id";
let songDir = new URLSearchParams(location.search).get("dir") === "desc" ? "desc" : "asc";

const songArtistSelect = document.getElementById("filterArtist");
const songAlbumSelect = document.getElementById("filterAlbum");
const songNoMatch = document.getElementById("songNoMatch");
const songListHeading = document.getElementById("songListHeading");
const songAlbumData = JSON.parse(document.getElementById("songAlbumData").textContent);
const songTrackAliases = JSON.parse(document.getElementById("songTrackAliases").textContent);

function songQuery(sort, dir) {
	const params = new URLSearchParams();
	params.set("sort", sort);
	params.set("dir", dir);
	if (songArtistSelect.value) {
		params.set("artist", songArtistSelect.value);
	}
	if (songAlbumSelect.value) {
		params.set("album", songAlbumSelect.value);
	}
	return "?" + params.toString();
}

function applySongFilter() {
	const artist = songArtistSelect.value;
	const album = songAlbumSelect.value;
	let visible = 0;

	Array.from(songListBody.rows).forEach(row => {
		let show = true;
		if (album) {
			show = (row.dataset.albumIds || "").split(",").includes(album);
		}
		else if (artist) {
			show = row.dataset.artistId === artist;
		}
		row.hidden = !show;
		if (show) {
			visible++;
		}
	});

	songNoMatch.hidden = visible > 0;
}

function repopulateSongAlbums() {
	const previous = songAlbumSelect.value;
	songAlbumSelect.length = 1;

	songAlbumData
		.filter(album => songArtistSelect.value
			? String(album.artist_id) === songArtistSelect.value
			: album.artist_id === null)
		.forEach(album => songAlbumSelect.add(new Option(album.name === null ? t("album.list.noName") : album.name, album.id)));

	songAlbumSelect.value = Array.from(songAlbumSelect.options).some(option => option.value === previous) ? previous : "";
}

function applySongTitles() {
	const album = songAlbumSelect.value;

	Array.from(songListBody.rows).forEach(row => {
		const cell = row.querySelector(".songTitleCell");
		if (!cell || cell.querySelector("input")) {
			return;
		}

		const songId = row.cells[0].textContent.trim();
		const listing = album
			? songTrackAliases.find(entry => String(entry.album_id) === album && String(entry.song_id) === songId)
			: null;

		cell.textContent = listing ? listing.name : cell.dataset.canonicalTitle;
		cell.classList.toggle("songTitleAliased", listing !== null && listing !== undefined);
	});
}

function songOptionLabel(select, value) {
	const option = Array.from(select.options).find(candidate => candidate.value === value);
	return option ? option.textContent : "";
}

function refreshSongHeading() {
	if (songAlbumSelect.value) {
		const album = songAlbumData.find(entry => String(entry.id) === songAlbumSelect.value);
		const albumName = songOptionLabel(songAlbumSelect, songAlbumSelect.value);

		songListHeading.textContent = album && album.artist_id !== null
			? t("song.list.headingAlbumBy", { album: albumName, artist: songOptionLabel(songArtistSelect, String(album.artist_id)) })
			: albumName;
		return;
	}

	songListHeading.textContent = songArtistSelect.value
		? songOptionLabel(songArtistSelect, songArtistSelect.value)
		: t("song.list.heading");
}

songArtistSelect.addEventListener("change", () => {
	repopulateSongAlbums();
	applySongFilter();
	applySongTitles();
	refreshSongHeading();
	history.replaceState(null, "", songQuery(songSort, songDir));
});

songAlbumSelect.addEventListener("change", () => {
	applySongFilter();
	applySongTitles();
	refreshSongHeading();
	history.replaceState(null, "", songQuery(songSort, songDir));
});

function compareCells(a, b, type) {
	if (a === "" || b === "") {
		return a === b ? 0 : (a === "" ? -1 : 1);
	}
	if (type === "number") {
		return Number(a) - Number(b);
	}
	const x = a.toLowerCase();
	const y = b.toLowerCase();
	return x < y ? -1 : x > y ? 1 : 0;
}

function cellText(row, index) {
	return row.cells[index].textContent.trim();
}

function sortRows(index, type) {
	const rows = Array.from(songListBody.rows);
	const flip = songDir === "desc" ? -1 : 1;

	rows.sort((rowA, rowB) => {
		const result = compareCells(cellText(rowA, index), cellText(rowB, index), type) * flip;
		return result !== 0 ? result : Number(cellText(rowA, 0)) - Number(cellText(rowB, 0));
	});

	const fragment = document.createDocumentFragment();
	rows.forEach(row => fragment.appendChild(row));
	songListBody.appendChild(fragment);
}

function refreshHeaders() {
	songListHeaders.forEach(header => {
		const link = header.querySelector("a");
		const key = header.dataset.sortKey;
		const isActive = key === songSort;
		const nextDir = isActive && songDir === "asc" ? "desc" : "asc";

		link.textContent = link.dataset.baseLabel + (isActive ? (songDir === "asc" ? " ▲" : " ▼") : "");
		link.href = songQuery(key, nextDir);
		link.title = nextDir === "asc" ? t("song.list.sortAscending") : t("song.list.sortDescending");
	});
}

songListHeaders.forEach(header => {
	const link = header.querySelector("a");
	link.dataset.baseLabel = link.textContent.replace(/[\s▲▼]+$/, "");

	link.addEventListener("click", event => {
		event.preventDefault();

		const key = header.dataset.sortKey;
		songDir = key === songSort && songDir === "asc" ? "desc" : "asc";
		songSort = key;

		sortRows(Number(header.dataset.sortIndex), header.dataset.sortType);
		refreshHeaders();
		history.replaceState(null, "", songQuery(songSort, songDir));
	});
});

const SONG_EDIT_ENDPOINT = "/modules/music/ajax/songEdit.php";
const RATING_ENDPOINT = "/modules/music/ajax/songRating.php";

const EDITABLE_CELLS = {
	songMyScoreCell: { field: "score", endpoint: RATING_ENDPOINT, required: false, needsAdmin: false, doubleClick: false },
	songMyNoteCell: { field: "note", endpoint: RATING_ENDPOINT, required: false, needsAdmin: false, doubleClick: false, wrapClass: "ratingNoteText" },
	songTitleCell: { field: "title", endpoint: SONG_EDIT_ENDPOINT, required: true, needsAdmin: true, doubleClick: true },
	songNoteCell: { field: "note", endpoint: SONG_EDIT_ENDPOINT, required: false, needsAdmin: true, doubleClick: true },
};

function setResult(cell, message) {
	cell.parentElement.querySelector(".songResultCell").textContent = message;

	const anyShown = Array.from(songListBody.querySelectorAll(".songResultCell")).some(resultCell => resultCell.textContent !== "");
	songListTable.classList.toggle("hideResultColumn", !anyShown);
}

function setCellValue(cell, spec, value) {
	if (!spec.wrapClass) {
		cell.textContent = value;
		return;
	}

	cell.textContent = "";
	cell.title = value;

	const text = document.createElement("span");
	text.className = spec.wrapClass;
	text.textContent = value;
	cell.appendChild(text);
}

function beginCellEdit(cell, spec) {
	const original = cell.textContent;
	const input = document.createElement("input");
	input.type = "text";
	input.value = original;

	setResult(cell, "");
	cell.textContent = "";
	cell.removeAttribute("title");
	cell.appendChild(input);
	input.focus();
	input.select();

	let settled = false;

	function finish(commit) {
		if (settled) {
			return;
		}
		settled = true;

		const value = input.value.trim();
		const keep = commit && (value !== "" || !spec.required);

		setCellValue(cell, spec, keep ? value : original);

		if (keep && value !== original) {
			saveCell(cell, spec, original, value);
		}
	}

	input.addEventListener("keydown", event => {
		if (event.key === "Enter") {
			finish(true);
		}
		if (event.key === "Escape") {
			finish(false);
		}
	});
	input.addEventListener("blur", () => finish(true));
}

function saveCell(cell, spec, original, value) {
	const id = cell.parentElement.cells[0].textContent.trim();

	fetch(spec.endpoint, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify({ id: id, field: spec.field, value: value })
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(result => {
		setCellValue(cell, spec, String(result.value));
		if (spec.field === "title") {
			cell.dataset.canonicalTitle = String(result.value);
		}
		setResult(cell, result.status === "ok" ? "" : result.message);
	})
	.catch(error => {
		setCellValue(cell, spec, original);
		setResult(cell, t("status.submitFailed", { error: error.message }));
	});
}

function editableCell(cell) {
	for (const [className, spec] of Object.entries(EDITABLE_CELLS)) {
		if (cell.classList.contains(className)) {
			return spec;
		}
	}
	return null;
}

function handleEdit(event, viaDoubleClick) {
	const cell = event.target.closest("td");
	if (!cell || cell.querySelector("input")) {
		return;
	}

	const spec = editableCell(cell);
	if (!spec || spec.doubleClick !== viaDoubleClick) {
		return;
	}
	if (spec.needsAdmin && !songListTable.dataset.canEdit) {
		return;
	}
	if (cell.classList.contains("songTitleAliased")) {
		return;
	}

	beginCellEdit(cell, spec);
}

songListBody.addEventListener("click", event => handleEdit(event, false));
songListBody.addEventListener("dblclick", event => handleEdit(event, true));
