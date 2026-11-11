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
const songListHeading = document.getElementById("songListHeading");
const songArtistData = JSON.parse(document.getElementById("songArtistData").textContent);
const songAlbumData = JSON.parse(document.getElementById("songAlbumData").textContent);
const songLinkFieldData = JSON.parse(document.getElementById("songLinkFieldData").textContent);
const songTrackAliases = JSON.parse(document.getElementById("songTrackAliases").textContent);
const songRelationTypes = JSON.parse(document.getElementById("songRelationTypes").textContent);
/// The table's id columns, each a type's end: [{key, type, role}].
const SONG_RELATION_COLUMNS = songRelationTypes.flatMap(type => Object.entries(type.columns || {})
	.map(([role, column]) => ({ key: column.key, type: type.type, role })));
const songCardModal = document.getElementById("songCardModal");
const songCardModalBody = document.getElementById("songCardModalBody");
const songCardModalClose = document.getElementById("songCardModalClose");
const songCardModalPrev = document.getElementById("songCardModalPrev");
const songCardModalNext = document.getElementById("songCardModalNext");

// The table and the card list are two independently rendered representations of
// the same songs, kept in sync by pairing each song id to its <tr> and its card.
const rowsBySongId = new Map();
Array.from(songListBody.children).forEach((tr, index) => {
	rowsBySongId.set(tr.dataset.songId, { tr, card: songCardList.children[index] });
});

function rowPairs() {
	return Array.from(rowsBySongId.values());
}

/// Cells whose text is clipped keep it in an inner block, because a max-width
/// on a table cell does nothing under table-layout: auto. Reads and writes go
/// through that inner element where it exists.
function valueElement(cell) {
	return cell.querySelector(".ratingNoteText, .songCellText, .songGateValue") || cell;
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

const songsShowingResult = new Set();

function syncResult(songId, message) {
	syncField(songId, "result", cell => valueElement(cell).textContent = message);

	if (rowsBySongId.has(String(songId))) {
		if (message === "") {
			songsShowingResult.delete(String(songId));
		}
		else {
			songsShowingResult.add(String(songId));
		}
	}

	const anyShown = songsShowingResult.size > 0;
	songListTable.classList.toggle("hideResultColumn", !anyShown);
	songCardList.classList.toggle("hideResultColumn", !anyShown);
	songCardModal.classList.toggle("hideResultColumn", !anyShown);
}

// The popped-up modal shows the song's real card element (moved out of the
// card list, not a copy), so editing and live poll updates keep working on it
// wherever it currently lives; closeCardModal puts it back where it came from.
let modalCardReturnAnchor = null;

const SONG_CARD_LINK_PARAMS = ["songCard"];

function openCardModal(songId) {
	const pair = rowsBySongId.get(String(songId));

	/// A hidden card would move into the modal and stay hidden, leaving an empty box.
	if (!pair || pair.card.hidden) {
		return;
	}

	closeCardModal();

	modalCardReturnAnchor = pair.card.nextElementSibling;
	songCardModalBody.appendChild(pair.card);
	songCardModal.hidden = false;
	refreshCardModalNav();
	writeCardLink(SONG_CARD_LINK_PARAMS, "songCard", Number(songId));
}

function closeCardModal() {
	const card = songCardModalBody.firstElementChild;
	if (card) {
		exitCardEditMode(card);
		songCardList.insertBefore(card, modalCardReturnAnchor);
	}
	modalCardReturnAnchor = null;
	songCardModal.hidden = true;
	writeCardLink(SONG_CARD_LINK_PARAMS, null);
}

// While the modal is open its card is out of the list, so modalCardReturnAnchor
// marks the gap it left: the next card starts at the anchor, the previous one
// starts just before it.
function modalNeighbour(forward) {
	let node = forward
		? modalCardReturnAnchor
		: (modalCardReturnAnchor ? modalCardReturnAnchor.previousElementSibling : songCardList.lastElementChild);

	while (node) {
		if (!node.hidden) {
			return node;
		}
		node = forward ? node.nextElementSibling : node.previousElementSibling;
	}

	return null;
}

function refreshCardModalNav() {
	songCardModalPrev.disabled = modalNeighbour(false) === null;
	songCardModalNext.disabled = modalNeighbour(true) === null;
}

function stepCardModal(forward) {
	if (songCardModal.hidden) {
		return;
	}

	const target = modalNeighbour(forward);
	if (target) {
		openCardModal(target.dataset.songId);
	}
}

songCardModalClose.addEventListener("click", closeCardModal);
songCardModalPrev.addEventListener("click", () => stepCardModal(false));
songCardModalNext.addEventListener("click", () => stepCardModal(true));

const SONG_ARTIST_ENDPOINT = "/music/ajax/song-artist";
const SONG_ALBUM_ENDPOINT = "/music/ajax/song-album";
const SONG_LINK_ENDPOINT = "/music/ajax/song-link";
const SONG_YEAR_ENDPOINT = "/music/ajax/song-year";
const SONG_DURATION_ENDPOINT = "/music/ajax/song-duration";

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
			const options = idsFromAttr(card, config.attr)
				.map(id => config.options.find(candidate => String(candidate.id) === id))
				.filter(Boolean);
			const names = options.map(optionLabel);

			cell.textContent = "";
			if (config.renderItem) {
				options.forEach((option, index) => {
					if (index > 0) {
						cell.appendChild(document.createTextNode(", "));
					}
					cell.appendChild(config.renderItem(option, optionLabel(option)));
				});
			}
			else {
				cell.textContent = names.join(", ");
			}

			if (config.hasTooltip) {
				if ("tooltip" in cell.dataset) {
					cell.dataset.tooltip = names.join(", ");
				}
				else {
					cell.title = names.join(", ");
				}
			}
		},
	};
}

// Same reason as the album names below: leaving edit mode rebuilds these as
// links so the artist card keeps opening from a card whose artists were just
// changed.
function artistNameLink(artistId, label) {
	const link = document.createElement("a");
	link.className = "songArtistLink";
	link.href = "/music/songs?artist=" + Number(artistId);
	link.dataset.artistCardId = String(artistId);
	link.textContent = label;
	return link;
}

const artistFieldEditor = createIdListEditor({
	field: "artist",
	attr: "artistIds",
	endpoint: SONG_ARTIST_ENDPOINT,
	idKey: "artist_id",
	options: songArtistData,
	hasTooltip: false,
	renderItem: (option, label) => artistNameLink(option.id, label),
});

function albumSongsHref(albumId, artistId) {
	return "/music/songs?" + (artistId === null || artistId === undefined ? "" : "artist=" + Number(artistId) + "&") + "album=" + Number(albumId);
}

// Leaving edit mode rebuilds the album names as links again, so the album card
// keeps opening from a card whose albums were just changed (albumCard.js picks
// them up by data-album-card-id, wherever they are on the page).
function albumNameLink(albumId, artistId, label) {
	const link = document.createElement("a");
	link.className = "songAlbumLink";
	link.href = albumSongsHref(albumId, artistId);
	link.dataset.albumCardId = String(albumId);
	link.textContent = label;
	return link;
}

const albumFieldEditor = createIdListEditor({
	field: "album",
	attr: "albumIds",
	endpoint: SONG_ALBUM_ENDPOINT,
	idKey: "album_id",
	options: songAlbumData,
	hasTooltip: true,
	renderItem: (option, label) => albumNameLink(option.id, option.artist_id, label),
});

function spotifyTrackId(value) {
	const match = /\/track\/([A-Za-z0-9]+)/.exec(value);
	if (match) {
		return match[1];
	}
	const trimmed = value.trim();
	return /^[A-Za-z0-9]+$/.test(trimmed) ? trimmed : null;
}

