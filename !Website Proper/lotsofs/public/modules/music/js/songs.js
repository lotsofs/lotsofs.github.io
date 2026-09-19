const songListTable = document.getElementById("songListTable");
const songListBody = songListTable.querySelector("tbody");
const songListHeaders = Array.from(songListTable.querySelectorAll("th[data-sort-key]"));
const songCardList = document.getElementById("songCards");

let songSort = new URLSearchParams(location.search).get("sort") || "id";
let songDir = new URLSearchParams(location.search).get("dir") === "desc" ? "desc" : "asc";

const songArtistSelect = document.getElementById("filterArtist");
const songAlbumSelect = document.getElementById("filterAlbum");
const songNoMatch = document.getElementById("songNoMatch");
const songMobileSortKey = document.getElementById("songMobileSortKey");
const songMobileSortDir = document.getElementById("songMobileSortDir");
const songCardViewToggle = document.getElementById("songCardViewToggle");
const songNotePreview = document.getElementById("songNotePreview");
const songNotePreviewHeader = document.getElementById("songNotePreviewHeader");
const songNotePreviewText = document.getElementById("songNotePreviewText");
const songNotePreviewClear = document.getElementById("songNotePreviewClear");
const songNotePreviewEmptyText = songNotePreviewText.textContent;
const songListHeading = document.getElementById("songListHeading");
const songArtistData = JSON.parse(document.getElementById("songArtistData").textContent);
const songAlbumData = JSON.parse(document.getElementById("songAlbumData").textContent);
const songLinkFieldData = JSON.parse(document.getElementById("songLinkFieldData").textContent);
const songTrackAliases = JSON.parse(document.getElementById("songTrackAliases").textContent);
const songCardModal = document.getElementById("songCardModal");
const songCardModalBody = document.getElementById("songCardModalBody");
const songCardModalClose = document.getElementById("songCardModalClose");

// The table and the card list are two independently rendered representations of
// the same songs, kept in sync by pairing each song id to its <tr> and its card.
const rowsBySongId = new Map();
Array.from(songListBody.children).forEach((tr, index) => {
	rowsBySongId.set(tr.dataset.songId, { tr, card: songCardList.children[index] });
});

function rowPairs() {
	return Array.from(rowsBySongId.values());
}

function valueElement(cell) {
	return cell.querySelector(".ratingNoteText") || cell;
}

function isBeingEdited(cell) {
	return !!cell.querySelector("input, textarea");
}

function cellText(cell) {
	return valueElement(cell).textContent;
}

function fieldCell(container, field) {
	return container.querySelector('[data-field="' + field + '"]');
}

function syncField(songId, field, apply) {
	const pair = rowsBySongId.get(String(songId));
	if (!pair) {
		return;
	}

	[pair.tr, pair.card].forEach(container => {
		const cell = fieldCell(container, field);
		if (cell) {
			apply(cell);
		}
	});
}

function syncResult(songId, message) {
	syncField(songId, "result", cell => valueElement(cell).textContent = message);

	const anyShown = rowPairs().some(pair => cellText(fieldCell(pair.tr, "result")) !== "");
	songListTable.classList.toggle("hideResultColumn", !anyShown);
	songCardList.classList.toggle("hideResultColumn", !anyShown);
	songCardModal.classList.toggle("hideResultColumn", !anyShown);
}

// The popped-up modal shows the song's real card element (moved out of the
// card list, not a copy), so editing and live poll updates keep working on it
// wherever it currently lives; closeCardModal puts it back where it came from.
let modalCardReturnAnchor = null;

function openCardModal(songId) {
	const pair = rowsBySongId.get(String(songId));
	if (!pair) {
		return;
	}

	closeCardModal();

	modalCardReturnAnchor = pair.card.nextElementSibling;
	songCardModalBody.appendChild(pair.card);
	songCardModal.hidden = false;
}

function closeCardModal() {
	const card = songCardModalBody.firstElementChild;
	if (card) {
		exitCardEditMode(card);
		songCardList.insertBefore(card, modalCardReturnAnchor);
	}
	modalCardReturnAnchor = null;
	songCardModal.hidden = true;
}

songCardModalClose.addEventListener("click", closeCardModal);

