# Music front end

Scripts in `public/modules/music/js/`. All are classic browser scripts sharing one global scope; there is no bundler and nothing here is executed by the test suite. `songs.js` alone is ~1,700 lines, `addSongs.js` ~1,200, `albumCard.js` ~690.

## Which script is on which page

| Page | Scripts (in load order) |
|---|---|
| every music page | `util.js` (head, from `/js/`), `nav.js`, `colour.js` (from `nav.php`), `tooltip.js` (from `foot.php`) |
| `/music/songs` | `cardLink.js`, `albumCard.js` (both via `albumCardModal.php`, **before**) then `songs.js` |
| `/music/artists`, `/music/albums` | `cardLink.js`, `albumCard.js` only (no `songs.js`; the list pages are static tables) |
| `/music/add-songs` | `addSongs.js` |

`songs.js` calls `writeCardLink`/`cardLinkValue`/`CARD_LINK_PARAMS` from `cardLink.js` and relies on `albumCard.js`'s delegated click listener, so the order is load-bearing (a test checks it). Do not give two scripts the same top-level `const` name: they share one scope (`albumCard.js` and `songs.js` each define their own modal consts under distinct names).

## Small scripts

- **`nav.js`**: `ResizeObserver` on `<nav>` publishing `--navHeight` (via `getBoundingClientRect().height`) to `<html>`; also run once synchronously.
- **`colour.js`**: live preview of the hue slider (`#navColourInput`) writing `--hue` on `<html>`; reverts to the saved hue if the `<details>` menu closes without saving.
- **`tooltip.js`**: delegated `mouseover/out/move` over `#musicTooltip`. Targets: `[data-tooltip]`, or any `[title]` inside a `[data-tooltip-titles]` container (the title is *moved* into `data-tooltip` on first hover; an SVG child `<title>` likewise). `musicTooltip.link(el, text)` for imperative use. Text is `pre-line` so `\n` breaks lines. Hidden on any scroll (capture). Code that rewrites a cell's tooltip must write `data-tooltip` if the element has one, else `title`.
- **`cardLink.js`**: `CARD_LINK_PARAMS = [songCard, albumCard, artistCard]`, `CARD_LINK_MODAL_PARAMS = [albumCard, artistCard]`, `writeCardLink(ownedParams, param|null, id)` (deletes the owned params, sets one, `history.replaceState`), `cardLinkValue(param)` (digits only or null).

## `songs.js` anatomy

Top to bottom (line numbers approximate):

