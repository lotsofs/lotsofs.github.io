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

// one artist row per unique pasted name
function buildArtistTable(data_userInput) {
	if (!data_userInput) return;

	const uniqueArtists = [];
	data_userInput.forEach(item => {
		const artist = escapeHtml(item.Artist || item.artist || '');
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
		// create tablerow
		const tr_Exists = artistMatchRows.querySelector(`tr[data_artist="${CSS.escape(artist)}"]`);
		if (tr_Exists) {
			return;
		}
		const tr_Element = appendChildToElement(artistMatchRows, "tr");
		tr_Element.setAttribute('data_artist', artist);

		// create cell with the provided name
		const nameCell_Element = appendChildToElement(tr_Element, "td", "");
		nameCell_Element.classList.add("providedNameCell");
		nameCell_Element.title = artist;
		const nameText_Element = appendChildToElement(nameCell_Element, "span", artist);
		nameText_Element.classList.add("providedName");

		// create dropdown cell
		const selectCell_Element = appendChildToElement(tr_Element, "td", "");
		selectCell_Element.classList.add("artistSelectCell");
		const dropDown_Element = appendChildToElement(selectCell_Element, "select");

		// create cell for the selected option's extras
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
		const [aliasLabelBefore, aliasLabelAfter] = t("artists.alsoStoreAlias").split("{name}");
		appendChildToElement(keepRawAliasLabel_Element, "span", aliasLabelBefore);
		const keepRawAliasName_Element = appendChildToElement(keepRawAliasLabel_Element, "span", `"${artist}"`);
		keepRawAliasName_Element.classList.add("aliasNameInLabel");
		appendChildToElement(keepRawAliasLabel_Element, "span", aliasLabelAfter || "");
		keepRawAliasLabel_Element.title = t("artists.alsoStoreTooltip", { name: artist });

		// create result cell
		const resultCell = appendChildToElement(tr_Element, "td", "");
		resultCell.classList.add("resultCell");

		// populate dropdown list
		const optionNew = appendChildToElement(dropDown_Element, "option", t("artists.option.new"));
		optionNew.value = ARTIST_OPTION_NEW;
		const optionCustom = appendChildToElement(dropDown_Element, "option", t("artists.option.custom"));
		optionCustom.value = ARTIST_OPTION_CUSTOM;
		const optionSkip = appendChildToElement(dropDown_Element, "option", t("artists.option.skip"));
		optionSkip.value = ARTIST_OPTION_SKIP;
		artistOptionLabels.forEach((name, artistId) => {
			const optionArtist = appendChildToElement(dropDown_Element, "option", name);
			optionArtist.value = artistId;
		});

		// preselect the artist this name is already an alias of
		const matchedAlias = data_artistNames.find(a => a.name == artist);
		if (matchedAlias) {
			dropDown_Element.value = matchedAlias.artist_id;
		}

		// rebuild every row when this one changes
		dropDown_Element.addEventListener('change', refreshAllRows);
		keepRawAliasCheckBox_Element.addEventListener('change', refreshAllRows);
		textInput_Element.addEventListener('input', refreshAllRows);
	});

	refreshAllRows();
}

// rebuild pending options, row cells, column visibility and previews
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

// offer every row the artists that other rows are about to create
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

		// restore the selection
		select.value = previousValue;
		if (!select.value) {
			select.value = ARTIST_OPTION_NEW;
		}
	});
}

// what the "Name To Store" cell shows for a given option
function rowExtrasFor(selectValue, providedName) {
	if (selectValue === ARTIST_OPTION_CUSTOM) {
		return { typedName: true, rawName: false, keepRawAlias: true };
	}
	if (selectValue === ARTIST_OPTION_NEW) {
		return { typedName: false, rawName: true, keepRawAlias: false };
	}
	if (selectValue === ARTIST_OPTION_SKIP || selectValue.startsWith(ARTIST_OPTION_PENDING_PREFIX)) {
		return { typedName: false, rawName: false, keepRawAlias: false };
	}
	const alreadyAnAlias = data_artistNames.some(a => a.name == providedName && a.artist_id == selectValue);
	return { typedName: false, rawName: false, keepRawAlias: !alreadyAnAlias };
}

// show or hide a row's "Name To Store" contents
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

// fill the Result column with what submitting would do
function refreshRowPreviews() {
	artistMatchRows.querySelectorAll("tr[data_artist]:not(.rowHandled)").forEach(row => {
		row.querySelector(".resultCell").textContent = previewResultFor(row);
	});
}