const SONG_ARTIST_ENDPOINT = "/music/ajax/song-artist";
const SONG_ALBUM_ENDPOINT = "/music/ajax/song-album";
const SONG_LINK_ENDPOINT = "/music/ajax/song-link";
const SONG_YEAR_ENDPOINT = "/music/ajax/song-year";
const SONG_DURATION_ENDPOINT = "/music/ajax/song-duration";

function postJson(endpoint, body) {
	return fetch(endpoint, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify(body)
	})
	.then(async response => {
		const result = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(result && result.error ? result.error : `HTTP ${response.status}`);
		}
		return result;
	});
}

function idsFromAttr(card, attr) {
	return (card.dataset[attr] || "").split(",").filter(Boolean);
}

function setIdsAttr(card, attr, ids) {
	card.dataset[attr] = ids.join(",");
}

// Shared editor for the artist/album fields: both are "pick one of a fixed
// list of existing records, several allowed per song" - same row shape (one
// <select> + a remove button), same add/remove/change flow, only the
// endpoint and option list differ.
function createIdListEditor(config) {
	function optionLabel(option) {
		return option.name === null ? "—" : option.name;
	}

	function buildRow(card, id) {
		const row = document.createElement("div");
		row.className = "songEditRow";
		if (id) {
			row.dataset.itemId = id;
		}

		const select = document.createElement("select");
		select.add(new Option("—", ""));
		config.options.forEach(option => select.add(new Option(optionLabel(option), String(option.id))));
		select.value = id || "";

		const remove = document.createElement("button");
		remove.type = "button";
		remove.className = "songEditRowRemove";
		remove.textContent = "×";

		select.addEventListener("change", () => {
			const songId = card.dataset.songId;
			const oldId = row.dataset.itemId || null;
			const newId = select.value || null;

			if (newId === oldId) {
				return;
			}

			select.disabled = true;

			const afterRemove = () => {
				if (!newId) {
					delete row.dataset.itemId;
					select.disabled = false;
					if (oldId) {
						setIdsAttr(card, config.attr, idsFromAttr(card, config.attr).filter(existing => existing !== oldId));
					}
					return;
				}

				postJson(config.endpoint, { song_id: songId, [config.idKey]: Number(newId), action: "add" })
					.then(result => {
						select.disabled = false;
						if (result.status === "ok" || result.status === "duplicate") {
							row.dataset.itemId = newId;
							const ids = idsFromAttr(card, config.attr).filter(existing => existing !== oldId);
							if (!ids.includes(newId)) {
								ids.push(newId);
							}
							setIdsAttr(card, config.attr, ids);
						}
						else {
							select.value = oldId || "";
							syncResult(songId, result.message);
						}
					})
					.catch(error => {
						select.disabled = false;
						select.value = oldId || "";
						syncResult(songId, t("status.submitFailed", { error: error.message }));
					});
			};

			if (oldId) {
				postJson(config.endpoint, { song_id: songId, [config.idKey]: Number(oldId), action: "remove" })
					.then(afterRemove)
					.catch(error => {
						select.disabled = false;
						select.value = oldId;
						syncResult(songId, t("status.submitFailed", { error: error.message }));
					});
			}
			else {
				afterRemove();
			}
		});

		remove.addEventListener("click", () => {
			const songId = card.dataset.songId;
			const id = row.dataset.itemId;

			if (!id) {
				row.remove();
				return;
			}

			remove.disabled = true;
			postJson(config.endpoint, { song_id: songId, [config.idKey]: Number(id), action: "remove" })
				.then(() => {
					setIdsAttr(card, config.attr, idsFromAttr(card, config.attr).filter(existing => existing !== id));
					row.remove();
				})
				.catch(error => {
					remove.disabled = false;
					syncResult(songId, t("status.submitFailed", { error: error.message }));
				});
		});

		row.append(select, remove);
		return row;
	}

	return {
		enter(card) {
			const cell = fieldCell(card, config.field);
			cell.textContent = "";

			const list = document.createElement("div");
			list.className = "songEditFieldList";

			idsFromAttr(card, config.attr).forEach(id => list.appendChild(buildRow(card, id)));

			const add = document.createElement("button");
			add.type = "button";
			add.className = "songEditRowAdd";
			add.textContent = "+";
			add.addEventListener("click", () => list.insertBefore(buildRow(card, null), add));

			list.appendChild(add);
			cell.appendChild(list);
		},
		exit(card) {
			const cell = fieldCell(card, config.field);
			const names = idsFromAttr(card, config.attr)
				.map(id => {
					const option = config.options.find(candidate => String(candidate.id) === id);
					return option ? optionLabel(option) : "";
				})
				.filter(Boolean);

			cell.textContent = names.join(", ");
			if (config.hasTooltip) {
				cell.title = names.join(", ");
			}
		},
	};
}