/// Mirrors youtubeLinkIn() in links.php: {id, start in whole seconds or null}, or null.
function youtubeLink(value) {
	const trimmed = value.trim();
	const match = /(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]+)/.exec(trimmed)
		|| /^([A-Za-z0-9_-]+)(?=[?&]|$)/.exec(trimmed);
	if (!match) {
		return null;
	}

	const time = /[?&#](?:t|start)=(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?(?=[&#]|$)/.exec(trimmed);
	const seconds = time ? Number(time[1] || 0) * 3600 + Number(time[2] || 0) * 60 + Number(time[3] || 0) : 0;
	return { id: match[1], start: seconds > 0 ? seconds : null };
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
	const link = url ? youtubeLink(url) : null;

	if (!link) {
		return null;
	}

	const embed = document.createElement("div");
	embed.className = "songYoutubeEmbed";
	const iframe = document.createElement("iframe");
	iframe.src = "https://www.youtube.com/embed/" + link.id + (link.start !== null ? "?start=" + link.start : "");
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

/// Mirrors songLinkHref() in links.php, off the same urlPrefix.
function linkHref(value, prefix) {
	if (/^https?:\/\//i.test(value)) {
		return value;
	}

	return (prefix || "https://") + value;
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
		chip.href = linkHref(value, field.urlPrefix);
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
	// Appended only when it has something in it, or :empty stops matching and the heading is stranded.
	exit(card) {
		const cell = fieldCell(card, "links");
		cell.textContent = "";

		const display = renderLinkDisplay(currentLinks(card));
		if (display.children.length) {
			cell.appendChild(display);
		}
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

/* The song's other names, each to delete or make its title, and a box to write in
   another. Making one the title is a rename to it, which promotes it. */
const aliasFieldEditor = {
	/// The server renders the row only for a song with another name; edit mode is where one is added.
	cell(card) {
		let cell = fieldCell(card, "aliases");
		if (!cell) {
			const title = fieldCell(card, "title").dataset.canonicalTitle;

			const term = document.createElement("dt");
			term.textContent = t("song.column.aliases");

			cell = document.createElement("dd");
			cell.className = "songAliasesCell";
			cell.dataset.field = "aliases";
			cell.dataset.titles = JSON.stringify(title ? [title] : []);

			/// Above Related, as the server renders them.
			(fieldCell(card, "relations") || fieldCell(card, "album")).previousElementSibling.before(term, cell);
		}
		return cell;
	},
	enter(card) {
		const cell = aliasFieldEditor.cell(card);
		const songId = card.dataset.songId;

		renderAliasEditor(cell, {
			kind: "song",
			id: songId,
			names: JSON.parse(cell.dataset.titles),
			use: name => postJson(SONG_EDIT_ENDPOINT, { id: songId, field: "title", value: name }),
			status: message => syncResult(songId, message),
			onChange: (result, action, name) => {
				if (action === "use") {
					applyRenamedTitles(songId, String(result.value), result.titles);
				}
				else {
					syncField(songId, "aliases", target => target.dataset.titles = JSON.stringify(result.names));
					if (action === "delete") {
						forgetTrackListing(songId, name);
					}
				}

				const pair = rowsBySongId.get(String(songId));
				if (pair) {
					applySongTitle(pair.tr);
					applySongTitle(pair.card);
				}
				/// Its versions list its names too.
				refreshVersionCells(songId);

				if (card.dataset.editing === "1") {
					aliasFieldEditor.enter(card);
				}
				/// A name written in and then Done: the blur saves it after leaving edit mode took the empty row away.
				else if (action === "add") {
					aliasFieldEditor.cell(card).dataset.titles = JSON.stringify(result.names);
					applyAliasCell(card);
				}
			},
		});
	},
	exit(card) {
		const cell = fieldCell(card, "aliases");
		if (!cell || !cell.querySelector(".cardAliasList")) {
			return;
		}

		cell.textContent = "";
		applyAliasCell(card);

		/// As the server renders it: no row for a song with no other name.
		if (JSON.parse(cell.dataset.titles).length < 2) {
			cell.previousElementSibling.remove();
			cell.remove();
		}
	},
};

/// A deleted name no album lists the song under any more, and the album filter reads this.
function forgetTrackListing(songId, name) {
	for (let index = songTrackAliases.length - 1; index >= 0; index--) {
		if (String(songTrackAliases[index].song_id) === String(songId) && songTrackAliases[index].name === name) {
			songTrackAliases.splice(index, 1);
		}
	}
}

const SONG_RELATIONSHIP_ENDPOINT = "/music/ajax/song-relationship";

/// The other song by id and title, since two songs may share a title, as songRows.php writes it.
function relationLink(relation) {
	const link = document.createElement("a");
	link.className = "songRelationLink";
	link.href = "/music/songs?songCard=" + Number(relation.song_id);
	link.dataset.songCardId = String(relation.song_id);

	const id = document.createElement("span");
	id.className = "songRelationId";
	id.textContent = Number(relation.song_id) + ":";

	const title = document.createElement("span");
	title.className = "songRelationTitle";
	title.textContent = relation.title;

	link.append(id, " ", title);
	return link;
}

/// One line of a card's relationships.
function relationLine(relation) {
	const line = document.createElement("div");
	line.className = "songRelation";
	line.append(relation.label + " ", relationLink(relation));
	return line;
}

/// A song's relationships from its own end, off its table row.
function songRelations(songId) {
	const pair = rowsBySongId.get(String(songId));
	return JSON.parse((pair && pair.tr.dataset.relations) || "[]");
}

/// Every name a song goes by, current first, off its table row.
function songNames(songId) {
	const pair = rowsBySongId.get(String(songId));
	if (!pair) {
		return [];
	}

	const cell = fieldCell(pair.tr, "aliases");
	if (cell && "titles" in cell.dataset) {
		return JSON.parse(cell.dataset.titles);
	}

	const title = fieldCell(pair.tr, "title").dataset.canonicalTitle;
	return title ? [title] : [];
}

/* Every name the song's versions go by that it does not, once each, with which
   versions go by it, as songRows.php lists them. */
function versionNames(songId, own) {
	const found = [];

	songRelations(songId).forEach(relation => {
		const type = songRelationTypes.find(candidate => candidate.type === relation.type);
		const otherEnd = relation.role === "variant" ? "target" : "variant";
		const which = t("song.list.versionName", { version: type ? type[otherEnd + "Noun"] : relation.type, id: Number(relation.song_id) });

		songNames(relation.song_id).forEach(name => {
			if (own.includes(name)) {
				return;
			}
			const entry = found.find(candidate => candidate.name === name);
			if (entry) {
				entry.which.push(which);
			}
			else {
				found.push({ name, which: [which] });
			}
		});
	});

	return found;
}

/* One id in a row's id columns, opening that song's card. One called otherwise
   is marked, with its title as the tooltip, as songRows.php writes it; the title
   is read off that song's row, so a rename shows at once. */
function relationNumber(relation, ownTitle) {
	const other = rowsBySongId.get(String(relation.song_id));
	const title = other ? fieldCell(other.tr, "title").dataset.canonicalTitle : relation.title;

	const link = document.createElement("a");
	link.className = "songRelationLink";
	link.href = "/music/songs?songCard=" + Number(relation.song_id);
	link.dataset.songCardId = String(relation.song_id);
	link.textContent = String(Number(relation.song_id));

	if (title !== ownTitle) {
		link.classList.add("songVersionHint");
		link.title = t("song.list.versionAs", { title });
	}
	return link;
}

/// A row's id columns, lowest id first.
function applyRelationCells(tr) {
	const ownTitle = fieldCell(tr, "title").dataset.canonicalTitle;
	const relations = songRelations(tr.dataset.songId);

	SONG_RELATION_COLUMNS.forEach(column => {
		const cell = fieldCell(tr, column.key);
		if (!cell) {
			return;
		}

		cell.textContent = "";
		relations
			.filter(relation => relation.type === column.type && relation.role === column.role)
			.sort((a, b) => a.song_id - b.song_id)
			.forEach((relation, index) => {
				if (index > 0) {
					cell.append(", ");
				}
				cell.appendChild(relationNumber(relation, ownTitle));
			});
	});
}

/* A song's Other names and ids in the table, and those of every song it relates
   to, whose rows list its names and compare its title. */
function refreshVersionCells(songId) {
	const ids = new Set([String(songId), ...songRelations(songId).map(relation => String(relation.song_id))]);
	ids.forEach(id => {
		const pair = rowsBySongId.get(id);
		if (pair) {
			applyAliasCell(pair.tr);
			applyRelationCells(pair.tr);
		}
	});
}

const SONG_RELATION_CHOICES_ID = "songRelationChoices";
let relationChoiceIds = null;
let relationChoiceLabels = null;

/* Every song on the page as the song box's suggestions, one <datalist> built on
   first use, each labelled "id: Artist — Title · Albums" as the import's Song
   step labels them, so the id is there to find and every label names one song.
   Sorted by artist and title, not by id. */
function relationChoices() {
	if (relationChoiceIds !== null) {
		return relationChoiceIds;
	}

	const choices = rowPairs().map(pair => {
		const albums = cellText(fieldCell(pair.tr, "album"));
		const name = cellText(fieldCell(pair.tr, "artist")) + " — " + fieldCell(pair.tr, "title").dataset.canonicalTitle;
		return { id: pair.tr.dataset.songId, name: albums ? name + " · " + albums : name };
	});

	const list = document.createElement("datalist");
	list.id = SONG_RELATION_CHOICES_ID;
	relationChoiceIds = new Map();
	relationChoiceLabels = new Map();

	choices
		.sort((a, b) => a.name.localeCompare(b.name) || Number(a.id) - Number(b.id))
		.forEach(choice => {
			const label = choice.id + ": " + choice.name;
			const option = document.createElement("option");
			option.value = label;
			list.appendChild(option);
			relationChoiceIds.set(label, choice.id);
			relationChoiceLabels.set(choice.id, label);
		});

	document.body.appendChild(list);
	return relationChoiceIds;
}

/// The song the box names: a whole label, or a song's id typed on its own (a leading # allowed).
function relationChoiceId(value) {
	const ids = relationChoices();
	if (ids.has(value)) {
		return ids.get(value);
	}

	const typed = value.trim().replace(/^#/, "");
	return /^\d+$/.test(typed) && relationChoiceLabels.has(typed) ? typed : null;
}

/// A rename changes a label, so the list is built again when next needed.
function forgetRelationChoices() {
	const list = document.getElementById(SONG_RELATION_CHOICES_ID);
	if (list) {
		list.remove();
	}
	relationChoiceIds = null;
	relationChoiceLabels = null;
}

/* The songs this one relates to, each line from this song's end of the row, so
   the other song's card shows the same row from the other end. They show on the
   card and in the table; edit mode makes each a kind and a song to change, as an
   artist or album row is, with a + for another. */
const relationFieldEditor = {
	/// The server renders the row only for a song with a relationship; edit mode is where one is added.
	cell(card) {
		let cell = fieldCell(card, "relations");
		if (!cell) {
			const term = document.createElement("dt");
			term.textContent = t("song.column.relations");

			cell = document.createElement("dd");
			cell.className = "songRelationsCell";
			cell.dataset.field = "relations";
			cell.dataset.relations = "[]";

			fieldCell(card, "album").previousElementSibling.before(term, cell);
		}
		return cell;
	},
	/// The card's lines.
	show(card) {
		const cell = fieldCell(card, "relations");
		if (!cell) {
			return;
		}

		const relations = JSON.parse(cell.dataset.relations || "[]");

		/// As the server renders it: no row for a song with no relationship.
		if (relations.length === 0) {
			cell.previousElementSibling.remove();
			cell.remove();
			return;
		}

		cell.textContent = "";
		relations.forEach(relation => cell.appendChild(relationLine(relation)));
	},
	/// Every song the answer names, row and card, since a relationship shows at both ends.
	apply(relationsBySong) {
		Object.entries(relationsBySong).forEach(([songId, relations]) => {
			const pair = rowsBySongId.get(String(songId));
			if (!pair) {
				return;
			}

			const json = JSON.stringify(relations);
			pair.tr.dataset.relations = json;
			applyAliasCell(pair.tr);
			applyRelationCells(pair.tr);

			if (relations.length === 0 && !fieldCell(pair.card, "relations")) {
				return;
			}

			relationFieldEditor.cell(pair.card).dataset.relations = json;
			if (pair.card.dataset.editing === "1") {
				relationFieldEditor.enter(pair.card);
			}
			else {
				relationFieldEditor.show(pair.card);
			}
		});
	},
	/// A refusal carries no lists to redraw from; onRefused puts a saved row back as stored.
	send(songId, body, controls, onRefused) {
		controls.forEach(control => control.disabled = true);
		postJson(SONG_RELATIONSHIP_ENDPOINT, body)
			.then(result => {
				controls.forEach(control => control.disabled = false);
				syncResult(songId, result.status === "ok" ? "" : result.message);
				if (result.relations) {
					relationFieldEditor.apply(result.relations);

					/// A link of a lending kind changes what both songs borrow.
					(result.ratings || []).forEach(applyRatingChange);
					(result.stats || []).forEach(applySongStats);
					Object.keys(result.relations).forEach(refreshVersionTooltips);
				}
				else if (onRefused) {
					onRefused();
				}
			})
			.catch(error => {
				controls.forEach(control => control.disabled = false);
				syncResult(songId, t("status.submitFailed", { error: error.message }));
			});
	},
	enter(card) {
		const cell = relationFieldEditor.cell(card);
		const songId = card.dataset.songId;

		const list = document.createElement("div");
		list.className = "cardRelationList";

		JSON.parse(cell.dataset.relations).forEach(relation => list.appendChild(relationFieldEditor.row(songId, relation)));

		/// As the artists and albums do: + adds a row above it, and the row saves itself; an answer redraws the list.
		const open = document.createElement("button");
		open.type = "button";
		open.className = "songEditRowAdd cardRelationOpen";
		open.textContent = "+";
		open.addEventListener("click", () => {
			const row = relationFieldEditor.row(songId, null);
			open.before(row);
			row.querySelector(".cardRelationKind").focus();
		});
		list.appendChild(open);

		cell.textContent = "";
		cell.appendChild(list);
	},
	/* One relationship as a kind and a song, saved once both are chosen and again
	   whenever either changes. A new row starts on neither, or picking the song
	   first would save a kind nobody chose, and its × drops it unsaved. A picked
	   suggestion saves at once; typing waits for Enter or leaving the box, since
	   a typed id can be the start of another (18, 183). */
	row(songId, relation) {
		const row = document.createElement("div");
		row.className = "cardRelationRow cardRelationEditRow" + (relation ? "" : " cardRelationAddRow");

		const current = relation ? relation.type + ":" + relation.role : "";

		const kind = document.createElement("select");
		kind.className = "cardRelationKind";
		if (!relation) {
			kind.add(new Option("—", ""));
		}
		songRelationTypes.forEach(type => {
			["variant", "target"].forEach(role => kind.add(new Option(type[role], type.type + ":" + role)));
		});
		/// A type no longer in the list stays as it is, under its code.
		if (relation && !Array.from(kind.options).some(option => option.value === current)) {
			kind.add(new Option(relation.label, current));
		}
		kind.value = current;

		/// Read afresh on every lookup: a rename drops the list (forgetRelationChoices).
		relationChoices();
		const currentLabel = relation ? (relationChoiceLabels.get(String(relation.song_id)) || relation.title) : "";

		const other = document.createElement("input");
		other.type = "text";
		other.className = "cardRelationSong";
		other.setAttribute("list", SONG_RELATION_CHOICES_ID);
		other.placeholder = t("relation.songPlaceholder");
		other.value = currentLabel;

		const remove = document.createElement("button");
		remove.type = "button";
		remove.className = "cardRelationRemove";
		remove.textContent = "×";

		/// A saved row the server refused shows what is stored again.
		const putBack = relation
			? () => {
				const card = row.closest(".songCard");
				if (card) {
					relationFieldEditor.enter(card);
				}
			}
			: null;

		function save() {
			const otherId = relationChoiceId(other.value);
			if (kind.disabled || kind.value === "" || !otherId) {
				return;
			}
			/// A typed id reads as the pick it stands for.
			other.value = relationChoiceLabels.get(otherId);
			if (relation && kind.value === current && otherId === String(relation.song_id)) {
				return;
			}

			/// The list has every song on the page, this one too, which can never be related to itself.
			if (otherId === String(songId)) {
				syncResult(songId, t("relation.result.sameSong"));
				other.value = currentLabel;
				return;
			}

			const [type, role] = kind.value.split(":");
			const body = { action: relation ? "update" : "add", song_id: Number(songId), other_song_id: Number(otherId), type, role };
			if (relation) {
				body.id = relation.id;
			}
			relationFieldEditor.send(songId, body, [kind, other, remove], putBack);
		}

		kind.addEventListener("change", save);
		other.addEventListener("input", event => {
			if (!event.inputType || event.inputType === "insertReplacementText") {
				save();
			}
		});
		/// A name that matches no song puts a saved row's own song back.
		other.addEventListener("change", () => {
			if (relationChoiceId(other.value) !== null) {
				save();
			}
			else if (relation) {
				other.value = currentLabel;
			}
		});

		if (relation) {
			remove.title = t("relation.remove");
			remove.addEventListener("click", () => relationFieldEditor.send(songId, { action: "remove", id: relation.id }, [kind, other, remove], putBack));
		}
		else {
			remove.addEventListener("click", () => row.remove());
		}

		row.append(kind, other, remove);
		return row;
	},
	exit(card) {
		const cell = fieldCell(card, "relations");
		if (cell && cell.querySelector(".cardRelationList")) {
			relationFieldEditor.show(card);
		}
	},
};

/// A card the filter hides cannot open here, so its link loads the page without the filter instead.
function handleRelationClick(event) {
	const link = event.target.closest("a[data-song-card-id]");
	if (!link) {
		return false;
	}

	const pair = rowsBySongId.get(link.dataset.songCardId);
	if (pair && !pair.card.hidden) {
		event.preventDefault();
		openCardModal(link.dataset.songCardId);
	}
	return true;
}

function setCardEditLabel(card, editing) {
	const button = card.querySelector(".songCardEditBtn");
	if (button) {
		button.textContent = editing ? t("song.list.cardModalDone") : t("song.list.cardModalEdit");
	}
}

function enterCardEditMode(card) {
	card.dataset.editing = "1";
	setCardEditLabel(card, true);
	artistFieldEditor.enter(card);
	albumFieldEditor.enter(card);
	yearFieldEditor.enter(card);
	durationFieldEditor.enter(card);
	linkFieldEditor.enter(card);
	aliasFieldEditor.enter(card);
	relationFieldEditor.enter(card);
}

function exitCardEditMode(card) {
	if (card.dataset.editing !== "1") {
		return;
	}
	delete card.dataset.editing;
	setCardEditLabel(card, false);
	artistFieldEditor.exit(card);
	albumFieldEditor.exit(card);
	yearFieldEditor.exit(card);
	durationFieldEditor.exit(card);
	linkFieldEditor.exit(card);
	aliasFieldEditor.exit(card);
	relationFieldEditor.exit(card);
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

	if (card.dataset.editing === "1") {
		exitCardEditMode(card);
		return;
	}

	const titleCell = fieldCell(card, "title");
	if (titleCell && !isBeingEdited(titleCell) && !titleCell.classList.contains("songTitleAliased")) {
		beginCellEdit(titleCell, TITLE_SPEC);
	}

	enterCardEditMode(card);
}

songCardModal.addEventListener("click", event => {
	if (event.target === songCardModal) {
		closeCardModal();
	}
});

document.addEventListener("keydown", event => {
	if (songCardModal.hidden) {
		return;
	}

	if (event.key === "Escape") {
		closeCardModal();
		return;
	}

	if (event.target.closest("input, textarea, select")) {
		return;
	}

	if (event.key === "ArrowLeft") {
		event.preventDefault();
		stepCardModal(false);
	}
	if (event.key === "ArrowRight") {
		event.preventDefault();
		stepCardModal(true);
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

/* A rater's name spans as many of their columns as the picker shows. CSS cannot
   do this: under a colspan of 3 with one of them hidden, every later header would
   sit a column to the left of its cells. With none shown the stylesheet hides the
   name. The server renders the same. */
const RATER_COLUMN_BOXES = Array.from(document.querySelectorAll('#songColumnPicker input[data-column="raterScore"], #songColumnPicker input[data-column="raterNote"], #songColumnPicker input[data-column="raterUpdated"]'));

function setRaterColspan() {
	const shown = RATER_COLUMN_BOXES.filter(box => box.checked).length;
	songListTable.querySelectorAll("thead th.songRaterGroup").forEach(th => th.colSpan = Math.max(1, shown));
}

/* The column picker hides a column at once and saves the choice behind it. One
   save at a time: each is a single upsert, but two clicks on the same box have to
   reach the server in the order they were made. */
const songColumnPicker = document.getElementById("songColumnPicker");
let songColumnSaves = Promise.resolve();

songColumnPicker.addEventListener("change", event => {
	const box = event.target.closest("input[data-column]");
	if (!box) {
		return;
	}

	songListTable.classList.toggle(box.dataset.hideClass, !box.checked);
	if (RATER_COLUMN_BOXES.includes(box)) {
		setRaterColspan();
	}

	const choice = { column: box.dataset.column, shown: box.checked };
	songColumnSaves = songColumnSaves
		.then(() => postJson("/music/ajax/song-columns", choice))
		.catch(error => showToast(container => container.textContent = t("song.list.columnsNotSaved", { error: error.message })));
});

/* Dragging a text column's right edge sets its width, kept in this browser only.
   A width is a custom property on <html> that the stylesheet reads, which
   songs.view.php puts back before the table is drawn; double-clicking the edge
   gives the column its own width back. Storage is read fresh each time, so two
   tabs keep each other's other columns. */
const SONG_WIDTHS_KEY = "musicSongColumnWidths";
const SONG_WIDTH_MIN_EM = 3;
const SONG_WIDTH_MAX_EM = 60;

function readSongWidths() {
	try {
		const widths = JSON.parse(localStorage.getItem(SONG_WIDTHS_KEY));
		return widths && typeof widths === "object" ? widths : {};
	}
	catch (error) {
		return {};
	}
}

function storeSongWidth(property, em) {
	const widths = readSongWidths();
	if (em === null) {
		delete widths[property];
	}
	else {
		widths[property] = em;
	}

	try {
		localStorage.setItem(SONG_WIDTHS_KEY, JSON.stringify(widths));
	}
	catch (error) {
		/* Refused storage: the width lasts as long as the page. */
	}
}

const songResizableColumns = [
	...[["songArtistCell", "Artist"], ["songTitleCell", "Title"], ["songAliasesCell", "Aliases"], ["songAlbumCell", "Album"]]
		.map(([cellClass, name]) => ({
			header: songListTable.querySelector(`thead th.${cellClass}`),
			body: `td.${cellClass}`,
			property: `--songWidth${name}`,
		})),
	...Array.from(songListTable.querySelectorAll("thead th.songRatingNoteCell"), header => {
		const accountId = header.dataset.sortKey.slice("note_".length);
		return { header, body: `td[data-field="note_${accountId}"]`, property: `--songWidthRater${accountId}` };
	}),
];

/// The width the property would have to be to keep the column as it is.
function songColumnContentWidth(column) {
	const cell = songListBody.querySelector(`tr:not([hidden]) > ${column.body}`) || column.header;
	const style = getComputedStyle(cell);

	return {
		px: cell.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
		fontSize: parseFloat(style.fontSize),
	};
}

songResizableColumns.forEach(column => {
	const grip = document.createElement("span");
	grip.className = "songColumnGrip";
	column.header.appendChild(grip);

	grip.addEventListener("pointerdown", event => {
		if (event.button !== 0) {
			return;
		}
		event.preventDefault();

		const start = songColumnContentWidth(column);
		const startX = event.clientX;
		let em = null;

		grip.setPointerCapture(event.pointerId);
		grip.classList.add("songColumnGripActive");
		document.documentElement.classList.add("songColumnResizing");

		function move(moveEvent) {
			const wanted = (start.px + moveEvent.clientX - startX) / start.fontSize;
			em = Math.round(Math.min(Math.max(wanted, SONG_WIDTH_MIN_EM), SONG_WIDTH_MAX_EM) * 100) / 100;
			document.documentElement.style.setProperty(column.property, em + "em");
		}

		function end() {
			grip.removeEventListener("pointermove", move);
			grip.removeEventListener("pointerup", end);
			grip.removeEventListener("pointercancel", end);
			grip.classList.remove("songColumnGripActive");
			document.documentElement.classList.remove("songColumnResizing");

			if (em !== null) {
				storeSongWidth(column.property, em);
			}
		}

		grip.addEventListener("pointermove", move);
		grip.addEventListener("pointerup", end);
		grip.addEventListener("pointercancel", end);
	});

	grip.addEventListener("dblclick", () => {
		document.documentElement.style.removeProperty(column.property);
		storeSongWidth(column.property, null);
	});
});

/* ID, Artist and Title stay at the screen's left edge while the table scrolls the
   page sideways, each sticky at the widths of those before it. Measured, since
   the widths follow the content and any dragged width, and a column the picker
   hides measures 0. Not while the three would take more than half the window,
   where they would leave too little room for anything else, as on a phone. */
const songPinnedHeaders = ["songIdCell", "songArtistCell", "songTitleCell"]
	.map(cellClass => songListTable.querySelector(`thead th.${cellClass}`));

/* The pinned cells stay transparent, like every other cell, so they show the
   page's hue-tinted gradient; what hides the columns scrolling under them is
   this one element behind them, painting that gradient where it stands. One
   element lined up on scroll, rather than every pinned cell. */
const songPinBackdrop = document.createElement("div");
songPinBackdrop.className = "songPinBackdrop";
songPinBackdrop.hidden = true;
songListTable.before(songPinBackdrop);

function pinSongColumns() {
	const [id, artist, title] = songPinnedHeaders.map(header => header.getBoundingClientRect().width);
	const pinned = id + artist + title > 0 && id + artist + title <= document.documentElement.clientWidth / 2;

	songListTable.style.setProperty("--songPinArtist", id + "px");
	songListTable.style.setProperty("--songPinTitle", id + artist + "px");
	songListTable.classList.toggle("songPinned", pinned);

	songPinBackdrop.hidden = !pinned;
	if (pinned) {
		const height = songListTable.getBoundingClientRect().height;
		songPinBackdrop.style.width = id + artist + title + "px";
		songPinBackdrop.style.height = height + "px";
		songPinBackdrop.style.marginBottom = -height + "px";
		placeSongPinBackdrop();
	}
}

/// The gradient as big as the body and where the body is, which moves as the backdrop sticks.
function placeSongPinBackdrop() {
	if (songPinBackdrop.hidden) {
		return;
	}

	const page = document.body.getBoundingClientRect();
	const box = songPinBackdrop.getBoundingClientRect();
	songPinBackdrop.style.backgroundSize = `${page.width}px ${page.height}px`;
	songPinBackdrop.style.backgroundPosition = `${page.left - box.left}px ${page.top - box.top}px`;
}

const songPinObserver = new ResizeObserver(pinSongColumns);
songPinnedHeaders.forEach(header => songPinObserver.observe(header));
songPinObserver.observe(songListTable);
songPinObserver.observe(document.body);
window.addEventListener("resize", pinSongColumns);

let songPinFrame = 0;
window.addEventListener("scroll", () => {
	if (!songPinFrame) {
		songPinFrame = requestAnimationFrame(() => {
			songPinFrame = 0;
			placeSongPinBackdrop();
		});
	}
}, { passive: true });
pinSongColumns();

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

	// Keeps whatever card is open, since this rebuilds the address from scratch.
	CARD_LINK_PARAMS.forEach(name => {
		const open = cardLinkValue(name);
		if (open !== null) {
			params.set(name, open);
		}
	});

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
	rowPairs().forEach(pair => {
		applySongTitle(pair.tr);
		applySongTitle(pair.card);
	});
}

/// A row's or card's title: the album's name for the track while that album is the filter.
function applySongTitle(container) {
	const cell = fieldCell(container, "title");
	if (!cell || cell.querySelector("input")) {
		return;
	}

	const album = songAlbumSelect.value;
	const songId = container.dataset.songId;
	const listing = album
		? songTrackAliases.find(entry => String(entry.album_id) === album && String(entry.song_id) === songId)
		: null;

	valueElement(cell).textContent = listing ? listing.name : cell.dataset.canonicalTitle;
	cell.classList.toggle("songTitleAliased", listing !== null && listing !== undefined);
	applyAliasCell(container);
}

/// One of a song's other names in the table, carrying its whole text, since lines clip.
function aliasLine(name) {
	const line = document.createElement("div");
	line.className = "songAlias";
	line.title = name;
	line.textContent = name;
	return line;
}

/// A name only the song's versions go by, its tooltip saying which, as songRows.php writes it.
function versionNameLine(name, which) {
	const hint = document.createElement("span");
	hint.className = "songVersionHint";
	hint.textContent = name;

	const line = document.createElement("div");
	line.className = "songAlias";
	line.title = [name, ...which].join("\n");
	line.appendChild(hint);
	return line;
}

/* Every name the song has but the one its title cell shows: a line each in the
   table, then its versions' names, and one per line on the card, which lists
   only its own. */
function applyAliasCell(container) {
	const cell = fieldCell(container, "aliases");
	const inTable = container.tagName === "TR";

	/// Not while the card's name editor is in it: that redraws itself.
	if (!cell || (!inTable && !("titles" in cell.dataset)) || cell.querySelector(".cardAliasList")) {
		return;
	}

	const shown = cellText(fieldCell(container, "title"));
	const own = inTable ? songNames(container.dataset.songId) : JSON.parse(cell.dataset.titles);
	const names = own.filter(name => name !== shown);

	if (inTable) {
		cell.textContent = "";
		names.forEach(name => cell.appendChild(aliasLine(name)));
		versionNames(container.dataset.songId, own).forEach(entry => cell.appendChild(versionNameLine(entry.name, entry.which)));
	}
	else {
		cell.textContent = names.join("\n");
	}
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

// Only ever asked about two cells that both have something in them.
function compareCells(a, b, type) {
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

// A cell whose text is not what it sorts by says so in data-sort-value.
function fieldValue(container, field) {
	const cell = fieldCell(container, field);
	if (!cell) {
		return "";
	}
	/// A borrowed rating sorts like a real one, as the server's ORDER BY over effective_rating has it.
	return "sortValue" in cell.dataset ? cell.dataset.sortValue : (cellText(cell).trim() || borrowedValue(cell));
}

function sortRows(key, type) {
	closeCardModal();

	const flip = songDir === "desc" ? -1 : 1;

	const decorated = rowPairs().map(pair => ({
		pair: pair,
		value: fieldValue(pair.tr, key),
		songId: Number(pair.tr.dataset.songId)
	}));

	decorated.sort((a, b) => {
		// Blank cells sink to the bottom, settled before the direction is applied.
		if (a.value === "" || b.value === "") {
			if (a.value === b.value) {
				return a.songId - b.songId;
			}
			return a.value === "" ? 1 : -1;
		}

		const result = compareCells(a.value, b.value, type) * flip;
		return result !== 0 ? result : a.songId - b.songId;
	});

	const tableFragment = document.createDocumentFragment();
	const cardFragment = document.createDocumentFragment();
	decorated.forEach(entry => {
		tableFragment.appendChild(entry.pair.tr);
		cardFragment.appendChild(entry.pair.card);
	});
	songListBody.appendChild(tableFragment);
	songCardList.appendChild(cardFragment);
	markLastVisibleRow();
}

/// The time columns start newest first; everything else ascending.
function firstSortDir(header) {
	return header.dataset.sortFirst || "asc";
}

function refreshHeaders() {
	songListHeaders.forEach(header => {
		const link = header.querySelector("a");
		const key = header.dataset.sortKey;
		const isActive = key === songSort;
		const nextDir = isActive ? (songDir === "asc" ? "desc" : "asc") : firstSortDir(header);

		link.textContent = link.dataset.baseLabel + (isActive ? (songDir === "asc" ? " ▲" : " ▼") : "");
		link.href = songQuery(key, nextDir);
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
		const dir = key === songSort ? (songDir === "asc" ? "desc" : "asc") : firstSortDir(header);

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

/// Anything on the way to a score from 0 to 10, which songRating.php enforces too.
function scoreFits(text) {
	const match = /^(\d*)([.,]\d*)?$/.exec(text);

	return match !== null && Number((match[1] || "0") + (match[2] || "").replace(",", ".")) <= 10;
}

const SCORE_SPEC = { selectOnFocus: true, fits: scoreFits };
const NOTE_SPEC = { multiline: true };

const EDITABLE_CELLS = {
	songMyScoreCell: { ...SCORE_SPEC, field: "score", endpoint: RATING_ENDPOINT, required: false },
	songMyNoteCell: { ...NOTE_SPEC, field: "note", endpoint: RATING_ENDPOINT, required: false },
};

// Not in EDITABLE_CELLS: a title is only edited via the modal's Edit button.
const TITLE_SPEC = { field: "title", endpoint: SONG_EDIT_ENDPOINT, required: true };

function scoreColour(score) {
	const clamped = Math.min(10, Math.max(0, score));
	return "hsl(" + clamped * 12 + ", 75%, 55%)";
}

function colorScoreCell(cell) {
	const text = cellText(cell).trim();
	const score = Number(text);

	if (text === "" || Number.isNaN(score)) {
		cell.style.removeProperty("color");
		return;
	}

	cell.style.color = scoreColour(score);
}

/* Nobody else's score or note is readable until this reader has one of their own
   for that song. Two independent conditions, one per half, plus the statistics,
   which are over everybody's scores and so follow the score one. The server
   renders the first state; this keeps it right as ratings are written, as the
   poll brings other people's in, and as the statistics are rewritten under them.

   A cell opened by hand stays open for the page session, keyed by song and
   data-field rather than by element: the field is what makes one rater's score
   and note two separate keys, and what makes the row and the card agree while
   the modal moves cards about. */
const songGateOn = JSON.parse(document.getElementById("songGateSetting").textContent) === true;
const songRevealed = new Set();

const SONG_GATE_CELLS = ".songRatingScoreCell:not(.songMyScoreCell),"
	+ " .songRatingNoteCell:not(.songMyNoteCell),"
	+ " .songStatCell:not(.songStatRatedCell)";

/* Which fields sit on the 0-10 ramp, read off the header row - which is never
   covered and whose data-sort-key is the cell's data-field - rather than from a
   second list of statistic names that could drift from routes/songs.php. A
   covered cell has songScoreColoured stripped, and this is what puts it back. */
const SONG_RAMP_FIELDS = new Set(
	Array.from(songListTable.querySelectorAll("thead th.songScoreColoured[data-sort-key]"), th => th.dataset.sortKey)
);

/// The row or the card a cell belongs to; a card is a <dl>, so one of the two matches.
function gateContainer(cell) {
	return cell.closest(".songCard, tr");
}

function gateKey(container, cell) {
	return container.dataset.songId + ":" + cell.dataset.field;
}

/* What this reader's own cell holds, including what they are part way through
   typing: beginCellEdit empties the value element, so reading the text mid-edit
   reads as "nothing written" and drops the gate over the rest of the row while
   they are looking at it. */
function ownValue(cell) {
	const input = cell.querySelector("input, textarea");
	const typed = (input ? input.value : cellText(cell)).trim();

	/// A rating borrowed from another version counts as the reader's own, as the server's gate has it.
	return typed !== "" ? typed : borrowedValue(cell);
}

/// What a rating cell borrows from the song's other versions (ratings.php), or "".
function borrowedValue(cell) {
	const span = cell.querySelector(".songInherited");

	return span ? span.textContent.trim() : "";
}

function gateRevealButton(cell) {
	let button = cell.querySelector(".songGateReveal");

	if (!button) {
		const isNote = cell.classList.contains("songRatingNoteCell");

		button = document.createElement("button");
		button.type = "button";
		button.className = "songGateReveal";
		/// The long label only where the column has room for it.
		button.textContent = cell.matches("td.songRatingScoreCell, td.songStatCell")
			? t("song.list.gateRevealShort")
			: t("song.list.gateReveal");
		button.title = isNote ? t("song.list.gateNoteHint") : t("song.list.gateScoreHint");
		cell.insertBefore(button, cell.firstChild);
	}

	return button;
}

function setCellGate(cell, gated) {
	cell.classList.toggle("songGated", gated);

	if (gated) {
		gateRevealButton(cell);
		/// Withheld with the value: the tooltip would hand it straight back.
		delete cell.dataset.tooltip;
		delete cell.dataset.tooltipRich;
		cell.removeAttribute("title");
		cell.classList.remove("songScoreColoured");
		cell.style.removeProperty("color");
		return;
	}

	if (SONG_RAMP_FIELDS.has(cell.dataset.field)) {
		cell.classList.add("songScoreColoured");
		colorScoreCell(cell);
	}

	if (cell.classList.contains("songRatingCell")) {
		refreshCellTooltip(cell);
	}
}

/* A rating cell's tooltip: a note's own text, then what that rater gave the
   song's other versions (refreshVersionTooltips). Whichever of title and
   data-tooltip the cell currently uses, since tooltip.js moves one into the other. */
function refreshCellTooltip(cell) {
	const own = cell.classList.contains("songRatingNoteCell") ? cellText(cell) : "";
	const lines = JSON.parse(cell.dataset.versions || "[]");
	const list = lines.map(line => line.map(([piece]) => piece).join("")).join("\n");
	const text = [own, list].filter(part => part !== "").join("\n\n");

	if ("tooltip" in cell.dataset) {
		cell.dataset.tooltip = text;
	}
	else if (text !== "") {
		cell.title = text;
	}
	else {
		cell.removeAttribute("title");
	}

	/// The list's sentences in bold, through tooltip.js; anything somebody wrote stays plain.
	if (lines.length === 0) {
		delete cell.dataset.tooltipRich;
		return;
	}

	const pieces = own === "" ? [] : [[own + "\n\n", false]];
	lines.forEach((line, index) => {
		if (index > 0) {
			pieces.push(["\n", false]);
		}
		pieces.push(...line);
	});
	cell.dataset.tooltipRich = JSON.stringify(pieces);
}

function applySpoilerGate(container) {
	if (!songGateOn || !container) {
		return;
	}

	const mineScore = container.querySelector(".songMyScoreCell");
	const mineNote = container.querySelector(".songMyNoteCell");

	/// No rater is "mine" on a page with no viewer, which covers nothing.
	const scoresGated = mineScore !== null && ownValue(mineScore) === "";
	const notesGated = mineNote !== null && ownValue(mineNote) === "";

	container.querySelectorAll(SONG_GATE_CELLS).forEach(cell => {
		const isNote = cell.classList.contains("songRatingNoteCell");
		const gated = (isNote ? notesGated : scoresGated)
			&& (cellText(cell).trim() !== "" || borrowedValue(cell) !== "")
			&& !songRevealed.has(gateKey(container, cell));

		setCellGate(cell, gated);
	});
}

/// Both trees, since the revealed set is keyed by song and field, not by element.
function regateSong(songId) {
	const pair = rowsBySongId.get(String(songId));
	if (!pair) {
		return;
	}

	applySpoilerGate(pair.tr);
	applySpoilerGate(pair.card);
}

document.addEventListener("click", event => {
	const button = event.target.closest(".songGateReveal");
	if (!button) {
		return;
	}

	const cell = button.closest("[data-field]");
	const container = cell && gateContainer(cell);
	if (!container) {
		return;
	}

	songRevealed.add(gateKey(container, cell));
	regateSong(container.dataset.songId);

	/// The songs this one lends to may list the value just uncovered.
	lendingVersions(container.dataset.songId).forEach(version => refreshVersionTooltips(version.song_id));
});

function setCellValue(cell, spec, value) {
	valueElement(cell).textContent = value;
	cell.classList.toggle("songCellEmpty", value === "");

	/// Every rating cell, since beginCellEdit strips the title a score's version list lives in too.
	if (cell.classList.contains("songRatingCell")) {
		refreshCellTooltip(cell);
	}

	if (cell.classList.contains("songScoreColoured")) {
		colorScoreCell(cell);
	}

	/// A rating landing anywhere on a song can open or close the gate on the rest.
	const container = gateContainer(cell);
	applySpoilerGate(container);

	/// The songs this one lends to list its ratings in their tooltips.
	if (container && cell.classList.contains("songRatingCell")) {
		lendingVersions(container.dataset.songId).forEach(version => refreshVersionTooltips(version.song_id));
	}
}

/* A rating borrowed from another version, shown faded beside an empty value
   (ratings.php). The server works out what it is; this only writes it, before
   setCellValue so the gate that runs there sees it. */
function setBorrowed(cell, value) {
	let span = cell.querySelector(".songInherited");
	if (!span && value === "") {
		return;
	}

	if (!span) {
		span = document.createElement("span");
		span.className = "songInherited";
		valueElement(cell).after(span);
	}

	span.textContent = value;
	cell.classList.toggle("songInheritedCell", value !== "");
	colourBorrowed(cell);
}

function colourBorrowed(cell) {
	const span = cell.querySelector(".songInherited");
	if (!span || !cell.classList.contains("songRatingScoreCell")) {
		return;
	}

	const score = Number(span.textContent);
	if (span.textContent === "" || Number.isNaN(score)) {
		span.style.removeProperty("color");
	}
	else {
		span.style.color = scoreColour(score);
	}
}

const SONG_LENDING_TYPES = new Set(songRelationTypes.filter(type => type.inherits).map(type => type.type));

/// The songs a song borrows ratings from, and lends them to: its relationships of a lending kind, once each.
function lendingVersions(songId) {
	const seen = new Set();
	const versions = [];

	songRelations(songId).forEach(relation => {
		if (SONG_LENDING_TYPES.has(relation.type) && !seen.has(relation.song_id)) {
			seen.add(relation.song_id);
			versions.push(relation);
		}
	});

	return versions;
}

/* One line of a rater cell's tooltip, "You scored the remastered version (2:
   Remaster Song) 8", or a note under "…(2: Remaster Song):", as [[piece, bold],
   ...] for tooltip.js. The sentence is
   bold and what was written is not. The template is split at {value} before
   anything is filled in, wherever the language puts the value and whatever a
   title holds. The version is named from the other song's end, since the line
   is about that song. */
function versionLine(cell, container, version, value) {
	const type = songRelationTypes.find(candidate => candidate.type === version.type);
	const otherEnd = version.role === "variant" ? "target" : "variant";
	const isNote = cell.classList.contains("songRatingNoteCell");
	const nameCell = fieldCell(container, "note_" + cell.dataset.accountId);
	const params = {
		name: nameCell ? nameCell.dataset.raterName : "",
		version: type ? type[otherEnd + "Noun"] : version.type,
		id: version.song_id,
		title: version.title,
	};

	const template = cell.classList.contains("songMineCell")
		? (isNote ? t("song.list.versionNoteMine") : t("song.list.versionScoreMine"))
		: (isNote ? t("song.list.versionNote") : t("song.list.versionScore"));
	/// One pass, as fillFromTemplate does: a name or title holding "{title}" is not filled in twice.
	const fill = text => text.replace(/\{(\w+)\}/g, (placeholder, key) => key in params ? String(params[key]) : placeholder);
	const [before, after = ""] = template.split("{value}");

	return [[fill(before), true], [value, false], [fill(after), true]].filter(([text]) => text !== "");
}

/* Each rater cell's list of what that rater gave the song's other versions,
   read off those songs' own cells - what was really written, never what they
   borrow in turn - for the cell's tooltip (versionLine). */
function refreshVersionTooltips(songId) {
	const pair = rowsBySongId.get(String(songId));
	if (!pair) {
		return;
	}

	const versions = lendingVersions(songId);

	[pair.tr, pair.card].forEach(container => {
		container.querySelectorAll(".songRatingCell[data-field]").forEach(cell => {
			const lines = [];
			versions.forEach(version => {
				const other = rowsBySongId.get(String(version.song_id));
				const source = other ? fieldCell(other.tr, cell.dataset.field) : null;
				/// What the gate covers there stays covered here; revealing it refreshes this list.
				const value = source && !source.classList.contains("songGated") ? cellText(source).trim() : "";
				if (value !== "") {
					lines.push(versionLine(cell, container, version, value));
				}
			});

			if (lines.length > 0) {
				cell.dataset.versions = JSON.stringify(lines);
			}
			else if ("versions" in cell.dataset) {
				delete cell.dataset.versions;
			}
			else {
				return;
			}

			/// A covered cell withholds its tooltip, the list included.
			if (!cell.classList.contains("songGated")) {
				refreshCellTooltip(cell);
			}
		});
	});
}

// Every cell holding one score on the 0-10 ramp, rater and statistic alike, and every borrowed score.
Array.from(document.querySelectorAll(".songScoreColoured")).forEach(colorScoreCell);
Array.from(document.querySelectorAll(".songRatingScoreCell.songInheritedCell")).forEach(colourBorrowed);

// Every song with a version lists in its rating tooltips what was given to the others.
rowPairs().forEach(pair => {
	if (lendingVersions(pair.tr.dataset.songId).length > 0) {
		refreshVersionTooltips(pair.tr.dataset.songId);
	}
});

/* Undoes any edit, typed, pasted or deleted, that leaves something fits() rejects.
   Put back, never stripped: the score opens selected, so a stripped letter would
   leave it empty and the blur would save that as clearing the score. */
function refuseUnfitInput(input, fits) {
	let before = { value: input.value, start: 0, end: input.value.length };

	input.addEventListener("beforeinput", () => {
		before = { value: input.value, start: input.selectionStart, end: input.selectionEnd };
	});

	input.addEventListener("input", () => {
		if (!fits(input.value)) {
			input.value = before.value;
			input.setSelectionRange(before.start, before.end);
		}
	});
}

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
	if (spec.fits) {
		input.inputMode = "decimal";
		refuseUnfitInput(input, spec.fits);
	}

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
	if (spec.endpoint === RATING_ENDPOINT) {
		saveRating(songId, spec.field, value, original);
		return;
	}

	cell.dataset.pending = "1";

	postJson(spec.endpoint, { id: songId, field: spec.field, value: value })
	.then(result => {
		delete cell.dataset.pending;
		syncField(songId, domField, target => setCellValue(target, spec, String(result.value)));
		if (spec === TITLE_SPEC && result.status === "ok" && result.titles) {
			applyRenamedTitles(songId, String(result.value), result.titles);
		}
		// Straight away rather than on the next poll tick.
		if (result.stats) {
			applySongStats(result.stats);
		}
		syncResult(songId, result.status === "ok" ? "" : result.message);
	})
	.catch(error => {
		delete cell.dataset.pending;
		syncField(songId, domField, target => setCellValue(target, spec, original));
		syncResult(songId, t("status.submitFailed", { error: error.message }));
	});
}

/* A rename can promote one of the song's names or replace the current one, so
   the names come back from the server rather than being worked out here. The
   canonical title is what the album filter falls back to. */
function applyRenamedTitles(songId, title, titles) {
	forgetRelationChoices();

	syncField(songId, "title", cell => {
		cell.dataset.canonicalTitle = title;

		// Whichever the cell currently uses, since tooltip.js moves title into data-tooltip.
		if ("tooltip" in cell.dataset) {
			cell.dataset.tooltip = titles.join(", ");
		}
		else if (cell.hasAttribute("title")) {
			cell.title = titles.join(", ");
		}
	});

	/// The table's cell even for a song that had one name; the card has the row only when it had more.
	syncField(songId, "aliases", cell => {
		cell.dataset.titles = JSON.stringify(titles);
	});

	const pair = rowsBySongId.get(String(songId));
	if (pair) {
		applyAliasCell(pair.card);
	}
	refreshVersionCells(songId);
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
	beginCellEdit(cell, spec);
}

/// Opens a song's card and flashes the part $findTarget picks out.
function openCardAndFlash(songId, findTarget) {
	const pair = rowsBySongId.get(String(songId));

	if (pair) {
		pair.card.querySelectorAll(".songRatingFlash").forEach(cell => cell.classList.remove("songRatingFlash"));
	}

	openCardModal(songId);

	if (!pair) {
		return;
	}

	const target = findTarget(pair.card);
	if (target) {
		flashCell(target);
	}
}

function handleListClick(event) {
	if (handleRelationClick(event)) {
		return;
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

	/// The pill has its own handler, and uncovering a value is not a request
	/// for the card. tbody is a nearer ancestor than document, so without this
	/// a click on a pill in a note cell would pop the modal as well.
	if (event.target.closest(".songGateReveal")) {
		return;
	}

	const noteCell = event.target.closest("td.songRatingNoteCell");
	if (noteCell && !editableCell(noteCell) && !isBeingEdited(noteCell)) {
		openCardAndFlash(noteCell.closest("[data-song-id]").dataset.songId, card => fieldCell(card, noteCell.dataset.field));
		return;
	}

	const pathAbbr = event.target.closest("abbr.songLinkAbbr[data-path]");
	if (pathAbbr) {
		openCardAndFlash(pathAbbr.closest("[data-song-id]").dataset.songId, card => card.querySelector(".songLinkChipWrap"));
		return;
	}

	handleListClick(event);
});

// The modal body hosts the real card element, not a copy.
[songCardList, songCardModalBody].forEach(root => root.addEventListener("click", handleListClick));

const RATING_POLL_ENDPOINT = "/music/ajax/song-rating-poll";
const RATING_POLL_INTERVAL = 3000;
const RATING_POLL_BACKOFF = 60000;
const RATING_POLL_JITTER = 15000;
const RATING_POLL_TIMEOUT = 10000;

let ratingCursor = Number(JSON.parse(document.getElementById("songRatingCursor").textContent)) || 0;
let ratingAuditCursor = Number(JSON.parse(document.getElementById("songAuditCursor").textContent)) || 0;
let ratingPollTimer = null;
let ratingPollInFlight = false;
let ratingPollFailures = 0;

const songToasts = document.getElementById("songToasts");
const TOAST_SHOW_OWN_EVENTS = true;
const TOAST_DEFAULT_DURATION = 180000;
/// From the cog menu: 0 is off for rating toasts, -1 is until closed.
const toastSeconds = Number(JSON.parse(document.getElementById("songToastSeconds").textContent));
const TOAST_LIMIT = 140;
const TOAST_VALUE_MAX = 140;

const toastTimers = new Map();

function dismissToast(toast) {
	clearTimeout(toastTimers.get(toast));
	toastTimers.delete(toast);
	toast.remove();
}

function toastStrong(text) {
	const element = document.createElement("strong");
	element.textContent = text;
	return element;
}

/// The rater's own colour, matching .navColourSwatch in styles.css.
function toastName(text, hue) {
	const element = toastStrong(text);
	element.style.color = `hsl(${Number(hue)}, 100%, 60%)`;
	return element;
}

function toastQuote(text) {
	const element = document.createElement("q");
	element.textContent = text;
	return element;
}

function toastScore(text) {
	const element = document.createElement("span");
	element.className = "songToastScore";
	element.textContent = text;

	const score = Number(text);
	if (text !== "" && !Number.isNaN(score)) {
		element.style.color = scoreColour(score);
	}

	return element;
}

function fillFromTemplate(container, template, parts) {
	const pattern = /\{(\w+)\}/g;
	let last = 0;
	let match;

	while ((match = pattern.exec(template)) !== null) {
		if (match.index > last) {
			container.appendChild(document.createTextNode(template.slice(last, match.index)));
		}

		const part = parts[match[1]];
		container.appendChild(part ? part() : document.createTextNode(match[0]));
		last = match.index + match[0].length;
	}

	container.appendChild(document.createTextNode(template.slice(last)));
}

function showToast(fill, songId) {
	while (songToasts.children.length >= TOAST_LIMIT) {
		dismissToast(songToasts.firstElementChild);
	}

	const toast = appendChildToElement(songToasts, "div");
	toast.className = "songToast";

	const text = appendChildToElement(toast, "span");
	text.className = "songToastText";
	fill(text);

	const close = appendChildToElement(toast, "button", "×");
	close.className = "songToastClose";
	close.type = "button";
	close.title = t("song.list.cardModalClose");

	close.addEventListener("click", event => {
		event.stopPropagation();
		dismissToast(toast);
	});

	toast.addEventListener("click", () => {
		openCardModal(songId);
		dismissToast(toast);
	});

	/* Off only reaches here for the unsaved-rating warning, which is too
	   important to drop, so it keeps the default lifetime. */
	if (toastSeconds !== -1) {
		const duration = toastSeconds > 0 ? toastSeconds * 1000 : TOAST_DEFAULT_DURATION;
		toastTimers.set(toast, setTimeout(() => dismissToast(toast), duration));
	}
}

/* Each half of a toast follows its own gate, read off this reader's own cell
   for that song in the table row - never the card, which the modal moves out
   of the list.

   A song this page never rendered, one somebody added since it loaded, has no
   cell to read. That withholds the value: there is no reading of "unknown"
   under which showing it is right, and a brand new song is the one case where
   this reader certainly has nothing of their own on it. */
function toastValueCovered(event) {
	if (!songGateOn || event.mine) {
		return false;
	}

	const pair = rowsBySongId.get(String(event.songId));
	if (!pair) {
		return true;
	}

	const mine = pair.tr.querySelector(event.field === "score" ? ".songMyScoreCell" : ".songMyNoteCell");

	return mine !== null && ownValue(mine) === "";
}

function showRatingEvent(event) {
	if (toastSeconds === 0 || (!TOAST_SHOW_OWN_EVENTS && event.mine)) {
		return;
	}

	const isScore = event.field === "score";
	const emphasise = isScore ? toastScore : toastQuote;

	const parts = {
		name: () => toastName(event.name, event.hue),
		song: () => toastStrong(event.songLabel),
	};

	let template;

	if (event.value === null) {
		template = isScore ? t("song.list.toastScoreCleared") : t("song.list.toastNoteCleared");
	}
	else if (toastValueCovered(event)) {
		/* Still names the person and the song; only the value is withheld, so
		   these templates carry neither {value} nor {previous} - an unmatched
		   placeholder is emitted literally by fillFromTemplate. */
		template = event.previousValue === null
			? (isScore ? t("song.list.toastScoreHidden") : t("song.list.toastNoteHidden"))
			: (isScore ? t("song.list.toastScoreUpdatedHidden") : t("song.list.toastNoteUpdatedHidden"));
	}
	else {
		const shown = event.value.length > TOAST_VALUE_MAX
			? event.value.slice(0, TOAST_VALUE_MAX) + "…"
			: event.value;

		parts.value = () => emphasise(shown);

		if (event.previousValue === null) {
			template = isScore ? t("song.list.toastScore") : t("song.list.toastNote");
		}
		else {
			parts.previous = () => emphasise(event.previousValue);
			template = isScore ? t("song.list.toastScoreUpdated") : t("song.list.toastNoteUpdated");
		}
	}

	showToast(container => fillFromTemplate(container, template, parts), event.songId);
}

/// Clears the class on finish, or moving the element re-fires the flash.
function flashCell(cell) {
	cell.classList.remove("songRatingFlash");
	void cell.offsetWidth;
	cell.classList.add("songRatingFlash");
	cell.addEventListener("animationend", () => cell.classList.remove("songRatingFlash"), { once: true });
}

/// The server says which half is borrowed (ratings.php): that value goes beside an empty real one.
function applyRatingHalf(pair, field, spec, value, borrowed) {
	const real = borrowed ? "" : value;
	const lent = borrowed ? value : "";

	[pair.tr, pair.card].forEach(container => {
		const cell = fieldCell(container, field);
		if (!cell || isBeingEdited(cell) || cell.dataset.pending) {
			return;
		}
		if (cellText(cell) === real && borrowedValue(cell) === lent) {
			return;
		}

		setBorrowed(cell, lent);
		setCellValue(cell, spec, real);
		flashCell(cell);
	});
}

// The statistics columns and Last updated after a score moved. The server sends finished strings.
const SONG_STAT_FIELDS = ["average", "deviation", "median", "mode", "highest", "lowest", "sum", "rated"];

function applySongStats(stats) {
	const pair = rowsBySongId.get(String(stats.song));
	if (!pair) {
		return;
	}

	SONG_STAT_FIELDS.forEach(field => {
		const cell = fieldCell(pair.tr, field);
		if (!cell) {
			return;
		}

		if (field === "mode") {
			cell.dataset.sortValue = stats.modeSort;
		}
		if (cellText(cell) === stats[field]) {
			return;
		}

		setCellValue(cell, {}, stats[field]);
	});

	if ("updated" in stats) {
		setTimeCell(fieldCell(pair.tr, "updated"), stats.updated, stats.updatedSort);
	}
}

function applyRatingChange(change) {
	const pair = rowsBySongId.get(String(change.song));
	if (!pair) {
		return;
	}

	applyRatingHalf(pair, "score_" + change.account, SCORE_SPEC, change.score, change.scoreBorrowed);
	applyRatingHalf(pair, "note_" + change.account, NOTE_SPEC, change.note, change.noteBorrowed);
	if ("updated" in change) {
		setTimeCell(fieldCell(pair.tr, "updated_" + change.account), change.updated, change.updatedSort);
	}
}

/// A time column's cell as the server wrote it: the text, and the unix time it sorts by.
function setTimeCell(cell, text, sort) {
	if (!cell) {
		return;
	}

	cell.textContent = text;
	if (sort === null || sort === undefined) {
		delete cell.dataset.sortValue;
	}
	else {
		cell.dataset.sortValue = String(sort);
	}
}

function scheduleRatingPoll(delay) {
	clearTimeout(ratingPollTimer);
	ratingPollTimer = setTimeout(pollRatings, delay);
}

function nextRatingPollDelay() {
	if (ratingPollFailures > 2) {
		return RATING_POLL_BACKOFF + Math.floor(Math.random() * RATING_POLL_JITTER);
	}
	return RATING_POLL_INTERVAL;
}

function pollRatings() {
	if (ratingPollInFlight) {
		return;
	}
	if (document.visibilityState !== "visible") {
		clearTimeout(ratingPollTimer);
		return;
	}
	ratingPollInFlight = true;

	const controller = new AbortController();
	const timeout = setTimeout(() => controller.abort(), RATING_POLL_TIMEOUT);

	postJson(RATING_POLL_ENDPOINT, { since: ratingCursor, sinceAudit: ratingAuditCursor }, { signal: controller.signal })
	.then(result => {
		result.changes.forEach(applyRatingChange);
		(result.stats || []).forEach(applySongStats);
		result.events.slice(-TOAST_LIMIT).forEach(showRatingEvent);
		ratingCursor = result.cursor;
		ratingAuditCursor = result.auditCursor;
		ratingPollFailures = 0;
		/// An answered poll is the surest sign the connection is back.
		flushUnsavedRatings();
	})
	.catch(() => {
		ratingPollFailures++;
	})
	.finally(() => {
		clearTimeout(timeout);
		ratingPollInFlight = false;
		scheduleRatingPoll(nextRatingPollDelay());
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

/* Rating writes that have not reached the server, keyed "song:field". Each is
   queued before it is sent and taken out once the server has it, so one that
   never lands is resent. See docs/music.md for why it is shaped like this. */
const songAccountId = JSON.parse(document.getElementById("songAccountId").textContent);
const UNSAVED_RATINGS_KEY = "musicUnsavedRatings:" + songAccountId;
const RATING_SAVE_TIMEOUT = 10000;
const RATING_RESEND_MIN = 3000;
const RATING_RESEND_MAX = 60000;
const RATING_SPECS = { score: EDITABLE_CELLS.songMyScoreCell, note: EDITABLE_CELLS.songMyNoteCell };

/// Set once storage refuses; the outbox then lives here, and leaving the page warns.
let unsavedMemory = null;
const ratingsInFlight = new Map();
const unsavedToasted = new Set();
let ratingResendTimer = null;
let ratingResendDelay = RATING_RESEND_MIN;
let ratingResendRunning = false;
let ratingResendAgain = false;
let ratingResendBlocked = false;

function unsavedKey(songId, field) {
	return songId + ":" + field;
}

function isUnsavedEntry(entry) {
	return !!entry
		&& /^\d+$/.test(String(entry.song))
		&& Object.hasOwn(RATING_SPECS, entry.field)
		&& typeof entry.value === "string";
}

/// Read fresh every time, so two open tabs share one outbox instead of each resending a stale copy.
function readUnsaved() {
	if (unsavedMemory) {
		return { ...unsavedMemory };
	}

	let raw;
	try {
		raw = localStorage.getItem(UNSAVED_RATINGS_KEY);
	}
	catch (error) {
		unsavedMemory = {};
		return {};
	}

	let stored;
	try {
		stored = JSON.parse(raw || "{}");
	}
	catch (error) {
		return {};
	}

	const outbox = {};
	if (stored && typeof stored === "object") {
		Object.values(stored).filter(isUnsavedEntry).forEach(entry => {
			outbox[unsavedKey(entry.song, entry.field)] = { song: String(entry.song), field: entry.field, value: entry.value };
		});
	}

	return outbox;
}

function writeUnsaved(outbox) {
	if (!unsavedMemory) {
		try {
			if (Object.keys(outbox).length === 0) {
				localStorage.removeItem(UNSAVED_RATINGS_KEY);
			}
			else {
				localStorage.setItem(UNSAVED_RATINGS_KEY, JSON.stringify(outbox));
			}
			return;
		}
		catch (error) {
			// Refused, so this page holds the outbox from here on.
		}
	}

	unsavedMemory = { ...outbox };
}

function queueRating(songId, field, value) {
	const outbox = readUnsaved();
	outbox[unsavedKey(songId, field)] = { song: String(songId), field: field, value: value };
	writeUnsaved(outbox);
}

/// Only while it still holds that value: a newer edit, here or in another tab, stays queued.
function unqueueRating(songId, field, value) {
	const outbox = readUnsaved();
	const key = unsavedKey(songId, field);

	if (outbox[key] && outbox[key].value === value) {
		delete outbox[key];
		writeUnsaved(outbox);
	}
}

/// On both copies of the cell, so the poll overwrites neither while a write is out or queued.
function holdRatingCell(songId, field, unsaved) {
	syncField(songId, field + "_" + songAccountId, cell => {
		cell.dataset.pending = "1";
		if (unsaved) {
			cell.classList.add("songCellUnsaved");
		}
	});
}

function releaseRatingCell(songId, field) {
	syncField(songId, field + "_" + songAccountId, cell => {
		delete cell.dataset.pending;
		cell.classList.remove("songCellUnsaved");
	});
}

/// "Artist — Title", as musicSongLabel() writes it for the poll's toasts.
function songLabelOf(songId) {
	const pair = rowsBySongId.get(String(songId));
	const artist = cellText(fieldCell(pair.tr, "artist")).trim();
	const title = cellText(fieldCell(pair.tr, "title")).trim();

	return artist === "" ? title : artist + " — " + title;
}

/// Once per song, and the logged-out one once per page: the Result column is off-screen.
function toastUnsavedRating(songId, login) {
	const toastKey = login ? "login" : String(songId);
	if (!rowsBySongId.has(String(songId)) || unsavedToasted.has(toastKey)) {
		return;
	}
	unsavedToasted.add(toastKey);

	if (login) {
		showToast(container => container.textContent = t("song.list.ratingQueuedLogin"), songId);
		return;
	}

	const label = songLabelOf(songId);
	showToast(container => fillFromTemplate(container, t("song.list.toastRatingQueued"), { song: () => toastStrong(label) }), songId);
}

/* One rating write, which never rejects. Resolves to "saved", "refused" (the
   server said no, so resending cannot help), "offline" (no answer, so it stays
   queued) or "login" (logged out, or a token only a fresh page load replaces).
   `original` is what to show if refused; null leaves the cell as it is. */
function sendRating(songId, field, value, original) {
	const key = unsavedKey(songId, field);
	const spec = RATING_SPECS[field];
	const domField = field + "_" + songAccountId;
	const controller = new AbortController();
	const timeout = setTimeout(() => controller.abort(), RATING_SAVE_TIMEOUT);

	ratingsInFlight.set(key, value);
	holdRatingCell(songId, field, false);

	return postJson(RATING_ENDPOINT, { id: songId, field: field, value: value }, { signal: controller.signal })
	.then(result => {
		unqueueRating(songId, field, value);
		// Straight away rather than on the next poll tick.
		if (result.stats) {
			applySongStats(result.stats);
		}

		const saved = result.status === "ok";
		const shown = result.value === undefined ? original : String(result.value);

		/// A newer edit is queued behind this one, and the cell is showing that.
		if (!readUnsaved()[key]) {
			releaseRatingCell(songId, field);
			if (shown !== null) {
				syncField(songId, domField, target => setCellValue(target, spec, shown));
			}
			syncResult(songId, saved ? "" : result.message);
			unsavedToasted.delete(String(songId));
		}

		/// What the write changed besides this cell: borrowed ratings here and on the songs it lends to.
		(result.ratings || []).forEach(applyRatingChange);
		(result.relatedStats || []).forEach(applySongStats);

		return saved ? "saved" : "refused";
	})
	.catch(error => {
		const status = error.status || 0;
		const login = status === 401 || status === 403;

		if (status >= 400 && status < 500 && !login) {
			unqueueRating(songId, field, value);
			if (!readUnsaved()[key]) {
				releaseRatingCell(songId, field);
				if (original !== null) {
					syncField(songId, domField, target => setCellValue(target, spec, original));
				}
				syncResult(songId, t("status.submitFailed", { error: error.message }));
			}
			return "refused";
		}

		holdRatingCell(songId, field, true);
		toastUnsavedRating(songId, login);

		if (login) {
			ratingResendBlocked = true;
			clearTimeout(ratingResendTimer);
			syncResult(songId, t("song.list.ratingQueuedLogin"));
			return "login";
		}

		syncResult(songId, t("song.list.ratingQueued"));
		return "offline";
	})
	.finally(() => {
		clearTimeout(timeout);
		if (ratingsInFlight.get(key) === value) {
			ratingsInFlight.delete(key);
		}
	});
}

function saveRating(songId, field, value, original) {
	queueRating(songId, field, value);

	sendRating(songId, field, value, original).then(outcome => {
		if (outcome === "offline") {
			scheduleRatingResend();
		}
	});
}

function scheduleRatingResend() {
	if (ratingResendBlocked) {
		return;
	}

	clearTimeout(ratingResendTimer);
	ratingResendTimer = setTimeout(flushUnsavedRatings, ratingResendDelay);
}

/// Every queued value shown and marked, and any mark whose entry has gone (sent, perhaps by another tab) lifted.
function showUnsaved(outbox) {
	document.querySelectorAll(".songCellUnsaved").forEach(cell => {
		const key = unsavedKey(cell.closest("[data-song-id]").dataset.songId, cell.dataset.field.split("_")[0]);

		if (!outbox[key] && !ratingsInFlight.has(key)) {
			delete cell.dataset.pending;
			cell.classList.remove("songCellUnsaved");
		}
	});

	Object.values(outbox).forEach(entry => {
		if (ratingsInFlight.has(unsavedKey(entry.song, entry.field))) {
			return;
		}

		syncField(entry.song, entry.field + "_" + songAccountId, cell => {
			if (!isBeingEdited(cell) && cellText(cell) !== entry.value) {
				setCellValue(cell, RATING_SPECS[entry.field], entry.value);
			}
		});
		holdRatingCell(entry.song, entry.field, true);
	});
}

/// One at a time, stopping at the first that gets no answer: the rest would only time out behind it.
function flushUnsavedRatings() {
	if (ratingResendBlocked) {
		return;
	}
	if (ratingResendRunning) {
		ratingResendAgain = true;
		return;
	}
	clearTimeout(ratingResendTimer);

	const outbox = readUnsaved();
	showUnsaved(outbox);

	const entries = Object.values(outbox);
	if (entries.length === 0) {
		ratingResendDelay = RATING_RESEND_MIN;
		return;
	}

	ratingResendRunning = true;

	const sendFrom = index => {
		if (index >= entries.length) {
			return Promise.resolve("saved");
		}

		const entry = entries[index];
		const key = unsavedKey(entry.song, entry.field);
		const current = readUnsaved()[key];

		/// Sent or replaced since this run read the outbox, or already on its way.
		if (!current || current.value !== entry.value || ratingsInFlight.get(key) === entry.value) {
			return sendFrom(index + 1);
		}

		return sendRating(entry.song, entry.field, entry.value, null)
		.then(outcome => outcome === "offline" || outcome === "login" ? outcome : sendFrom(index + 1));
	};

	sendFrom(0).then(outcome => {
		ratingResendRunning = false;

		if (outcome === "offline") {
			ratingResendDelay = Math.min(ratingResendDelay * 2, RATING_RESEND_MAX);
			scheduleRatingResend();
		}
		else {
			ratingResendDelay = RATING_RESEND_MIN;
			if (ratingResendAgain) {
				scheduleRatingResend();
			}
		}

		ratingResendAgain = false;
	});
}

window.addEventListener("online", flushUnsavedRatings);

/// Only when storage refused; otherwise the outbox outlives the page and goes on the next visit.
window.addEventListener("beforeunload", event => {
	if (unsavedMemory && Object.keys(unsavedMemory).length > 0) {
		event.preventDefault();
		event.returnValue = "";
	}
});

/// Whatever a previous visit could not send: shown, marked, and sent.
flushUnsavedRatings();

// A link straight to a song's card, last so the rows are paired and filtered.
const linkedSongCard = cardLinkValue("songCard");
if (linkedSongCard !== null) {
	openCardModal(linkedSongCard);

	/// Nothing opened, so drop the parameter rather than let it claim a card.
	if (songCardModal.hidden) {
		writeCardLink(SONG_CARD_LINK_PARAMS, null);
	}
}
