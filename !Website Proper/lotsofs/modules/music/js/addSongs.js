const data_artistNames = JSON.parse(document.getElementById("artistNamesData").textContent);

const ARTIST_OPTION_SKIP = "skip";
const ARTIST_OPTION_NEW = "new";
const ARTIST_OPTION_CUSTOM = "custom";
const ARTIST_OPTION_PENDING_PREFIX = "pending:";

const pasteInput = document.getElementById("pasteInput");
const statusMessage = document.getElementById("statusMessage");
const artistMatchTable = document.getElementById("artistMatchTable");
const artistMatchRows = document.getElementById("artistMatchRows");
const submitButton = document.getElementById("submitButton");
const songTable = document.getElementById("songTable");
const songRows = document.getElementById("songRows");
const submitSongsButton = document.getElementById("submitSongsButton");

let pastedRows = [];
let artistIdByProvidedName = new Map();

function showRowResult(row, result) {
	row.querySelector(".resultCell").textContent = result.message || "";
}

function flagUnreportedRows(container) {
	container.querySelectorAll("tr").forEach(row => {
		const cell = row.querySelector(".resultCell");
		if (cell && cell.textContent.trim() === "") {
			cell.textContent = t("status.noResult");
		}
	});
}

function buildArtistTable(data_userInput) {
	if (!data_userInput) return;

	const uniqueArtists = [];
	data_userInput.forEach(item => {
		const artist = (item.Artist || item.artist || '').trim();
		if (artist && !uniqueArtists.includes(artist)) {
			uniqueArtists.push(artist);
		}
	});

	const artistOptionLabels = new Map();
	data_artistNames.forEach(a => {
		if (a.is_actual || !artistOptionLabels.has(a.artist_id)) {
			artistOptionLabels.set(a.artist_id, a.name);
		}
	});

	uniqueArtists.forEach(artist => {
		const tr_Exists = artistMatchRows.querySelector(`tr[data_artist="${CSS.escape(artist)}"]`);
		if (tr_Exists) {
			return;
		}
		const tr_Element = appendChildToElement(artistMatchRows, "tr");
		tr_Element.setAttribute('data_artist', artist);

		const nameCell_Element = appendChildToElement(tr_Element, "td", "");
		nameCell_Element.classList.add("providedNameCell");
		nameCell_Element.title = artist;
		const nameText_Element = appendChildToElement(nameCell_Element, "span", artist);
		nameText_Element.classList.add("providedName");

		const selectCell_Element = appendChildToElement(tr_Element, "td", "");
		selectCell_Element.classList.add("artistSelectCell");
		const dropDown_Element = appendChildToElement(selectCell_Element, "select");

		const extrasCell_Element = appendChildToElement(tr_Element, "td", "");
		extrasCell_Element.classList.add("extrasCell");
		const extras_Element = appendChildToElement(extrasCell_Element, "span");
		extras_Element.classList.add("rowExtras");
		const rawNamePreview_Element = appendChildToElement(extras_Element, "span", artist);
		rawNamePreview_Element.classList.add("rawNamePreview");
		const textInput_Element = appendChildToElement(extras_Element, "input");
		textInput_Element.type = "text";
		const keepRawAliasLabel_Element = appendChildToElement(extras_Element, "label");
		const keepRawAliasCheckBox_Element = appendChildToElement(keepRawAliasLabel_Element, "input");
		keepRawAliasCheckBox_Element.type = "checkbox";
		keepRawAliasCheckBox_Element.checked = true;
		const [aliasLabelBefore, aliasLabelAfter] = t("artist.alsoStoreAlias").split("{name}");
		appendChildToElement(keepRawAliasLabel_Element, "span", aliasLabelBefore);
		const keepRawAliasName_Element = appendChildToElement(keepRawAliasLabel_Element, "span", `"${artist}"`);
		keepRawAliasName_Element.classList.add("aliasNameInLabel");
		appendChildToElement(keepRawAliasLabel_Element, "span", aliasLabelAfter || "");
		keepRawAliasLabel_Element.title = t("artist.alsoStoreTooltip", { name: artist });

		const resultCell = appendChildToElement(tr_Element, "td", "");
		resultCell.classList.add("resultCell");

		const optionNew = appendChildToElement(dropDown_Element, "option", t("artist.option.new"));
		optionNew.value = ARTIST_OPTION_NEW;
		const optionCustom = appendChildToElement(dropDown_Element, "option", t("artist.option.custom"));
		optionCustom.value = ARTIST_OPTION_CUSTOM;
		const optionSkip = appendChildToElement(dropDown_Element, "option", t("artist.option.skip"));
		optionSkip.value = ARTIST_OPTION_SKIP;
		artistOptionLabels.forEach((name, artistId) => {
			const optionArtist = appendChildToElement(dropDown_Element, "option", name);
			optionArtist.value = artistId;
		});

		const matchedAlias = data_artistNames.find(a => a.name == artist);
		if (matchedAlias) {
			dropDown_Element.value = matchedAlias.artist_id;
		}

		dropDown_Element.addEventListener('change', refreshAllRows);
		keepRawAliasCheckBox_Element.addEventListener('change', refreshAllRows);
		textInput_Element.addEventListener('input', refreshAllRows);
	});

	refreshAllRows();
}

