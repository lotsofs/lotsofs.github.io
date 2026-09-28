const albumCardModal = document.getElementById("albumCardModal");
const albumCardModalBody = document.getElementById("albumCardModalBody");
const albumCardModalClose = document.getElementById("albumCardModalClose");

const ALBUM_OPTIONS_ENDPOINT = "/music/ajax/album-options";
const ALBUM_EDIT_ENDPOINT = "/music/ajax/album-edit";
const ALBUM_TRACK_ENDPOINT = "/music/ajax/album-track";
const ARTIST_EDIT_ENDPOINT = "/music/ajax/artist-edit";

// One modal, two kinds of card: the endpoint, trigger and parameter names of each.
const CARD_KINDS = {
	album: { endpoint: "/music/ajax/album-card", trigger: "[data-album-card-id]", dataset: "albumCardId", param: "album_id", link: "albumCard" },
	artist: { endpoint: "/music/ajax/artist-card", trigger: "[data-artist-card-id]", dataset: "artistCardId", param: "artist_id", link: "artistCard" },
};

// Cards are always fetched fresh rather than cached, edit mode included.
let albumCardRequest = 0;
let albumCardArtists = [];
let albumCardSongs = [];
let albumCardTrackAliases = {};

// The edit dropdowns' catalogue, fetched on the first Edit click and kept.
let albumCardOptions = null;

// The graph order, held for the page session and sent with every card request.
let albumGraphSort = "";
let albumGraphDir = "";

// Whose scores the statistics are read as, held the same way. "" is everyone.
let albumStatsWho = "";

function loadAlbumOptions() {
	if (!albumCardOptions) {
		albumCardOptions = postJson(ALBUM_OPTIONS_ENDPOINT, {})
			.then(result => {
				albumCardArtists = result.artists || [];
				albumCardSongs = result.songs || [];
			})
			.catch(error => {
				// Left unset so the next Edit click tries again.
				albumCardOptions = null;
				throw error;
			});
	}

	return albumCardOptions;
}

function albumCardMessage(text) {
	const message = document.createElement("p");
	message.className = "albumCardMessage";
	message.textContent = text;

	albumCardModalBody.textContent = "";
	albumCardModalBody.appendChild(message);
}

/// keepPlace re-renders the card on screen, holding the dialog's scroll position.
function openAlbumCard(kind, cardId, keepPlace) {
	const request = ++albumCardRequest;
	const dialog = albumCardModal.querySelector(".cardModalDialog");
	const scrollTop = keepPlace && dialog ? dialog.scrollTop : 0;
	const spec = CARD_KINDS[kind];

	if (!keepPlace) {
		albumCardMessage(t("album.card.loading"));
	}
	albumCardModal.hidden = false;

	/// Written before the fetch; a card that does not exist clears it again below.
	writeCardLink(CARD_LINK_MODAL_PARAMS, spec.link, Number(cardId));

	postJson(spec.endpoint, { [spec.param]: Number(cardId), sort: albumGraphSort, dir: albumGraphDir, who: albumStatsWho })
		.then(result => {
			if (request !== albumCardRequest) {
				return;
			}
			if (result.status !== "ok") {
				albumCardMessage(result.message);
				writeCardLink(CARD_LINK_MODAL_PARAMS, null);
				return;
			}
			albumCardTrackAliases = result.trackAliases || {};
			albumCardModalBody.innerHTML = result.html;

			if (dialog) {
				dialog.scrollTop = scrollTop;
			}
		})
		.catch(error => {
			if (request !== albumCardRequest) {
				return;
			}
			albumCardMessage(t("album.card.loadFailed", { error: error.message }));
		});
}

function closeAlbumCard() {
	albumCardRequest++;
	albumCardModal.hidden = true;
	albumCardModalBody.textContent = "";
	writeCardLink(CARD_LINK_MODAL_PARAMS, null);
}

function albumCardField(card, field) {
	return card.querySelector('[data-field="' + field + '"]');
}

function albumCardStatus(card, message) {
	const cell = albumCardField(card, "status");
	if (cell) {
		cell.textContent = message;
	}
}