const artistFieldEditor = createIdListEditor({
	field: "artist",
	attr: "artistIds",
	endpoint: SONG_ARTIST_ENDPOINT,
	idKey: "artist_id",
	options: songArtistData,
	hasTooltip: false,
});

const albumFieldEditor = createIdListEditor({
	field: "album",
	attr: "albumIds",
	endpoint: SONG_ALBUM_ENDPOINT,
	idKey: "album_id",
	options: songAlbumData,
	hasTooltip: true,
});

function spotifyTrackId(value) {
	const match = /\/track\/([A-Za-z0-9]+)/.exec(value);
	if (match) {
		return match[1];
	}
	const trimmed = value.trim();
	return /^[A-Za-z0-9]+$/.test(trimmed) ? trimmed : null;
}

function youtubeTrackId(value) {
	const match = /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]+)/.exec(value);
	if (match) {
		return match[1];
	}
	const trimmed = value.trim();
	return /^[A-Za-z0-9_-]+$/.test(trimmed) ? trimmed : null;
}

function renderSpotifyEmbed(url) {
	const trackId = url ? spotifyTrackId(url) : null;

	if (!trackId) {
		return null;
	}

	const embed = document.createElement("div");
	embed.className = "songSpotifyEmbed";
	const iframe = document.createElement("iframe");
	iframe.src = "https://open.spotify.com/embed/track/" + trackId + "?theme=0";
	iframe.width = "280";
	iframe.height = "80";
	iframe.frameBorder = "0";
	iframe.loading = "lazy";
	iframe.allow = "encrypted-media; clipboard-write";
	iframe.title = "Spotify";
	embed.appendChild(iframe);
	return embed;
}

function renderYoutubeEmbed(url) {
	const trackId = url ? youtubeTrackId(url) : null;

	if (!trackId) {
		return null;
	}

	const embed = document.createElement("div");
	embed.className = "songYoutubeEmbed";
	const iframe = document.createElement("iframe");
	iframe.src = "https://www.youtube.com/embed/" + trackId;
	iframe.width = "280";
	iframe.height = "158";
	iframe.frameBorder = "0";
	iframe.loading = "lazy";
	iframe.allowFullscreen = true;
	iframe.title = "YouTube";
	embed.appendChild(iframe);
	return embed;
}

function renderSoundcloudEmbed(url) {
	if (!url) {
		return null;
	}

	const embed = document.createElement("div");
	embed.className = "songSoundcloudEmbed";
	const iframe = document.createElement("iframe");
	iframe.src = "https://w.soundcloud.com/player/?url=" + encodeURIComponent(url)
		+ "&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true";
	iframe.width = "280";
	iframe.height = "166";
	iframe.frameBorder = "0";
	iframe.loading = "lazy";
	iframe.allow = "autoplay";
	iframe.title = "SoundCloud";
	embed.appendChild(iframe);
	return embed;
}

function currentLinks(card) {
	return JSON.parse(card.dataset.links || "{}");
}