function refreshAllRows() {
	refreshPendingArtistOptions();
	artistMatchRows.querySelectorAll("tr[data_artist]").forEach(row => {
		syncRowInputToSelection(
			row.querySelector("select"),
			row.querySelector("input[type='text']"),
			row.querySelector("label"),
			row.querySelector(".rawNamePreview"),
			row.getAttribute("data_artist"));
	});
	syncExtrasColumnVisibility();
	refreshRowPreviews();
}

function refreshPendingArtistOptions() {
	const rows = Array.from(artistMatchRows.querySelectorAll("tr[data_artist]"));

	const pendingArtists = [];
	rows.forEach(row => {
		const select = row.querySelector("select");
		if (select.value !== ARTIST_OPTION_NEW && select.value !== ARTIST_OPTION_CUSTOM) {
			return;
		}
		const providedName = row.getAttribute("data_artist");
		const typedName = row.querySelector("input[type='text']").value.trim();
		pendingArtists.push({
			ownerName: providedName,
			label: select.value === ARTIST_OPTION_CUSTOM && typedName ? typedName : providedName,
		});
	});

	rows.forEach(row => {
		const select = row.querySelector("select");
		const previousValue = select.value;
		select.querySelectorAll("option.pendingArtistOption").forEach(option => option.remove());

		pendingArtists.forEach(pending => {
			if (pending.ownerName === row.getAttribute("data_artist")) {
				return;
			}
			const option = appendChildToElement(select, "option", pending.label);
			option.value = ARTIST_OPTION_PENDING_PREFIX + encodeURIComponent(pending.ownerName);
			option.classList.add("pendingArtistOption");
		});

		select.value = previousValue;
		if (!select.value) {
			select.value = ARTIST_OPTION_NEW;
		}
	});
}

function rowExtrasFor(selectValue, providedName) {
	if (selectValue === ARTIST_OPTION_CUSTOM) {
		return { typedName: true, rawName: false, keepRawAlias: true };
	}
	if (selectValue === ARTIST_OPTION_NEW) {
		return { typedName: false, rawName: true, keepRawAlias: false };
	}
	if (selectValue === ARTIST_OPTION_SKIP) {
		return { typedName: false, rawName: false, keepRawAlias: false };
	}
	if (selectValue.startsWith(ARTIST_OPTION_PENDING_PREFIX)) {
		return { typedName: false, rawName: false, keepRawAlias: true };
	}
	const alreadyAnAlias = data_artistNames.some(a => a.name == providedName && a.artist_id == selectValue);
	return { typedName: false, rawName: false, keepRawAlias: !alreadyAnAlias };
}

function syncRowInputToSelection(select, input, keepRawAliasLabel, rawNamePreview, providedName) {
	const extras = rowExtrasFor(select.value, providedName);
	input.hidden = !extras.typedName;
	rawNamePreview.hidden = !extras.rawName;
	keepRawAliasLabel.hidden = !extras.keepRawAlias;
	if (extras.typedName) {
		input.placeholder = providedName;
	}
	else {
		input.value = "";
	}
}

function refreshRowPreviews() {
	artistMatchRows.querySelectorAll("tr[data_artist]:not(.rowHandled)").forEach(row => {
		row.querySelector(".resultCell").textContent = previewResultFor(row);
	});
}