function albumOptionLabel(option) {
	return option.name === null ? "—" : option.name;
}

function albumSongLabel(song) {
	return albumOptionLabel(song) + (song.artist ? " — " + song.artist : "");
}

const albumNameEditor = {
	enter(card) {
		const cell = albumCardField(card, "name");
		cell.textContent = "";

		const input = document.createElement("input");
		input.type = "text";
		input.className = "albumEditName";
		input.value = card.dataset.albumName || "";

		input.addEventListener("blur", () => {
			const value = input.value.trim();
			if (value === (card.dataset.albumName || "")) {
				return;
			}

			input.disabled = true;
			postJson(ALBUM_EDIT_ENDPOINT, { album_id: card.dataset.albumId, field: "name", value })
				.then(result => {
					input.disabled = false;
					if (result.status === "ok") {
						card.dataset.albumName = result.value;
						albumCardStatus(card, "");
					}
					else {
						albumCardStatus(card, result.message);
					}
					input.value = card.dataset.albumName || "";
				})
				.catch(error => {
					input.disabled = false;
					input.value = card.dataset.albumName || "";
					albumCardStatus(card, t("status.submitFailed", { error: error.message }));
				});
		});

		cell.appendChild(input);
	},
};

const albumArtistEditor = {
	enter(card) {
		const cell = albumCardField(card, "artist");
		cell.textContent = "";

		const select = document.createElement("select");
		select.add(new Option("—", ""));
		albumCardArtists.forEach(artist => select.add(new Option(albumOptionLabel(artist), String(artist.id))));
		select.value = card.dataset.artistId || "";

		select.addEventListener("change", () => {
			select.disabled = true;
			postJson(ALBUM_EDIT_ENDPOINT, { album_id: card.dataset.albumId, field: "artist", value: select.value })
				.then(result => {
					select.disabled = false;
					if (result.status === "ok") {
						card.dataset.artistId = result.value === null ? "" : String(result.value);
						albumCardStatus(card, "");
					}
					else {
						select.value = card.dataset.artistId || "";
						albumCardStatus(card, result.message);
					}
				})
				.catch(error => {
					select.disabled = false;
					select.value = card.dataset.artistId || "";
					albumCardStatus(card, t("status.submitFailed", { error: error.message }));
				});
		});

		cell.appendChild(select);
	},
};

const albumYearEditor = {
	enter(card) {
		const cell = albumCardField(card, "year");
		cell.textContent = "";

		const input = document.createElement("input");
		input.type = "text";
		input.className = "albumEditYear";
		input.value = card.dataset.releaseYear || "";

		input.addEventListener("blur", () => {
			const value = input.value.trim();
			if (value === (card.dataset.releaseYear || "")) {
				return;
			}

			input.disabled = true;
			postJson(ALBUM_EDIT_ENDPOINT, { album_id: card.dataset.albumId, field: "year", value })
				.then(result => {
					input.disabled = false;
					if (result.status === "ok") {
						card.dataset.releaseYear = result.value === null ? "" : String(result.value);
						albumCardStatus(card, "");
					}
					else {
						albumCardStatus(card, result.message);
					}
					input.value = card.dataset.releaseYear || "";
				})
				.catch(error => {
					input.disabled = false;
					input.value = card.dataset.releaseYear || "";
					albumCardStatus(card, t("status.submitFailed", { error: error.message }));
				});
		});

		cell.appendChild(input);
	},
};