// what this row's selection would do on submit
function previewResultFor(row) {
	const providedName = row.getAttribute("data_artist");
	const selectValue = row.querySelector("select").value;

	if (selectValue === ARTIST_OPTION_SKIP) {
		return t("artists.preview.skipped");
	}
	if (selectValue === ARTIST_OPTION_NEW || selectValue === ARTIST_OPTION_CUSTOM) {
		return t("artists.preview.createsNew");
	}
	if (selectValue.startsWith(ARTIST_OPTION_PENDING_PREFIX)) {
		return t("artists.preview.joinsNew");
	}
	if (data_artistNames.some(a => a.name == providedName && a.artist_id == selectValue)) {
		return t("artists.preview.artistFound");
	}
	return row.querySelector("input[type='checkbox']").checked
		? t("artists.preview.addsAlias")
		: t("artists.preview.nothingToStore");
}

// hide the "Name To Store" column when no row needs it
function syncExtrasColumnVisibility() {
	const rows = artistMatchRows.querySelectorAll("tr[data_artist]");
	const anyRowNeedsExtras = Array.from(rows).some(row => {
		const extras = rowExtrasFor(row.querySelector("select").value, row.getAttribute("data_artist"));
		return extras.typedName || extras.rawName || extras.keepRawAlias;
	});
	artistMatchTable.classList.toggle("hideExtrasColumn", !anyRowNeedsExtras);
}

// one line per track, artist and title separated by a tab
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
		data.push({ Artist: artist, Title: (fields[1] || "").trim() });
	});
	pastedRows = data;
	buildArtistTable(data);
}
pasteInput.addEventListener('input', parsePastedTsv);

// resolve a row's selection to the artist it targets
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

// send the artist rows, then build the song table
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

		// skip rows with nothing left to record
		const extras = rowExtrasFor(select.value, artist);
		if (extras.keepRawAlias && !keepRawAliasCheckbox.checked && select.value !== ARTIST_OPTION_CUSTOM) {
			return;
		}

		const createsArtist = select.value === ARTIST_OPTION_NEW || select.value === ARTIST_OPTION_CUSTOM;

		const payload = {
			provided_name: artist,
			og_name: input.value.trim() || artist,
			is_actual: createsArtist,
		};
		if (target.kind === 'existing') {
			payload.artist_id = target.artistId;
		}
		else {
			payload.artist_id = ARTIST_OPTION_NEW;
			payload.group = target.groupKey;
		}

		// also keep the pasted name as an alias
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
			"Content-Type": "application/json"
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
			const resultCell = row.querySelector(".resultCell");
			resultCell.textContent = result.message;
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
}

// one row per unique title of every artist that came back with an id
function buildSongTable(artistResults) {
	const artistIdByProvidedName = new Map();
	artistResults.forEach(result => {
		if (result.artist_id) {
			artistIdByProvidedName.set(result.provided_name, result.artist_id);
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
		const resultCell = appendChildToElement(row, "td", "");
		resultCell.classList.add("resultCell");
	});

	const hasSongs = songRows.children.length > 0;
	songTable.hidden = !hasSongs;
	submitSongsButton.hidden = !hasSongs;
}

// actual name if known, else the pasted one
function artistDisplayName(artistId, providedName) {
	const known = data_artistNames.find(a => a.artist_id == artistId && a.is_actual);
	return known ? known.name : providedName;
}

// send the song rows
submitSongsButton.addEventListener('click', () => {
	const songs = [];
	songRows.querySelectorAll("tr:not(.rowHandled)").forEach(row => {
		songs.push({
			artist_id: row.getAttribute("data_artist_id"),
			title: row.getAttribute("data_title"),
		});
	});
	if (songs.length === 0) {
		return;
	}
	statusMessage.innerHTML = t("status.submitting");
	fetch("/modules/music/ajax/song.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json"
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
				if (row.getAttribute("data_artist_id") != result.artist_id || row.getAttribute("data_title") !== result.title) {
					return;
				}
				row.querySelector(".resultCell").textContent = result.message;
				if (result.status === "ok" || result.status === "duplicate") {
					row.classList.add("rowHandled");
				}
			});
		});
	})
	.catch(error => {
		statusMessage.innerHTML = t("status.submitFailed", { error: error });
	});
});