function previewResultFor(row) {
	const providedName = row.getAttribute("data_artist");
	const selectValue = row.querySelector("select").value;

	if (selectValue === ARTIST_OPTION_SKIP) {
		return t("artist.preview.skipped");
	}
	if (selectValue === ARTIST_OPTION_NEW || selectValue === ARTIST_OPTION_CUSTOM) {
		return t("artist.preview.createsNew");
	}
	if (selectValue.startsWith(ARTIST_OPTION_PENDING_PREFIX)) {
		return row.querySelector("input[type='checkbox']").checked
			? t("artist.preview.joinsNew")
			: t("artist.preview.nothingToStore");
	}
	if (data_artistNames.some(a => a.name == providedName && a.artist_id == selectValue)) {
		return t("artist.preview.found");
	}
	return row.querySelector("input[type='checkbox']").checked
		? t("artist.preview.addsAlias")
		: t("artist.preview.nothingToStore");
}

function syncExtrasColumnVisibility() {
	const rows = artistMatchRows.querySelectorAll("tr[data_artist]");
	const anyRowNeedsExtras = Array.from(rows).some(row => {
		const extras = rowExtrasFor(row.querySelector("select").value, row.getAttribute("data_artist"));
		return extras.typedName || extras.rawName || extras.keepRawAlias;
	});
	artistMatchTable.classList.toggle("hideExtrasColumn", !anyRowNeedsExtras);
}

function parsePastedTsv() {
	const text = pasteInput.value;
	artistMatchRows.innerHTML = "";
	statusMessage.innerHTML = "";
	hideSongTable();
	if (!text.trim()) {
		return;
	}
	const data = [];
	text.split(/\r?\n/).forEach(line => {
		const fields = line.split("\t");
		const artist = fields[0].trim();
		if (!artist) {
			return;
		}
		const trackRaw = (fields[3] || "").trim();
		data.push({
			Artist: artist,
			Title: (fields[1] || "").trim(),
			Album: (fields[2] || "").trim(),
			Track: /^\d+$/.test(trackRaw) ? Number(trackRaw) : null,
		});
	});
	pastedRows = data;
	buildArtistTable(data);
}
pasteInput.addEventListener('input', parsePastedTsv);

function resolveRowTarget(row, depth = 0) {
	const select = row.querySelector("select");
	const value = select.value;
	if (depth > 20) {
		return { kind: 'skip' };
	}
	if (value === ARTIST_OPTION_SKIP) {
		return { kind: 'skip' };
	}
	if (value === ARTIST_OPTION_NEW || value === ARTIST_OPTION_CUSTOM) {
		return { kind: 'new', groupKey: row.getAttribute('data_artist') };
	}
	if (value.startsWith(ARTIST_OPTION_PENDING_PREFIX)) {
		const targetName = decodeURIComponent(value.slice(ARTIST_OPTION_PENDING_PREFIX.length));
		const targetRow = artistMatchRows.querySelector(`tr[data_artist="${CSS.escape(targetName)}"]`);
		if (!targetRow) {
			return { kind: 'skip' };
		}
		return resolveRowTarget(targetRow, depth + 1);
	}
	return { kind: 'existing', artistId: value };
}