// A track is identified by its song, so changing one is a remove then an add.
const albumTrackEditor = {
	enter(card) {
		const cell = albumCardField(card, "tracks");
		const existing = Array.from(cell.querySelectorAll(".albumTrack")).map(track => ({
			songId: track.dataset.songId,
			position: track.dataset.position || "",
			aliasId: track.dataset.songAliasId || "",
		}));

		cell.textContent = "";

		const list = document.createElement("div");
		list.className = "albumEditTrackList";

		existing.forEach(track => list.appendChild(albumTrackEditor.buildRow(card, track.songId, track.position, track.aliasId)));

		const add = document.createElement("button");
		add.type = "button";
		add.className = "albumEditTrackAdd";
		add.textContent = "+";
		add.title = t("album.card.addTrack");
		add.addEventListener("click", () => list.insertBefore(albumTrackEditor.buildRow(card, null, "", ""), add));

		list.appendChild(add);
		cell.appendChild(list);
	},

	// Reorders the rows by track number, unnumbered ones last.
	sort(row) {
		const list = row.closest(".albumEditTrackList");
		if (!list) {
			return;
		}

		const add = list.querySelector(".albumEditTrackAdd");
		const rows = Array.from(list.querySelectorAll(".albumEditTrackRow"));
		const key = candidate => candidate.dataset.position ? Number(candidate.dataset.position) : Number.MAX_SAFE_INTEGER;

		rows.sort((a, b) => key(a) - key(b));
		rows.forEach(candidate => list.insertBefore(candidate, add));
	},

	buildRow(card, songId, position, aliasId) {
		const row = document.createElement("div");
		row.className = "albumEditTrackRow";
		row.dataset.position = position;
		if (songId) {
			row.dataset.songId = songId;
		}

		const positionInput = document.createElement("input");
		positionInput.type = "text";
		positionInput.className = "albumEditTrackPosition";
		positionInput.value = position;

		const select = document.createElement("select");
		select.add(new Option("—", ""));
		albumCardSongs.forEach(song => select.add(new Option(albumSongLabel(song), String(song.id))));
		select.value = songId || "";

		const alias = document.createElement("select");
		alias.className = "albumEditTrackAlias";
		alias.title = t("album.card.aliasHint");

		function fillAliases(aliases, selected) {
			alias.textContent = "";
			alias.add(new Option(t("album.card.aliasDefault"), ""));
			(aliases || []).forEach(option => alias.add(new Option(option.name, String(option.id))));
			alias.value = selected || "";
			alias.disabled = !row.dataset.songId;
		}

		fillAliases(albumCardTrackAliases[songId] || [], aliasId);

		const remove = document.createElement("button");
		remove.type = "button";
		remove.className = "albumEditTrackRemove";
		remove.textContent = "−";
		remove.title = t("album.card.removeTrack");

		function post(body) {
			return postJson(ALBUM_TRACK_ENDPOINT, Object.assign({ album_id: card.dataset.albumId }, body));
		}

		function failed(error) {
			albumCardStatus(card, t("status.submitFailed", { error: error.message }));
		}

		positionInput.addEventListener("blur", () => {
			const value = positionInput.value.trim();
			if (!row.dataset.songId || value === (row.dataset.position || "")) {
				row.dataset.position = value;
				return;
			}

			positionInput.disabled = true;
			post({ song_id: Number(row.dataset.songId), action: "position", position: value })
				.then(result => {
					positionInput.disabled = false;
					if (result.status === "ok") {
						row.dataset.position = value;
						albumTrackEditor.sort(row);
						albumCardStatus(card, "");
					}
					else {
						positionInput.value = row.dataset.position || "";
						albumCardStatus(card, result.message);
					}
				})
				.catch(error => {
					positionInput.disabled = false;
					positionInput.value = row.dataset.position || "";
					failed(error);
				});
		});

		select.addEventListener("change", () => {
			const oldId = row.dataset.songId || null;
			const newId = select.value || null;

			if (newId === oldId) {
				return;
			}

			select.disabled = true;

			const attach = () => {
				if (!newId) {
					delete row.dataset.songId;
					select.disabled = false;
					fillAliases([], "");
					return;
				}

				post({ song_id: Number(newId), action: "add", position: positionInput.value.trim() })
					.then(result => {
						select.disabled = false;
						if (result.status === "ok" || result.status === "duplicate") {
							row.dataset.songId = newId;
							row.dataset.position = positionInput.value.trim();
							albumCardTrackAliases[newId] = result.aliases || [];
							fillAliases(result.aliases, "");
							albumTrackEditor.sort(row);
							albumCardStatus(card, result.status === "duplicate" ? result.message : "");
						}
						else {
							select.value = oldId || "";
							albumCardStatus(card, result.message);
						}
					})
					.catch(error => {
						select.disabled = false;
						select.value = oldId || "";
						failed(error);
					});
			};

			if (oldId) {
				post({ song_id: Number(oldId), action: "remove" })
					.then(attach)
					.catch(error => {
						select.disabled = false;
						select.value = oldId;
						failed(error);
					});
			}
			else {
				attach();
			}
		});

		alias.addEventListener("change", () => {
			if (!row.dataset.songId) {
				return;
			}

			const chosen = alias.value;
			alias.disabled = true;
			post({ song_id: Number(row.dataset.songId), action: "alias", song_alias_id: chosen })
				.then(result => {
					alias.disabled = false;
					if (result.status === "ok") {
						row.dataset.songAliasId = chosen;
						albumCardStatus(card, "");
					}
					else {
						alias.value = row.dataset.songAliasId || "";
						albumCardStatus(card, result.message);
					}
				})
				.catch(error => {
					alias.disabled = false;
					alias.value = row.dataset.songAliasId || "";
					failed(error);
				});
		});

		remove.addEventListener("click", () => {
			if (!row.dataset.songId) {
				row.remove();
				return;
			}

			remove.disabled = true;
			post({ song_id: Number(row.dataset.songId), action: "remove" })
				.then(() => row.remove())
				.catch(error => {
					remove.disabled = false;
					failed(error);
				});
		});

		row.dataset.songAliasId = aliasId || "";
		row.append(positionInput, select, alias, remove);
		return row;
	},
};