function renderLinkDisplay(links) {
	const container = document.createElement("div");

	const spotifyEmbed = renderSpotifyEmbed(links.spotify_url);
	if (spotifyEmbed) {
		container.appendChild(spotifyEmbed);
	}

	const youtubeEmbed = renderYoutubeEmbed(links.youtube_url);
	if (youtubeEmbed) {
		container.appendChild(youtubeEmbed);
	}

	const soundcloudEmbed = renderSoundcloudEmbed(links.soundcloud_url);
	if (soundcloudEmbed) {
		container.appendChild(soundcloudEmbed);
	}

	const chips = document.createElement("div");
	chips.className = "songLinkChips";

	songLinkFieldData.forEach(field => {
		const value = links[field.key];
		if (!value) {
			return;
		}
		if (field.key === "spotify_url" && spotifyEmbed) {
			return;
		}
		if (field.key === "youtube_url" && youtubeEmbed) {
			return;
		}
		if (field.key === "soundcloud_url" && soundcloudEmbed) {
			return;
		}

		if (field.key === "filepath") {
			const chip = document.createElement("span");
			chip.className = "songLinkChip songLinkChipWrap";
			chip.title = value;
			chip.textContent = field.label + ": " + value;
			chips.appendChild(chip);
			return;
		}

		const chip = document.createElement("a");
		chip.className = "songLinkChip";
		chip.href = value;
		chip.target = "_blank";
		chip.rel = "noopener";
		chip.title = value;
		chip.textContent = field.key === "other_url" ? value : field.label;
		chips.appendChild(chip);
	});

	if (chips.children.length) {
		container.appendChild(chips);
	}

	return container;
}

const linkFieldEditor = {
	enter(card) {
		const cell = fieldCell(card, "links");
		cell.textContent = "";

		const list = document.createElement("div");
		list.className = "songEditFieldList";

		const links = currentLinks(card);

		songLinkFieldData.forEach(field => {
			const row = document.createElement("div");
			row.className = "songEditRow";

			const label = document.createElement("span");
			label.className = "songEditRowLabel";
			label.textContent = field.label;

			const input = document.createElement("input");
			input.type = "text";
			input.value = links[field.key] || "";

			function save() {
				const songId = card.dataset.songId;
				const value = input.value.trim();

				if (value === (links[field.key] || "")) {
					return;
				}

				input.disabled = true;
				postJson(SONG_LINK_ENDPOINT, { song_id: songId, field: field.key, value })
					.then(result => {
						input.disabled = false;
						if (result.status === "ok") {
							links[field.key] = result.value;
							card.dataset.links = JSON.stringify(links);
							input.value = result.value || "";
						}
						else {
							input.value = links[field.key] || "";
							syncResult(songId, result.message);
						}
					})
					.catch(error => {
						input.disabled = false;
						input.value = links[field.key] || "";
						syncResult(songId, t("status.submitFailed", { error: error.message }));
					});
			}

			input.addEventListener("blur", save);

			row.append(label, input);
			list.appendChild(row);
		});

		cell.appendChild(list);
	},
	exit(card) {
		const cell = fieldCell(card, "links");
		cell.textContent = "";
		cell.appendChild(renderLinkDisplay(currentLinks(card)));
	},
};

function displayYearFor(card) {
	return card.dataset.songYear || card.dataset.fallbackYear || "";
}

const yearFieldEditor = {
	enter(card) {
		const cell = fieldCell(card, "year");
		cell.textContent = "";

		const input = document.createElement("input");
		input.type = "text";
		input.value = card.dataset.songYear || "";
		if (!card.dataset.songYear && card.dataset.fallbackYear) {
			input.placeholder = card.dataset.fallbackYear;
		}

		function save() {
			const songId = card.dataset.songId;
			const value = input.value.trim();

			if (value === (card.dataset.songYear || "")) {
				return;
			}

			input.disabled = true;
			postJson(SONG_YEAR_ENDPOINT, { song_id: songId, value })
				.then(result => {
					input.disabled = false;
					if (result.status === "ok") {
						card.dataset.songYear = result.value === null ? "" : String(result.value);
						input.value = card.dataset.songYear;
						input.placeholder = card.dataset.songYear ? "" : (card.dataset.fallbackYear || "");
					}
					else {
						input.value = card.dataset.songYear || "";
						syncResult(songId, result.message);
					}
				})
				.catch(error => {
					input.disabled = false;
					input.value = card.dataset.songYear || "";
					syncResult(songId, t("status.submitFailed", { error: error.message }));
				});
		}

		input.addEventListener("blur", save);
		cell.appendChild(input);
	},
	exit(card) {
		const cell = fieldCell(card, "year");
		cell.textContent = displayYearFor(card);
	},
};

function formatDuration(seconds) {
	return Math.floor(seconds / 60) + ":" + String(seconds % 60).padStart(2, "0");
}

function displayDurationFor(card) {
	return card.dataset.duration === "" ? "" : formatDuration(Number(card.dataset.duration));
}