1. **State and DOM handles (1–25).** `songSort`/`songDir` seeded from the URL; JSON blobs from the page (`songArtistData`, `songAlbumData`, `songLinkFieldData`, `songTrackAliases`).
2. **Dual-DOM sync (26–88).** `rowsBySongId` pairs `tbody.children[i]` with `#songCards.children[i]` (positional!). `syncField(songId, field, apply)` applies a change to the cell with that `data-field` in both. `valueElement(cell)` finds the inner text node holder (`.ratingNoteText, .songCellText, .songGateValue`) because widths are enforced on an inner block. `syncResult(songId, msg)` writes the transient Result column/field and toggles `hideResultColumn` on table, card list and modal when nothing is showing a message.
3. **Modal (90–160).** `openCardModal` *moves* the real card node into `#songCardModalBody` and remembers `modalCardReturnAnchor` (the next sibling) so `closeCardModal` can reinsert it in place; refuses hidden cards; `writeCardLink("songCard")`. Prev/next walk visible siblings (`modalNeighbour`). Escape closes; ←/→ step unless focus is in a form control.
4. **Edit-mode editors (163–716).** Admin-only, driven by the card's Edit button (`handleCardEditClick`). `createIdListEditor(config)` builds the shared artist/album multi-select editors (select + remove per row, `+` to add; each change is remove-then-add against `song-artist`/`song-album`). `linkFieldEditor`, `yearFieldEditor`, `durationFieldEditor` swap a field for inputs saving on blur to `song-link`/`song-year`/`song-duration`. Leaving edit mode rebuilds artist/album names as links (`artistNameLink`, `albumNameLink`) so the cards keep opening, and re-renders embeds (`renderSpotifyEmbed`, `renderYoutubeEmbed`, `renderSoundcloudEmbed`, `renderLinkDisplay`). The title is edited through the generic cell editor with `TITLE_SPEC` against `song-edit`. Edits patch only the card, not the table row (a reload picks them up); `data-links`, `data-song-year`, `data-fallback-year`, `data-duration` on the card hold current values for re-render.
5. **Card view toggle (802–822).** `html.songCardView` class, auto-on below 700 px width unless the user toggled manually; a tiny inline script in `songs.view.php` sets it before first paint.
6. **Filter and sort (824–1077).** `songQuery()` rebuilds the address from sort, dir, filters **and the open card params**. `applySongFilter()` hides/shows pairs (`hidden`), `repopulateSongAlbums()` narrows the album select to the chosen artist (or artist-less albums), `applySongTitles()` swaps titles to the album's listed-as alias when an album filter is active, `refreshSongHeading()`. `sortRows()` re-appends paired nodes in new order (blank cells last in both directions, ties by id); `fieldValue()` prefers `data-sort-value`; `compareCells` supports `number`, `duration`, text (case-insensitive). Header links are intercepted and sorted client-side; the mobile sort `<select>`s do the same.
7. **Rating cells (1079–1435).** `EDITABLE_CELLS` maps `.songMyScoreCell`/`.songMyNoteCell` to `song-rating`. `beginCellEdit(cell, spec)` replaces the value with an `<input>`/`<textarea>` (Enter commits, Shift+Enter newline in notes, Escape reverts, blur commits); `saveCell` posts and then writes the server's canonical value and `stats`, or reverts on failure. `cell.dataset.pending` blocks poll overwrites while a save is in flight; `isBeingEdited(cell)` blocks them while typing. `colorScoreCell`/`scoreColour` apply the 0–10 ramp to every `.songScoreColoured` cell.
8. **Blind-rating gate (1110–1252).** `songGateOn` from `#songGateSetting`. `applySpoilerGate(container)` (called from `setCellValue`, the single choke point for value changes) recomputes per row or card: scores gated iff the reader's own score cell is empty, notes gated iff their note cell is empty; `SONG_GATE_CELLS` = other raters' score/note cells + statistic cells except `rated`. `setCellGate` toggles `songGated`, injects/removes the `.songGateReveal` pill, strips tooltip/title and the colour class when covered, restores them when uncovered. Reveals are remembered per page session in `songRevealed` keyed `songId:data-field`. `ownValue()` reads a live input so mid-edit typing does not flicker the gate. The tbody click handler ignores clicks on a pill.
9. **Click routing in the table body (1405–1435).** Title cell → open card; other raters' note cell → open card and flash that note; filepath abbr → open card and flash the chip; otherwise generic edit/Edit-button handling. The same handler is attached to `#songCards` and the modal body.
10. **Live updates (1437–1717).** Poll loop (3 s; after >2 consecutive failures back off to 60 s + up to 15 s jitter; 10 s abort timeout; pauses while the tab is hidden and fires immediately on becoming visible). A tick applies `changes` (rewrites score/note cells for both trees unless pending/editing, flashing them), `stats` (`applySongStats`: writes finished strings into the **table row only**, setting `data-sort-value` for mode), then `events` as toasts. Toasts: `#songToasts` (outside `.songLayout` so it never becomes a positional child), 180 s lifetime, max 140, click opens the song's card. Each toast follows the same gates as the cells (`toastValueCovered`); covered toasts use separate templates because `fillFromTemplate` emits an unmatched `{value}` literally. `TOAST_SHOW_OWN_EVENTS` is deliberately `true` (see TODO).
11. **Deep link (1719–1728).** If `?songCard=` is present, open it last (rows must be paired and filtered first); clear the parameter if nothing opened.

## `albumCard.js` anatomy

One modal (`#albumCardModal`, ids predate the artist card) hosts two card kinds described by `CARD_KINDS` (`album`, `artist`: endpoint, click trigger selector, dataset key, request param name, link param). Key behaviours:

- `openAlbumCard(kind, id, keepPlace)`: posts `{<param>: id, sort, dir, who}` using page-session state `albumGraphSort`, `albumGraphDir`, `albumStatsWho`; a request counter drops stale responses; `keepPlace` re-renders while preserving the dialog's scroll position; writes the card link before fetching and clears it if the card is missing. Cards are never cached.
- Changing the graph order key resets direction to that order's `data-default-dir`; changing "statistics for" re-fetches. All three re-render server-side.
- Edit mode (admin): album → `loadAlbumOptions()` (first click only, cached promise, retried after failure) then `enterAlbumEditMode` (name, artist, year, track editors); artist → `enterArtistEditMode` (name only, no catalogue needed). The editors post to `album-edit`, `album-track`, `artist-edit`. **"Done" re-fetches the card** rather than patching. Track alias dropdowns draw on `trackAliases` from the card response and on the `aliases` returned by `album-track` `add`.
- Wheel over `select.cardWheelSelect` steps the option (debounced, passes through at the ends so the card still scrolls).
- One delegated `document` click listener opens a card from any `[data-album-card-id]` / `[data-artist-card-id]`, or from a `.songAlbumCell` / `.songArtistCell` whose first link carries the id; ignored for modified clicks and clicks on form controls.
- Escape handling is capture-phase and stops propagation so the song modal beneath stays open.
- Deep link on load for `?albumCard=` / `?artistCard=`.

## `addSongs.js` (the import wizard)

Four stages, each a table built from the previous stage's server results, each with its own submit button; once a row is handled it gets `.rowHandled` and is disabled; a failed `buildNext` shows `status.nextStepFailed`.

1. **Paste and column mapping.** `parseRawLines` (tab separated), a mapping row per column with a role `<select>` (`ignore, artist, title, album, track, year, duration, spotify_url, youtube_url, soundcloud_url, bandcamp_url, filepath, other_url`). `guessRoleForColumn` is content-first (`M:SS` → duration; Spotify URL or 22-char id; YouTube URL or 11-char id; soundcloud/bandcamp hosts; any http URL → other) and falls back by position to artist/title/album/track for the first four columns. A role can be used once (`onColumnRoleChange` demotes the previous holder to `ignore`); `artist` and `title` are mandatory. Output: `pastedRows[] = {Artist, Title, Album, Track (int|null), Year, Duration, Links{role: value}}`; rows with no artist are dropped.
2. **Artist match** → `POST artist-alias`. Each distinct pasted artist gets a select (existing artists, skip, new, custom name, or "same as pending row X": resolved recursively by `resolveRowTarget`, depth-limited) and a keep-pasted-spelling checkbox. Results feed `artistIdByProvidedName`.
3. **Song match** → `POST song`. Per row: existing songs of that artist, new, custom name, skip; results (`song_id`, `song_alias_id`) stored in `latestSongResults`, joined back to pasted rows by the key `artist_id + "\t" + provided_name` (`songResultsByKey`).
4. **Extras** (shown only if any row has links/year/duration) → one `song-link`/`song-year`/`song-duration` write per value, in parallel per row. Then **Album** → `POST album`. Extras deliberately precede Album so the confirmed year pre-fills the album year. Track numbers: explicit numbers are kept (including 0 and gaps), blanks take the lowest free positions in paste order (`assignTrackPositions`).

State lives only in the DOM and a few module-level maps; reloading or editing the textarea resets the wizard (`handlePasteInput`).

## Writing front-end changes safely

- Any new per-song value shown in both table and card must carry `data-field` on both cells and be written via `syncField`/`setCellValue`.
- Anything covered by the blind-rating gate must be added to `SONG_GATE_CELLS` *and* gated server-side in `songRows.php`, with a test. Remember the CSS id-prefix rule for hiding.
- New server-rendered cards: add to `CARD_KINDS`, an endpoint, and a partial; reuse the shared chrome classes and `scoreStats.php`/`scoreGraph.php`.
- New user-visible text: catalogue key in all three locales (English required, others may start blank), referenced as a literal `t('key')`.
- You cannot run any of this: re-read the diff carefully for global-name collisions and DOM assumptions, and tell the owner it needs a manual look.