// An artist's whole edit mode: the title turned into a text box.
const artistNameEditor = {
	enter(card) {
		const cell = albumCardField(card, "name");
		cell.textContent = "";

		const input = document.createElement("input");
		input.type = "text";
		input.className = "albumEditName";
		input.value = card.dataset.artistName || "";

		input.addEventListener("blur", () => {
			const value = input.value.trim();
			if (value === (card.dataset.artistName || "")) {
				return;
			}

			input.disabled = true;
			postJson(ARTIST_EDIT_ENDPOINT, { artist_id: card.dataset.cardId, field: "name", value })
				.then(result => {
					input.disabled = false;
					if (result.status === "ok") {
						card.dataset.artistName = result.value;
						albumCardStatus(card, "");
					}
					else {
						albumCardStatus(card, result.message);
					}
					input.value = card.dataset.artistName || "";
				})
				.catch(error => {
					input.disabled = false;
					input.value = card.dataset.artistName || "";
					albumCardStatus(card, t("status.submitFailed", { error: error.message }));
				});
		});

		cell.appendChild(input);
	},
};

function enterArtistEditMode(card) {
	card.dataset.editing = "1";

	const button = card.querySelector(".cardEditBtn");
	if (button) {
		button.textContent = t("album.card.done");
	}

	artistNameEditor.enter(card);
}

function enterAlbumEditMode(card) {
	card.dataset.editing = "1";

	const button = card.querySelector(".albumCardEditBtn");
	if (button) {
		button.textContent = t("album.card.done");
	}

	albumNameEditor.enter(card);
	albumArtistEditor.enter(card);
	albumYearEditor.enter(card);
	albumTrackEditor.enter(card);
}

// The three reading controls; a new order key resets the direction to its default.
albumCardModalBody.addEventListener("change", event => {
	const key = event.target.closest(".albumGraphSortKey");
	const dir = event.target.closest(".albumGraphSortDir");
	const who = event.target.closest(".cardStatsWho");

	if (!key && !dir && !who) {
		return;
	}

	const card = event.target.closest("[data-card-kind]");

	if (who) {
		albumStatsWho = who.value;
	}
	else {
		const keySelect = albumCardModalBody.querySelector(".albumGraphSortKey");
		const dirSelect = albumCardModalBody.querySelector(".albumGraphSortDir");

		albumGraphSort = keySelect ? keySelect.value : "";
		albumGraphDir = key
			? (keySelect.selectedOptions[0].dataset.defaultDir || "")
			: (dirSelect ? dirSelect.value : "");
	}

	openAlbumCard(card.dataset.cardKind, card.dataset.cardId, true);
});