const durationFieldEditor = {
	enter(card) {
		const cell = fieldCell(card, "duration");
		cell.textContent = "";

		const input = document.createElement("input");
		input.type = "text";
		input.value = displayDurationFor(card);

		function save() {
			const songId = card.dataset.songId;
			const value = input.value.trim();

			if (value === displayDurationFor(card)) {
				return;
			}

			input.disabled = true;
			postJson(SONG_DURATION_ENDPOINT, { song_id: songId, value })
				.then(result => {
					input.disabled = false;
					if (result.status === "ok") {
						card.dataset.duration = result.value === null ? "" : String(result.value);
					}
					else {
						syncResult(songId, result.message);
					}
					input.value = displayDurationFor(card);
				})
				.catch(error => {
					input.disabled = false;
					input.value = displayDurationFor(card);
					syncResult(songId, t("status.submitFailed", { error: error.message }));
				});
		}

		input.addEventListener("blur", save);
		cell.appendChild(input);
	},
	exit(card) {
		const cell = fieldCell(card, "duration");
		cell.textContent = displayDurationFor(card);
	},
};

function enterCardEditMode(card) {
	card.dataset.editing = "1";
	artistFieldEditor.enter(card);
	albumFieldEditor.enter(card);
	yearFieldEditor.enter(card);
	durationFieldEditor.enter(card);
	linkFieldEditor.enter(card);
}

function exitCardEditMode(card) {
	if (card.dataset.editing !== "1") {
		return;
	}
	delete card.dataset.editing;
	artistFieldEditor.exit(card);
	albumFieldEditor.exit(card);
	yearFieldEditor.exit(card);
	durationFieldEditor.exit(card);
	linkFieldEditor.exit(card);
}

function handleCardEditClick(event) {
	const button = event.target.closest(".songCardEditBtn");
	if (!button) {
		return;
	}

	const card = button.closest("[data-song-id]");
	if (!card) {
		return;
	}

	const titleCell = fieldCell(card, "title");
	if (titleCell && !isBeingEdited(titleCell) && !titleCell.classList.contains("songTitleAliased")) {
		beginCellEdit(titleCell, TITLE_SPEC);
	}

	if (card.dataset.editing !== "1") {
		enterCardEditMode(card);
	}
}

songCardModal.addEventListener("click", event => {
	if (event.target === songCardModal) {
		closeCardModal();
	}
});

document.addEventListener("keydown", event => {
	if (event.key === "Escape" && !songCardModal.hidden) {
		closeCardModal();
	}
});

const songCardViewMedia = window.matchMedia("(max-width: 700px)");
let songCardViewManual = false;

function setCardView(enabled) {
	document.documentElement.classList.toggle("songCardView", enabled);
	songCardViewToggle.setAttribute("aria-pressed", String(enabled));
	songCardViewToggle.textContent = enabled ? t("song.list.cardViewToggleOff") : t("song.list.cardViewToggleOn");
}

setCardView(songCardViewMedia.matches);

songCardViewMedia.addEventListener("change", event => {
	if (!songCardViewManual) {
		setCardView(event.matches);
	}
});

songCardViewToggle.addEventListener("click", () => {
	songCardViewManual = true;
	setCardView(!document.documentElement.classList.contains("songCardView"));
});

const songNav = document.querySelector("nav");

const songStickyOffsets = new ResizeObserver(() => {
	document.documentElement.style.setProperty("--songNavHeight", songNav.getBoundingClientRect().height + "px");
	document.documentElement.style.setProperty("--songHeaderHeight", songListTable.tHead.getBoundingClientRect().height + "px");
});

songStickyOffsets.observe(songNav);
songStickyOffsets.observe(songListTable.tHead);

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

function markLastVisibleRow() {
	let last = null;

	rowPairs().forEach(pair => {
		pair.tr.classList.remove("songRowLast");
		pair.card.classList.remove("songRowLast");
		if (!pair.tr.hidden) {
			last = pair;
		}
	});

	if (last) {
		last.tr.classList.add("songRowLast");
		last.card.classList.add("songRowLast");
	}
}

