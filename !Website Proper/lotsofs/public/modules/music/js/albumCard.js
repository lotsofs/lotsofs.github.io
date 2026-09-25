const albumCardModal = document.getElementById("albumCardModal");
const albumCardModalBody = document.getElementById("albumCardModalBody");
const albumCardModalClose = document.getElementById("albumCardModalClose");

const ALBUM_CARD_ENDPOINT = "/music/ajax/album-card";
const ALBUM_OPTIONS_ENDPOINT = "/music/ajax/album-options";
const ALBUM_EDIT_ENDPOINT = "/music/ajax/album-edit";
const ALBUM_TRACK_ENDPOINT = "/music/ajax/album-track";

// Cards are always fetched fresh rather than cached: the song list can move a
// song on or off an album while the page is open, which would leave a cached
// track list quietly wrong. Leaving edit mode re-fetches for the same reason -
// it is how the track titles, the artist name and the averages catch up with
// whatever was just changed, instead of every editor having to patch them.
let albumCardRequest = 0;
let albumCardArtists = [];
let albumCardSongs = [];
let albumCardTrackAliases = {};

// The dropdown option lists are the whole catalogue and have nothing to do with
// any one album, so they are fetched on the first Edit click and kept for the
// rest of the page - not sent with every card. Opening ten cards to read them
// now costs nothing, where before each open re-sent every song in the library.
let albumCardOptions = null;

// The graph order is a reading preference, not a property of the album, so it
// survives closing one card and opening another. The server does the sorting -
// re-rendering costs one small request and keeps the svg geometry in one place
// rather than reimplementing the run-splitting in JS.
let albumGraphSort = "";
let albumGraphDir = "";

function loadAlbumOptions() {
	if (!albumCardOptions) {
		albumCardOptions = postJson(ALBUM_OPTIONS_ENDPOINT, {})
			.then(result => {
				albumCardArtists = result.artists || [];
				albumCardSongs = result.songs || [];
			})
			.catch(error => {
				// Left unset so the next Edit click tries again rather than
				// opening an editor with empty dropdowns.
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

/// keepPlace is for a re-render of the card already on screen - reordering the
/// graph - where replacing the body would otherwise throw the reader back to
/// the top of a card they had scrolled down through to reach the dropdown.
function openAlbumCard(albumId, keepPlace) {
	const request = ++albumCardRequest;
	const dialog = albumCardModal.querySelector(".cardModalDialog");
	const scrollTop = keepPlace && dialog ? dialog.scrollTop : 0;

	if (!keepPlace) {
		albumCardMessage(t("album.card.loading"));
	}
	albumCardModal.hidden = false;

	postJson(ALBUM_CARD_ENDPOINT, { album_id: Number(albumId), sort: albumGraphSort, dir: albumGraphDir })
		.then(result => {
			if (request !== albumCardRequest) {
				return;
			}
			if (result.status !== "ok") {
				albumCardMessage(result.message);
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

// A track is identified by its song: album_track holds at most one row per
// (album, song), so changing a row's dropdown is a remove of the old song
// followed by an add of the new one at the same position.
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

	// Track numbers are what the list is ordered by, so a renumbered row moves
	// to where its new number puts it rather than staying where it was typed.
	// Rows with no number at all sink to the bottom, the same place the server
	// renders them.
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

// Changing the key resets the direction to that order's natural one - high to
// low for a score, first to last for a running order - which is almost always
// what you meant, and the direction select is right there to flip it.
albumCardModalBody.addEventListener("change", event => {
	const key = event.target.closest(".albumGraphSortKey");
	const dir = event.target.closest(".albumGraphSortDir");

	if (!key && !dir) {
		return;
	}

	const card = event.target.closest(".albumCard");
	const keySelect = albumCardModalBody.querySelector(".albumGraphSortKey");
	const dirSelect = albumCardModalBody.querySelector(".albumGraphSortDir");

	albumGraphSort = keySelect ? keySelect.value : "";
	albumGraphDir = key
		? (keySelect.selectedOptions[0].dataset.defaultDir || "")
		: (dirSelect ? dirSelect.value : "");

	openAlbumCard(card.dataset.albumId, true);
});

albumCardModalBody.addEventListener("click", event => {
	const button = event.target.closest(".albumCardEditBtn");
	if (!button) {
		return;
	}

	const card = button.closest(".albumCard");
	if (card.dataset.editing === "1") {
		openAlbumCard(card.dataset.albumId);
		return;
	}

	button.disabled = true;
	loadAlbumOptions()
		.then(() => {
			button.disabled = false;
			// The card can have been closed, or replaced by another album's,
			// while the catalogue was on its way.
			if (card.isConnected) {
				enterAlbumEditMode(card);
			}
		})
		.catch(error => {
			button.disabled = false;
			albumCardStatus(card, t("status.submitFailed", { error: error.message }));
		});
});

// One delegated listener covers every album name on the page - the song table,
// the song cards (including the one the song modal has moved into itself) and
// the album list - because they all carry data-album-card-id. The href stays a
// real link to that album's songs, so modified clicks and middle clicks still
// navigate the way they did before.
document.addEventListener("click", event => {
	if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
		return;
	}

	if (event.target.closest("input, select, textarea, button")) {
		return;
	}

	// The whole album cell opens the card, not only the name inside it: the
	// column is narrow enough that the name is usually ellipsed, which leaves
	// a very small thing to hit. A click on a name still opens that name - the
	// cell only answers for clicks that miss, and then it opens the first
	// album, which for all but a handful of songs is the only one.
	let trigger = event.target.closest("[data-album-card-id]");

	if (!trigger) {
		const cell = event.target.closest(".songAlbumCell");
		trigger = cell ? cell.querySelector("[data-album-card-id]") : null;
	}

	if (!trigger) {
		return;
	}

	event.preventDefault();
	openAlbumCard(trigger.dataset.albumCardId);
});

albumCardModalClose.addEventListener("click", closeAlbumCard);

albumCardModal.addEventListener("click", event => {
	if (event.target === albumCardModal) {
		closeAlbumCard();
	}
});

/// Capture phase, and the event stops here: on the song list this modal opens
/// on top of the song card modal, whose own Escape handler would otherwise
/// close that one too and leave the album card floating over a closed list.
document.addEventListener("keydown", event => {
	if (albumCardModal.hidden || event.key !== "Escape") {
		return;
	}

	event.stopPropagation();
	closeAlbumCard();
}, true);