// The wheel steps the selects marked cardWheelSelect, never the editor's.
const CARD_WHEEL_GAP = 60;
const CARD_WHEEL_SETTLE = 250;

let cardWheelAt = 0;
let cardWheelTimer = null;

albumCardModalBody.addEventListener("wheel", event => {
	const select = event.target.closest("select.cardWheelSelect");
	if (!select || event.deltaY === 0) {
		return;
	}

	const next = select.selectedIndex + (event.deltaY > 0 ? 1 : -1);

	/// Past either end the card scrolls instead.
	if (next < 0 || next >= select.options.length) {
		return;
	}

	event.preventDefault();

	const now = Date.now();
	if (now - cardWheelAt < CARD_WHEEL_GAP) {
		return;
	}
	cardWheelAt = now;

	select.selectedIndex = next;
	const chosen = select.value;

	clearTimeout(cardWheelTimer);
	cardWheelTimer = setTimeout(() => {
		/// The card may have re-rendered; its replacement has the same id.
		const live = select.isConnected ? select : albumCardModalBody.querySelector("#" + select.id);
		if (!live) {
			return;
		}

		live.value = chosen;
		live.dispatchEvent(new Event("change", { bubbles: true }));
	}, CARD_WHEEL_SETTLE);
}, { passive: false });

albumCardModalBody.addEventListener("click", event => {
	const button = event.target.closest(".cardEditBtn");
	if (!button) {
		return;
	}

	const card = button.closest("[data-card-kind]");
	if (card.dataset.editing === "1") {
		openAlbumCard(card.dataset.cardKind, card.dataset.cardId);
		return;
	}

	// An artist needs no catalogue to edit, so it skips the fetch.
	if (card.dataset.cardKind === "artist") {
		enterArtistEditMode(card);
		return;
	}

	button.disabled = true;
	loadAlbumOptions()
		.then(() => {
			button.disabled = false;
			// The card may have been closed while the catalogue was on its way.
			if (card.isConnected) {
				enterAlbumEditMode(card);
			}
		})
		.catch(error => {
			button.disabled = false;
			albumCardStatus(card, t("status.submitFailed", { error: error.message }));
		});
});

// One delegated listener for every album and artist name on the page.
const CARD_CELLS = { ".songAlbumCell": "album", ".songArtistCell": "artist" };

document.addEventListener("click", event => {
	if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
		return;
	}

	if (event.target.closest("input, select, textarea, button")) {
		return;
	}

	for (const [kind, spec] of Object.entries(CARD_KINDS)) {
		const named = event.target.closest(spec.trigger);
		if (named) {
			event.preventDefault();
			openAlbumCard(kind, named.dataset[spec.dataset]);
			return;
		}
	}

	for (const [selector, kind] of Object.entries(CARD_CELLS)) {
		const cell = event.target.closest(selector);
		const first = cell ? cell.querySelector(CARD_KINDS[kind].trigger) : null;
		if (first) {
			event.preventDefault();
			openAlbumCard(kind, first.dataset[CARD_KINDS[kind].dataset]);
			return;
		}
	}
});

albumCardModalClose.addEventListener("click", closeAlbumCard);

albumCardModal.addEventListener("click", event => {
	if (event.target === albumCardModal) {
		closeAlbumCard();
	}
});

/// Capture phase and stopped here, or the song modal's Escape closes that too.
document.addEventListener("keydown", event => {
	if (albumCardModal.hidden || event.key !== "Escape") {
		return;
	}

	event.stopPropagation();
	closeAlbumCard();
}, true);

// A link straight to a card.
for (const [kind, spec] of Object.entries(CARD_KINDS)) {
	const linked = cardLinkValue(spec.link);
	if (linked !== null) {
		openAlbumCard(kind, linked);
		break;
	}
}