function applySongFilter() {
	closeCardModal();

	const artist = songArtistSelect.value;
	const album = songAlbumSelect.value;
	let visible = 0;

	rowPairs().forEach(pair => {
		let show = true;
		if (album) {
			show = (pair.tr.dataset.albumIds || "").split(",").includes(album);
		}
		else if (artist) {
			show = (pair.tr.dataset.artistIds || "").split(",").includes(artist);
		}
		pair.tr.hidden = !show;
		pair.card.hidden = !show;
		if (show) {
			visible++;
		}
	});

	markLastVisibleRow();
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

	rowPairs().forEach(pair => {
		[pair.tr, pair.card].forEach(container => {
			const cell = fieldCell(container, "title");
			if (!cell || cell.querySelector("input")) {
				return;
			}

			const songId = container.dataset.songId;
			const listing = album
				? songTrackAliases.find(entry => String(entry.album_id) === album && String(entry.song_id) === songId)
				: null;

			valueElement(cell).textContent = listing ? listing.name : cell.dataset.canonicalTitle;
			cell.classList.toggle("songTitleAliased", listing !== null && listing !== undefined);
		});
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

function durationSeconds(text) {
	const [minutes, seconds] = text.split(":");
	return Number(minutes) * 60 + Number(seconds);
}

function compareCells(a, b, type) {
	if (a === "" || b === "") {
		return a === b ? 0 : (a === "" ? -1 : 1);
	}
	if (type === "number") {
		return Number(a) - Number(b);
	}
	if (type === "duration") {
		return durationSeconds(a) - durationSeconds(b);
	}
	const x = a.toLowerCase();
	const y = b.toLowerCase();
	return x < y ? -1 : x > y ? 1 : 0;
}

function fieldValue(container, field) {
	const cell = fieldCell(container, field);
	return cell ? cellText(cell).trim() : "";
}

function sortRows(key, type) {
	closeCardModal();

	const pairs = rowPairs();
	const flip = songDir === "desc" ? -1 : 1;

	pairs.sort((pairA, pairB) => {
		const result = compareCells(fieldValue(pairA.tr, key), fieldValue(pairB.tr, key), type) * flip;
		return result !== 0 ? result : Number(pairA.tr.dataset.songId) - Number(pairB.tr.dataset.songId);
	});

	const tableFragment = document.createDocumentFragment();
	const cardFragment = document.createDocumentFragment();
	pairs.forEach(pair => {
		tableFragment.appendChild(pair.tr);
		cardFragment.appendChild(pair.card);
	});
	songListBody.appendChild(tableFragment);
	songCardList.appendChild(cardFragment);
	markLastVisibleRow();
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

function syncMobileSortControls() {
	songMobileSortKey.value = songSort;
	songMobileSortDir.value = songDir;
}

function applySort(key, dir, type) {
	songSort = key;
	songDir = dir;

	sortRows(key, type);
	refreshHeaders();
	syncMobileSortControls();
	history.replaceState(null, "", songQuery(songSort, songDir));
}

songListHeaders.forEach(header => {
	const link = header.querySelector("a");
	link.dataset.baseLabel = link.textContent.replace(/[\s▲▼]+$/, "");
	songMobileSortKey.add(new Option(link.dataset.baseLabel, header.dataset.sortKey));

	link.addEventListener("click", event => {
		event.preventDefault();

		const key = header.dataset.sortKey;
		const dir = key === songSort && songDir === "asc" ? "desc" : "asc";

		applySort(key, dir, header.dataset.sortType);
	});
});

syncMobileSortControls();
markLastVisibleRow();

function applyMobileSort() {
	const key = songMobileSortKey.value;
	const header = songListHeaders.find(candidate => candidate.dataset.sortKey === key);
	if (!header) {
		return;
	}

	applySort(key, songMobileSortDir.value, header.dataset.sortType);
}

songMobileSortKey.addEventListener("change", applyMobileSort);
songMobileSortDir.addEventListener("change", applyMobileSort);

const RATING_ENDPOINT = "/music/ajax/song-rating";
const SONG_EDIT_ENDPOINT = "/music/ajax/song-edit";

const SCORE_SPEC = { selectOnFocus: true };
const NOTE_SPEC = { syncTitle: true, multiline: true };

const EDITABLE_CELLS = {
	songMyScoreCell: { ...SCORE_SPEC, field: "score", endpoint: RATING_ENDPOINT, required: false, needsAdmin: false },
	songMyNoteCell: { ...NOTE_SPEC, field: "note", endpoint: RATING_ENDPOINT, required: false, needsAdmin: false },
};

// Not in EDITABLE_CELLS on purpose: title editing is only ever started
// explicitly via the modal's Edit button, never by clicking/double-clicking
// a title directly (that opens the modal instead - see the title-click
// handler below).
const TITLE_SPEC = { field: "title", endpoint: SONG_EDIT_ENDPOINT, required: true };

function colorScoreCell(cell) {
	const text = cellText(cell).trim();
	const score = Number(text);

	if (text === "" || Number.isNaN(score)) {
		cell.style.removeProperty("color");
		return;
	}

	const clamped = Math.min(10, Math.max(0, score));
	cell.style.color = "hsl(" + clamped * 12 + ", 75%, 55%)";
}

function setCellValue(cell, spec, value) {
	valueElement(cell).textContent = value;
	cell.classList.toggle("songCellEmpty", value === "");

	if (spec.syncTitle) {
		cell.title = value;
	}

	if (cell.classList.contains("songRatingScoreCell")) {
		colorScoreCell(cell);
	}
}

Array.from(document.querySelectorAll(".songRatingScoreCell")).forEach(colorScoreCell);

function beginCellEdit(cell, spec) {
	const songId = cell.closest("[data-song-id]").dataset.songId;
	const domField = cell.dataset.field;
	const value = valueElement(cell);
	const original = value.textContent;
	const input = document.createElement(spec.multiline ? "textarea" : "input");
	if (spec.multiline) {
		input.className = "songNoteEditor";
		input.rows = 3;
	}
	else {
		input.type = "text";
	}
	input.value = original;

	syncResult(songId, "");
	value.textContent = "";
	cell.classList.remove("songCellEmpty");
	cell.removeAttribute("title");
	value.appendChild(input);
	input.focus();
	if (spec.selectOnFocus) {
		input.select();
	}
	else {
		input.setSelectionRange(input.value.length, input.value.length);
	}

	let settled = false;

	function finish(commit) {
		if (settled) {
			return;
		}
		settled = true;

		const entered = input.value.trim();
		const keep = commit && (entered !== "" || !spec.required);
		const finalValue = keep ? entered : original;

		syncField(songId, domField, target => setCellValue(target, spec, finalValue));

		if (keep && entered !== original) {
			saveCell(songId, domField, cell, spec, original, entered);
		}
	}

	input.addEventListener("keydown", event => {
		if (event.key === "Enter" && !event.shiftKey) {
			event.preventDefault();
			finish(true);
		}
		if (event.key === "Escape") {
			finish(false);
		}
	});
	input.addEventListener("blur", () => finish(true));
}

function saveCell(songId, domField, cell, spec, original, value) {
	cell.dataset.pending = "1";

	fetch(spec.endpoint, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify({ id: songId, field: spec.field, value: value })
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(result => {
		delete cell.dataset.pending;
		syncField(songId, domField, target => setCellValue(target, spec, String(result.value)));
		syncResult(songId, result.status === "ok" ? "" : result.message);
	})
	.catch(error => {
		delete cell.dataset.pending;
		syncField(songId, domField, target => setCellValue(target, spec, original));
		syncResult(songId, t("status.submitFailed", { error: error.message }));
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

function handleEdit(event) {
	const cell = event.target.closest("td[data-field], dd[data-field]");
	if (!cell || isBeingEdited(cell)) {
		return;
	}

	const spec = editableCell(cell);
	if (!spec) {
		return;
	}
	if (spec.needsAdmin && !songListTable.dataset.canEdit) {
		return;
	}

	beginCellEdit(cell, spec);
}

let previewedNoteCell = null;

function setPreview(header, text) {
	songNotePreviewHeader.textContent = header || "";
	songNotePreviewText.textContent = text || songNotePreviewEmptyText;
	songNotePreview.classList.toggle("songNotePreviewEmpty", !text);
}

function showNotePreview(cell) {
	previewedNoteCell = cell;
	const text = cell.title;

	if (text) {
		const container = cell.closest("[data-song-id]");
		const scoreCell = fieldCell(container, "score_" + cell.dataset.accountId);

		setPreview(t("song.list.notePreviewHeader", {
			name: cell.dataset.raterName,
			song: cellText(fieldCell(container, "title")).trim(),
			score: (scoreCell && cellText(scoreCell).trim()) || "–"
		}), text);
	}
	else {
		setPreview("", "");
	}

	flashCell(cell);
	flashCell(songNotePreview);
}

function showFilepathPreview(abbr) {
	const path = abbr.dataset.path;
	if (!path) {
		return;
	}

	previewedNoteCell = null;
	const container = abbr.closest("[data-song-id]");
	setPreview(t("song.list.pathPreviewHeader", {
		song: cellText(fieldCell(container, "title")).trim()
	}), path);

	flashCell(songNotePreview);
}

function clearNotePreview() {
	previewedNoteCell = null;
	setPreview("", "");
}

songNotePreviewClear.addEventListener("click", clearNotePreview);

function handleListClick(event) {
	const noteCell = event.target.closest("td.songRatingNoteCell, dd.songRatingNoteCell");
	if (noteCell && !isBeingEdited(noteCell)) {
		showNotePreview(noteCell);
	}

	const pathAbbr = event.target.closest("abbr.songLinkAbbr");
	if (pathAbbr) {
		showFilepathPreview(pathAbbr);
	}

	handleCardEditClick(event);
	handleEdit(event);
}

songListBody.addEventListener("click", event => {
	const titleCell = event.target.closest("td.songTitleCell");
	if (titleCell) {
		openCardModal(titleCell.closest("[data-song-id]").dataset.songId);
		return;
	}

	handleListClick(event);
});

// The modal body hosts the real card element while it's popped up (not a
// copy), so editing needs to keep working on it there too.
[songCardList, songCardModalBody].forEach(root => root.addEventListener("click", handleListClick));

const RATING_POLL_ENDPOINT = "/music/ajax/song-rating-poll";
const RATING_POLL_INTERVAL = 3000;
const RATING_POLL_BACKOFF = 60000;

let ratingCursor = Number(JSON.parse(document.getElementById("songRatingCursor").textContent)) || 0;
let ratingPollTimer = null;
let ratingPollInFlight = false;
let ratingPollFailures = 0;

function flashCell(cell) {
	cell.classList.remove("songRatingFlash");
	void cell.offsetWidth;
	cell.classList.add("songRatingFlash");
}

function applyRatingHalf(pair, field, spec, value) {
	[pair.tr, pair.card].forEach(container => {
		const cell = fieldCell(container, field);
		if (!cell || isBeingEdited(cell) || cell.dataset.pending) {
			return;
		}
		if (cellText(cell) === value) {
			return;
		}

		setCellValue(cell, spec, value);
		flashCell(cell);

		if (cell === previewedNoteCell) {
			showNotePreview(cell);
		}
	});
}

function applyRatingChange(change) {
	const pair = rowsBySongId.get(String(change.song));
	if (!pair) {
		return;
	}

	applyRatingHalf(pair, "score_" + change.account, SCORE_SPEC, change.score);
	applyRatingHalf(pair, "note_" + change.account, NOTE_SPEC, change.note);
}

function scheduleRatingPoll(delay) {
	clearTimeout(ratingPollTimer);
	ratingPollTimer = setTimeout(pollRatings, delay);
}

function pollRatings() {
	if (document.visibilityState !== "visible" || ratingPollInFlight) {
		return;
	}
	ratingPollInFlight = true;

	fetch(RATING_POLL_ENDPOINT, {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify({ since: ratingCursor })
	})
	.then(response => response.ok ? response.json() : Promise.reject(new Error(response.status)))
	.then(result => {
		result.changes.forEach(applyRatingChange);
		ratingCursor = result.cursor;
		ratingPollFailures = 0;
	})
	.catch(() => {
		ratingPollFailures++;
	})
	.finally(() => {
		ratingPollInFlight = false;
		scheduleRatingPoll(ratingPollFailures > 2 ? RATING_POLL_BACKOFF : RATING_POLL_INTERVAL);
	});
}

document.addEventListener("visibilitychange", () => {
	if (document.visibilityState === "visible") {
		pollRatings();
		return;
	}
	clearTimeout(ratingPollTimer);
});

if (document.visibilityState === "visible") {
	scheduleRatingPoll(RATING_POLL_INTERVAL);
}