submitButton.addEventListener('click', () => {
	const newArtists = [];

	const rows = artistMatchRows.querySelectorAll("tr[data_artist]:not(.rowHandled)");
	rows.forEach(row => {
		const artist = row.getAttribute("data_artist");
		const select = row.querySelector("select");
		const input = row.querySelector("input[type='text']");
		const keepRawAliasCheckbox = row.querySelector("input[type='checkbox']");
		const target = resolveRowTarget(row);

		if (target.kind === 'skip') {
			return;
		}

		const createsArtist = select.value === ARTIST_OPTION_NEW || select.value === ARTIST_OPTION_CUSTOM;

		const payload = {
			provided_name: artist,
			og_name: input.value.trim() || artist,
			is_actual: createsArtist,
		};

		if (!createsArtist && !keepRawAliasCheckbox.checked) {
			payload.store_name = false;
		}
		if (target.kind === 'existing') {
			payload.artist_id = target.artistId;
		}
		else {
			payload.artist_id = ARTIST_OPTION_NEW;
			payload.group = target.groupKey;
		}

		if (select.value === ARTIST_OPTION_CUSTOM && keepRawAliasCheckbox.checked) {
			payload.also_alias_provided_name = true;
		}
		newArtists.push(payload);
	});
	if (newArtists.length === 0) {
		return;
	}
	statusMessage.innerHTML = t("status.submitting");
	fetch("/modules/music/ajax/artistAlias.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify(newArtists)
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(results => {
		statusMessage.innerHTML = "";
		results.forEach(result => {
			const row = artistMatchRows.querySelector(`tr[data_artist="${CSS.escape(result.provided_name)}"]`);
			if (!row) return;
			showRowResult(row, result);
			if (result.status === "ok" || result.status === "duplicate") {
				row.classList.add("rowHandled");
				row.querySelectorAll("select, input").forEach(el => el.disabled = true);
			}
		});
		buildSongTable(results);
	})
	.catch(error => {
		statusMessage.innerHTML = t("status.submitFailed", { error: error });
	});
});

function hideSongTable() {
	songRows.innerHTML = "";
	songTable.hidden = true;
	submitSongsButton.hidden = true;
	hideAlbumTable();
}

const SONG_OPTION_SKIP = "skip";
const SONG_OPTION_NEW = "new";
const SONG_OPTION_CUSTOM = "custom";

let songsByArtistId = new Map();
let artistNameById = new Map();

function songExtrasFor(selectValue, providedName, artistId) {
	if (selectValue === SONG_OPTION_CUSTOM) {
		return { typedName: true, rawName: false, keepRawAlias: true };
	}
	if (selectValue === SONG_OPTION_NEW) {
		return { typedName: false, rawName: true, keepRawAlias: false };
	}
	if (selectValue === SONG_OPTION_SKIP) {
		return { typedName: false, rawName: false, keepRawAlias: false };
	}
	const alreadyAnAlias = (songsByArtistId.get(artistId) || []).some(song => song.name === providedName && song.id == selectValue);
	return { typedName: false, rawName: false, keepRawAlias: !alreadyAnAlias };
}

function syncSongRow(row) {
	const extras = songExtrasFor(
		row.querySelector(".songSelectCell select").value,
		row.getAttribute("data_title"),
		Number(row.getAttribute("data_artist_id")));

	row.querySelector(".extrasCell input[type='text']").hidden = !extras.typedName;
	row.querySelector(".extrasCell .rawNamePreview").hidden = !extras.rawName;
	row.querySelector(".extrasCell label").hidden = !extras.keepRawAlias;
}

function buildSongTable(artistResults) {
	artistIdByProvidedName = new Map();
	songsByArtistId = new Map();
	artistNameById = new Map();
	artistResults.forEach(result => {
		if (result.artist_id) {
			artistIdByProvidedName.set(result.provided_name, result.artist_id);
			songsByArtistId.set(result.artist_id, result.songs || []);
			if (result.artist_name) {
				artistNameById.set(result.artist_id, result.artist_name);
			}
		}
	});

	songRows.innerHTML = "";
	const seen = new Set();

	pastedRows.forEach(item => {
		const artistId = artistIdByProvidedName.get(item.Artist);
		if (!artistId || !item.Title) {
			return;
		}
		const key = artistId + "\t" + item.Title;
		if (seen.has(key)) {
			return;
		}
		seen.add(key);

		const row = appendChildToElement(songRows, "tr");
		row.setAttribute("data_artist_id", artistId);
		row.setAttribute("data_title", item.Title);

		appendChildToElement(row, "td", artistDisplayName(artistId, item.Artist));
		appendChildToElement(row, "td", item.Title);

		const selectCell = appendChildToElement(row, "td", "");
		selectCell.classList.add("songSelectCell");
		const select = appendChildToElement(selectCell, "select");

		const extrasCell = appendChildToElement(row, "td", "");
		extrasCell.classList.add("extrasCell");
		const extras = appendChildToElement(extrasCell, "span");
		extras.classList.add("rowExtras");
		appendChildToElement(extras, "span", item.Title).classList.add("rawNamePreview");
		const textInput = appendChildToElement(extras, "input");
		textInput.type = "text";
		textInput.placeholder = item.Title;
		const keepLabel = appendChildToElement(extras, "label");
		const keepBox = appendChildToElement(keepLabel, "input");
		keepBox.type = "checkbox";
		keepBox.checked = true;
		const [labelBefore, labelAfter] = t("artist.alsoStoreAlias").split("{name}");
		appendChildToElement(keepLabel, "span", labelBefore);
		appendChildToElement(keepLabel, "span", `"${item.Title}"`).classList.add("aliasNameInLabel");
		appendChildToElement(keepLabel, "span", labelAfter || "");

		const resultCell = appendChildToElement(row, "td", "");
		resultCell.classList.add("resultCell");

		appendChildToElement(select, "option", t("artist.option.new")).value = SONG_OPTION_NEW;
		appendChildToElement(select, "option", t("artist.option.custom")).value = SONG_OPTION_CUSTOM;
		appendChildToElement(select, "option", t("artist.option.skip")).value = SONG_OPTION_SKIP;

		const aliases = songsByArtistId.get(artistId) || [];

		const songLabels = new Map();
		aliases.forEach(song => {
			if (song.is_actual || !songLabels.has(song.id)) {
				songLabels.set(song.id, song.name);
			}
		});
		songLabels.forEach((name, songId) => {
			appendChildToElement(select, "option", name).value = songId;
		});

		const matchedAlias = aliases.find(song => song.name === item.Title);
		if (matchedAlias) {
			select.value = matchedAlias.id;
		}

		select.addEventListener("change", () => syncSongRow(row));
		syncSongRow(row);
	});

	const hasSongs = songRows.children.length > 0;
	songTable.hidden = !hasSongs;
	submitSongsButton.hidden = !hasSongs;
}

function artistDisplayName(artistId, providedName) {
	if (artistNameById.has(artistId)) {
		return artistNameById.get(artistId);
	}
	const known = data_artistNames.find(a => a.artist_id == artistId && a.is_actual);
	return known ? known.name : providedName;
}

submitSongsButton.addEventListener('click', () => {
	const songs = [];
	songRows.querySelectorAll("tr:not(.rowHandled)").forEach(row => {
		const select = row.querySelector(".songSelectCell select");
		const typedName = row.querySelector(".extrasCell input[type='text']").value.trim();

		songs.push({
			artist_id: row.getAttribute("data_artist_id"),
			title: row.getAttribute("data_title"),
			song_id: select.value,
			og_name: select.value === SONG_OPTION_CUSTOM ? typedName : "",
			also_alias_provided_name: row.querySelector(".extrasCell input[type='checkbox']").checked,
		});
	});
	if (songs.length === 0) {
		return;
	}
	statusMessage.innerHTML = t("status.submitting");
	fetch("/modules/music/ajax/song.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify(songs)
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(results => {
		statusMessage.innerHTML = "";
		results.forEach(result => {
			songRows.querySelectorAll("tr").forEach(row => {
				if (row.getAttribute("data_artist_id") != result.artist_id || row.getAttribute("data_title") !== result.provided_name) {
					return;
				}
				showRowResult(row, result);
				if (result.status === "ok" || result.status === "duplicate") {
					row.classList.add("rowHandled");
				}
			});
		});
		flagUnreportedRows(songRows);
		try {
			buildAlbumTable(results);
		}
		catch (error) {
			statusMessage.innerHTML = t("status.albumsFailed", { error: error.message });
			throw error;
		}
	})
	.catch(error => {
		if (statusMessage.innerHTML === "") {
			statusMessage.innerHTML = t("status.submitFailed", { error: error });
		}
	});
});

const data_albumNames = JSON.parse(document.getElementById("albumNamesData").textContent);

const ALBUM_OPTION_SKIP = "skip";
const ALBUM_OPTION_NEW = "new";
const ALBUM_OPTION_CUSTOM = "custom";

const albumTable = document.getElementById("albumTable");
const albumRows = document.getElementById("albumRows");
const submitAlbumsButton = document.getElementById("submitAlbumsButton");
const albumScrollSpace = document.getElementById("albumScrollSpace");

function hideAlbumTable() {
	albumRows.innerHTML = "";
	albumTable.hidden = true;
	submitAlbumsButton.hidden = true;
	albumScrollSpace.hidden = true;
}

function assignTrackPositions(tracks) {
	const claimed = new Set();
	tracks.forEach(track => {
		if (track.explicit !== null) {
			claimed.add(track.explicit);
		}
	});

	let next = 1;
	tracks.forEach(track => {
		if (track.explicit !== null) {
			track.position = track.explicit;
			return;
		}
		while (claimed.has(next)) {
			next++;
		}
		track.position = next;
		claimed.add(next);
	});
}

function collectAlbums(songResults) {
	const songIdByKey = new Map();
	songResults.forEach(result => {
		if (result.song_id) {
			songIdByKey.set(result.artist_id + "\t" + result.title, result.song_id);
		}
	});

	const albums = new Map();
	pastedRows.forEach(item => {
		if (!item.Album) {
			return;
		}
		const artistId = artistIdByProvidedName.get(item.Artist);
		const songId = artistId ? songIdByKey.get(artistId + "\t" + item.Title) : null;
		if (!songId) {
			return;
		}
		if (!albums.has(item.Album)) {
			albums.set(item.Album, []);
		}
		albums.get(item.Album).push({ songId, artistId, title: item.Title, explicit: item.Track });
	});

	albums.forEach(assignTrackPositions);
	return albums;
}

function albumExtrasFor(selectValue, providedName) {
	if (selectValue === ALBUM_OPTION_CUSTOM) {
		return { typedName: true, rawName: false, keepRawAlias: true };
	}
	if (selectValue === ALBUM_OPTION_NEW) {
		return { typedName: false, rawName: true, keepRawAlias: false };
	}
	if (selectValue === ALBUM_OPTION_SKIP) {
		return { typedName: false, rawName: false, keepRawAlias: false };
	}
	const alreadyAnAlias = data_albumNames.some(a => a.name == providedName && a.album_id == selectValue);
	return { typedName: false, rawName: false, keepRawAlias: !alreadyAnAlias };
}

function syncAlbumRow(row) {
	const providedName = row.getAttribute("data_album");
	const extras = albumExtrasFor(row.querySelector(".albumSelectCell select").value, providedName);

	row.querySelector(".extrasCell input[type='text']").hidden = !extras.typedName;
	row.querySelector(".extrasCell .rawNamePreview").hidden = !extras.rawName;
	row.querySelector(".extrasCell label").hidden = !extras.keepRawAlias;
}

function buildAlbumTable(songResults) {
	albumRows.innerHTML = "";

	const albums = collectAlbums(songResults);

	const albumOptionLabels = new Map();
	data_albumNames.forEach(a => {
		if (a.is_actual || !albumOptionLabels.has(a.album_id)) {
			albumOptionLabels.set(a.album_id, a.artist_name ? `${a.name} - ${a.artist_name}` : a.name);
		}
	});

	const providedNameByArtistId = new Map();
	artistIdByProvidedName.forEach((id, name) => {
		if (!providedNameByArtistId.has(id)) {
			providedNameByArtistId.set(id, name);
		}
	});

	albums.forEach((tracks, albumName) => {
		const row = appendChildToElement(albumRows, "tr");
		row.setAttribute("data_album", albumName);
		row.setAttribute("data_tracks", JSON.stringify(tracks.map(track => ({ song_id: track.songId, position: track.position }))));

		const nameCell = appendChildToElement(row, "td", "");
		nameCell.classList.add("providedNameCell");
		nameCell.title = albumName;
		appendChildToElement(nameCell, "span", albumName).classList.add("providedName");

		const selectCell = appendChildToElement(row, "td", "");
		selectCell.classList.add("albumSelectCell");
		const select = appendChildToElement(selectCell, "select");

		const extrasCell = appendChildToElement(row, "td", "");
		extrasCell.classList.add("extrasCell");
		const extras = appendChildToElement(extrasCell, "span");
		extras.classList.add("rowExtras");
		appendChildToElement(extras, "span", albumName).classList.add("rawNamePreview");
		const textInput = appendChildToElement(extras, "input");
		textInput.type = "text";
		textInput.placeholder = albumName;
		const keepLabel = appendChildToElement(extras, "label");
		const keepBox = appendChildToElement(keepLabel, "input");
		keepBox.type = "checkbox";
		keepBox.checked = true;
		const [labelBefore, labelAfter] = t("artist.alsoStoreAlias").split("{name}");
		appendChildToElement(keepLabel, "span", labelBefore);
		appendChildToElement(keepLabel, "span", `"${albumName}"`).classList.add("aliasNameInLabel");
		appendChildToElement(keepLabel, "span", labelAfter || "");

		const artistCell = appendChildToElement(row, "td", "");
		artistCell.classList.add("albumArtistCell");
		const artistSelect = appendChildToElement(artistCell, "select");

		const yearCell = appendChildToElement(row, "td", "");
		yearCell.classList.add("albumYearCell");
		const yearInput = appendChildToElement(yearCell, "input");
		yearInput.type = "text";

		const positions = tracks.map(track => track.position).sort((a, b) => a - b);
		const tracksCell = appendChildToElement(row, "td", positions.length === 1
			? t("album.trackSummaryOne", { first: positions[0] })
			: t("album.trackSummary", { count: positions.length, first: positions[0], last: positions[positions.length - 1] }));
		tracksCell.classList.add("albumTracksCell");
		tracksCell.title = tracks
			.slice()
			.sort((a, b) => a.position - b.position)
			.map(track => `${track.position} ${track.title}`)
			.join("\n");

		appendChildToElement(row, "td", "").classList.add("resultCell");

		const optionNew = appendChildToElement(select, "option", t("artist.option.new"));
		optionNew.value = ALBUM_OPTION_NEW;
		const optionCustom = appendChildToElement(select, "option", t("artist.option.custom"));
		optionCustom.value = ALBUM_OPTION_CUSTOM;
		const optionSkip = appendChildToElement(select, "option", t("artist.option.skip"));
		optionSkip.value = ALBUM_OPTION_SKIP;
		albumOptionLabels.forEach((label, albumId) => {
			appendChildToElement(select, "option", label).value = albumId;
		});

		const matchedAlias = data_albumNames.find(a => a.name == albumName);
		if (matchedAlias) {
			select.value = matchedAlias.album_id;
		}

		const counts = new Map();
		tracks.forEach(track => counts.set(track.artistId, (counts.get(track.artistId) || 0) + 1));
		Array.from(counts.entries())
			.sort((a, b) => b[1] - a[1])
			.forEach(entry => {
				appendChildToElement(artistSelect, "option", artistDisplayName(entry[0], providedNameByArtistId.get(entry[0]) || "")).value = entry[0];
			});
		appendChildToElement(artistSelect, "option", t("album.option.none")).value = "";

		select.addEventListener("change", () => syncAlbumRow(row));
		syncAlbumRow(row);
	});

	const hasAlbums = albumRows.children.length > 0;
	albumTable.hidden = !hasAlbums;
	submitAlbumsButton.hidden = !hasAlbums;
	albumScrollSpace.hidden = !hasAlbums;
}

submitAlbumsButton.addEventListener('click', () => {
	const albums = [];

	albumRows.querySelectorAll("tr:not(.rowHandled)").forEach(row => {
		const select = row.querySelector(".albumSelectCell select");
		if (select.value === ALBUM_OPTION_SKIP) {
			return;
		}

		const isNew = select.value === ALBUM_OPTION_NEW || select.value === ALBUM_OPTION_CUSTOM;
		const typedName = row.querySelector(".extrasCell input[type='text']").value.trim();

		albums.push({
			provided_name: row.getAttribute("data_album"),
			album_id: select.value,
			og_name: select.value === ALBUM_OPTION_CUSTOM ? typedName : "",
			is_actual: isNew,
			also_alias_provided_name: row.querySelector(".extrasCell input[type='checkbox']").checked,
			artist_id: row.querySelector(".albumArtistCell select").value || null,
			release_year: row.querySelector(".albumYearCell input").value.trim(),
			tracks: JSON.parse(row.getAttribute("data_tracks")),
		});
	});

	if (albums.length === 0) {
		return;
	}

	statusMessage.innerHTML = t("status.submitting");
	fetch("/modules/music/ajax/album.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify(albums)
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(results => {
		statusMessage.innerHTML = "";
		results.forEach(result => {
			const row = albumRows.querySelector(`tr[data_album="${CSS.escape(result.provided_name)}"]`);
			if (!row) {
				return;
			}
			showRowResult(row, result);
			if (result.status === "ok") {
				row.classList.add("rowHandled");
			}
		});
		flagUnreportedRows(albumRows);
	})
	.catch(error => {
		statusMessage.innerHTML = t("status.submitFailed", { error: error });
	});
});
