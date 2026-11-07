# Tasks

Detail for the **Defects and tasks** and **Possible, not planned** rows in [TODO.md](TODO.md), grouped by module in the index's order. Open decisions are in [decisions.md](decisions.md); finished items go to [DONE.md](DONE.md).

Dependencies between the plans:

- hideandseek's `001` freezes the day the module first ships (MIG). CAS, and the table PIN needs, are edits in place before that and table rebuilds after, so they come first. PRV and WHO also want doing before players arrive. Shipping it means taking it off `$unshippedModules` in `build-deploy.php`.
- PAR waits on JST: there is nothing to run a JS test with.
- IRC is most useful after GAM: the Grim Fandango game cues wait on both.
- ALT and GAM are each one more relationship type; doing them together settles `inherits` and the id columns once.

## PWR — No password reset

Music has no password reset. A user who forgets their password stays locked out until someone edits `account.password_hash` in the live database file (mode `0640`) by hand. hideandseek copied music's account system and has none either. Read in the code.

**Plan.** The site has no email, so reset has to go through an admin: an admin action on the accounts page issues a one-time reset code (like an invite code; `inviteCode.php` already generates and normalises those), and the user redeems it on a reset page to set a new password. Hash with `hashPassword()`, never `password_hash()` directly (see `CLAUDE.md`). Clear that account's login rate-limit rows on success. Music's code table goes in the unshipped migration (see `music.md`).

**Verify.** A test that issues a code, resets, logs in with the new password, and refuses the same code a second time.

**Docs.** `music-reference.md` (schema, routes); `hideandseek-reference.md` if hideandseek gets it too.

## TRN — de and fy translations are unreviewed

Every music key is translated into `de` and `fy` (`php tests/i18n-coverage.php`), but all of it is first-draft quality, written by Claude and never checked by a native speaker. `fy` is the site default, so it is what most users read. Start with the invented score-column abbreviations: `Sc.` (en), `Wert.` (de) and `Wurd.` (fy), where `Wurd.` can read as the start of another word. `lang/de/*.php` marks uncertain choices with `// ?` (Interpret or Künstler, Wertung or Punkte, Titel or Tracks, Widerrufen, and others). English stays the source catalogue and the test language. Read in the code.

**Plan.** A native speaker reads each locale in the running site rather than in the PHP files, since context and width matter (the score columns are 2em wide). Fixes go in the `lang/<locale>/*.php` part files; each `// ?` goes once its choice is settled.

**Verify.** `php tests/i18n-coverage.php` stays clean; no `// ?` is left in `lang/de`.

**Docs.** None, unless a term choice becomes a rule worth recording in `music.md`.

## RID — The Related row's song box cannot find a song by its id

In a card's edit mode, a Related row's song is typed into a box whose suggestions are one `<datalist>` of every song on the page, labelled `Artist — Title · Albums` (`relationChoices()` in `songs.js`). A label gets ` #id` only when it would otherwise repeat, and `save()` looks the typed text up as a whole label. So a song cannot be found by its id, either among the suggestions or by typing it. The owner ran into this on 2026-11-07, linking the Director's Cut's Copal's Office and Domino's In Charge to songs 183 and 175, which the import TSV named by id. Seen by the owner; the lookup read in the code.

**Plan.** Put the id in every label, in the shape the import's Song step uses (`139: Title · Album`, `songOptionLabels()` in `addSongs.js`), and keep sorting by artist and title. Have `save()` also accept a bare id that names a song on the page (`relationChoiceLabels` is keyed by id), so typing `183` and leaving the box links it without picking a suggestion.

**Verify.** Headless Chrome and Firefox: typing `183` in a Related row's song box suggests Don Copal, and leaving the box with `183` in it saves the link. A title that two songs share still offers both, each naming one song.

**Docs.** `music.md` (the relationships bullet describes the labels and the ` #id` rule).

## LGO — Logout takes two redirects

`routes/logout.php` always redirects to `/music/songs`, which, with nobody signed in, redirects again to `/music/login`. Read in the code.

**Plan.** Redirect to `/music/login` directly. Keep it unconditional, so a failed CSRF check lands there too.

**Verify.** The logout test in `tests/cases/accounts.php` asserts only the 302; add an assertion on `Location: /music/login`.

**Docs.** `music-reference.md` (the login/logout row).

## REG — Registration misreports database failures

`routes/register.php` inserts the account and spends the invite in one transaction, and its `catch (Throwable $e)` turns every failure into `register.error.badInvite`. A real database error (a locked file, a full disk, a schema mismatch) reaches the user as "invalid invite" and is logged nowhere. hideandseek's `register.php` has the same shape. Read in the code.

**Plan.** Throw a dedicated exception for the already-spent invite and catch only that as a bad invite; let anything else reach normal error handling. Same change in both modules.

**Verify.** The existing invite tests still pass; a forced failure (a temporarily renamed table, say) no longer reads as a bad invite.

**Docs.** None.

## REN — Rename the album card modal

`#albumCardModal`, `partials/albumCardModal.php` and `js/albumCard.js` host the artist card too, so their names undersell them. Read in the code.

**Plan.** Rename to `#cardModal`, `partials/cardModal.php` and `js/cardModal.js`. Mechanical but wide: the views that include the partial, the stylesheet, `songs.js`, and assertions in `tests/cases/albums.php`, `artistcard.php`, `catalogue.php` and `songs.php`. A change of its own, so nothing else hides in the diff.

**Verify.** The suite; `grep -rn albumCardModal app public tests docs` finds nothing.

**Docs.** `music.md` (cards section), `music-frontend.md`, `music-reference.md`.

## LFT — Leftovers from the 2026-09-25 audit

Loose ends from that audit. Read in the code.

- **A song rename does not keep the old name.** `ajax/songEdit.php` promotes an alias the song already has, and otherwise overwrites the current one. `albumEdit.php` keeps the old name as another name instead. The History page records the rename either way (`edit_audit`), but the song no longer answers to its old name. Doing as albums do would be consistent, and an unwanted old name can now be deleted from the song's card.
- **Two SELECTs are written out twice**: the album header in `routes/albums.php` and `ajax/albumCard.php`, and the audit row in `routes/audit.php` and `ajax/songRatingPoll.php`. Sharing them means a shared SQL fragment, which cuts against "SQL lives in route and ajax files" (`conventions.md`).

**Plan.** Make `songEdit.php` keep the old name as an alias. For the SELECTs, decide whether a shared fragment is worth the exception; if not, drop that half of this item.

**Verify.** A test that a rename leaves the old name among the song's aliases.

**Docs.** `music-reference.md` (the `song-edit` row points here); `conventions.md` if the SQL rule changes.

## CHN — Borrowed ratings reach only direct versions

A rater with no rating on a song borrows from the songs a relationship of a lending kind ties it to directly (`song_version` in `ratings.php`). In a chain, a 1998 original, its 2015 remaster and a later remaster of that, the original borrows from the 2015 song only, and the later one's ratings never reach it. Decided for now by the owner (2026-11-05), not a defect. Read in the code.

**Plan.** If wanted, make `song_version` the transitive closure of lending relationships: a recursive CTE (SQLite has had them since 3.8.3, so the host's unknown version is no obstacle), guarding against cycles. Decide whether a farther version should weigh as much in the average as a near one.

**Verify.** A–B–C with only C rated: A shows C's rating, borrowed; the song list, the cards and the poll agree.

**Docs.** `music.md` (Statistics, the borrowed ratings bullet).

## LNK — A relationship change reaches other open pages only on reload

The poll finds changes through `account_song.updated_at`. Linking, unlinking or re-pointing two songs touches no `account_song` row, yet changes what both borrow (`ratings.php`), their statistics, the blind-rating covers and their Related lines. The page that made the change gets all of it back in the `song-relationship` answer; every other open page keeps the old state until it reloads, or until a rating write on one of those songs happens to bring part of it. Read in the code (2026-11-05 review), not reproduced.

**Plan.** Give the poll a second cursor for relationships: a change counter or timestamp the endpoint bumps on add, update and remove (removals have no row left to stamp, so a counter beats a column), and, when it moved, answer with every account's effective ratings, the statistics and the relations of the songs involved, as `song-relationship` already does.

**Verify.** Two sessions on `/music/songs`; linking two songs in one shows the borrowed values, the statistics and the Related lines in the other within a poll tick, and unlinking takes them away.

**Docs.** `music.md` (Shared helpers and live updates; Statistics, the borrowed ratings bullet), `music-reference.md` (`song-rating-poll`).

## OVR — A song card does not show that a rater also rated another version

Every rating cell, in the table and on the song card, lists in its hover tooltip what that rater gave the song's other versions (`refreshVersionTooltips()` in `songs.js`, which sets `data-versions`). Nothing else shows it. A borrowed value at least shows faded, but a card with its own score and note looks the same whether or not the rater also rated the remaster. So nothing invites the hover, and a touch screen has none. Read in the code; asked for by the owner, 2026-11-07.

**Plan.** On the song card, put a small hint after a score or note whose cell has `data-versions` (a mark or a short "also on 1 other version"), carrying the same list as its tooltip. Set and clear it wherever `refreshVersionTooltips()` sets that attribute, so writes, the poll, reveals and relationship changes keep it right. The list already leaves out values the blind-rating gate covers on the other version. Decide whether a covered cell shows the hint: the `rated` count is never covered, so knowing a rating exists gives nothing away. Also decide whether the table gets it, where the 2em score columns have little room.

**Verify.** Headless: the card of a song whose rater rated both versions shows the hint on that rater's score and note. Clearing the other version's rating takes it away. A covered value never shows through the hint.

**Docs.** `music.md` (Statistics, the borrowed-ratings bullet's hover sentence).

## ADO — A borrowed rating cannot be marked as true for this version too

A rater with no rating on a song borrows their ratings on its versions (`effective_rating` in `ratings.php`). The borrowed value is drawn faded and italic (`.songInherited`, opacity 0.65), because nobody wrote it there. So a version the rater has not heard looks the same as one they have heard and found no different from the other, say a remaster they cannot tell from the original. The mark is what sets those two apart. The only way to say "heard it, same verdict" today is typing the same score and note in again, which also stops it following later changes on the other version. Asked for by the owner, 2026-11-07.

**Plan.** A button beside the reader's own borrowed score or note marks it as theirs for this song. All the mark does is change how the value is drawn: upright, and less faded (opacity around 0.85), on every reader's page, since it is the rater's own statement. It stays borrowed. It still follows the other version, counts exactly as it does now, and a real rating still replaces it. A second press takes the mark back.
- **Storage:** a table of its own in the unshipped migration (`010`), one row per account, song and field. Not a column on `account_song`, whose rows mean the rater wrote something there.
- **Logging:** none, since `rating_audit` would turn the mark into a toast.
- **To decide:** whether score and note are marked together or separately, and whether writing a real rating deletes the mark.
- **Updates:** the song list and the rating answers carry the marks. Other open pages see one on reload, as with LNK.

**Verify.** A test that marks a borrowed score through the endpoint, then finds the marked class on that cell for every reader, with the statistics unchanged. A rater cannot mark someone else's cell. Clearing the other version's rating leaves the mark on an empty cell drawing nothing.

**Docs.** `music.md` (Statistics, the borrowed-ratings bullet), `music-reference.md` (the table and the endpoint), and a What's New entry in `changelog.php`.

## BSO — A borrowed score ties with real ones by song id

Sorting the song list by a score sorts a borrowed value as the rater's own (`music.md`, Statistics). Equal values are then ordered by song id, in all three places that sort: `sortRows()` in `songs.js`, `songsOrderBy()` in `routes/songs.php`, and the statistics `usort` below it. So a remaster borrowing an 8 lands wherever its id puts it among the real 8s, usually far from the original it borrows from. Read in the code; asked for by the owner, 2026-11-07.

**Plan.** Change the tie-break, in all three places alike, to one of two rules:
- **Sink:** real values before borrowed ones within each value. This is a second sort key, the same in both directions.
- **Beside:** a borrowed value sits right after the song it borrows from. This works only when it has one lender, since an average over several versions may equal none of them. Such a value then falls back to sinking.

The page needs to know which values are borrowed: a cell holding only a `.songInherited` span is one, and the server has `effective_rating`'s borrowed flag. Statistics columns are over everyone's ratings mixed, so decide whether a song whose statistic includes any borrowed value counts as borrowed, or whether the rule covers the rater columns only. ADO's mark may want to count as real here.

**Verify.** With an original and its remaster, the remaster borrowing the 8, sorting by that rater's score in either direction puts the remaster where the rule says. Clicking the header and reloading the link the header writes give the same order.

**Docs.** `music.md` (Linking and sorting: the rule that the three sorts agree).

## GAM — No game version relationship

`musicSongRelationTypes()` in `relationships.php` has only `remaster`, so a cue taken from a game's files cannot be tied to the soundtrack track released from it. The Grim Fandango Remastered cues extracted from its `YEAR*MUS.LAB` files (TSV built 2026-11-07) are waiting on it: the TSV carries each cue's disc song ids in a column the import ignores. The mapping can be rebuilt from the owner's `D:\Music\VGM` file names, `Title (NN Disc title; cue; cue).mp3`, where NN is the track's position on the Director's Cut album (TSV built 2026-11-07; its 01–37 are album 12's songs, its 38–51 new songs). The game-cue TSV predates that album in the catalogue, so its ids point 38–51 at the 1998 songs those tracks remaster (39 Copal's Office is Don Copal); point them at the Director's Cut songs instead. One name is wrong: Casino Calavera lists `1106 - casino interior` for what is `1108`. Read in the code.

**Plan.** One more entry in `musicSongRelationTypes()`, the variant being the game version and the target the disc release: labels "game version of …" and "disc release of …", and their two nouns, in each locale's part files. Decide two things first. Should it `inherit`, so that a game cue borrows its disc release's ratings as a remaster does? And should it get id columns in the song table (a `columns` entry, its keys in `MUSIC_SONG_COLUMNS`, the stylesheet's left-edge chain and hide list)? Then link the Grim cues, on each card or through IRC.

**Verify.** A test like the remaster ones in `tests/cases/relationships.php`: both cards read their own line. `php tests/i18n-coverage.php` stays clean.

**Docs.** `music.md` (the relationships bullet says only `remaster` exists), and a What's New entry in `changelog.php`.

## ALT — No alternate take relationship

The Grim Fandango Remastered Director's Cut (imported 2026-11-07) has two alternate takes of tracks on the 2015 album: Lost Souls' Alliance (Alt Solo) of Lost Souls' Alliance (138), and Blue Casket Bop (Alt Solo) of Blue Casket Bop (151). Under the rule VER settled (split on edit or performance), a different solo is a separate song. But the only relationship type is `remaster`, which would call the alt solo a remastered version, so the pairs stay unlinked. Read in the code.

**Plan.** The same change as GAM: one more entry in `musicSongRelationTypes()`, with the alternate take as the variant. Proposed labels are "alternate take of …" and "main take of …", with their nouns, in each locale's part files. Decide `inherits`: the takes share their composition and most of their performance, so borrowing may be wanted, unlike a game cue. Decide id columns as for GAM. Then link the two pairs on their cards.

**Verify.** As GAM: a test like the remaster ones in `tests/cases/relationships.php`, and `php tests/i18n-coverage.php` clean.

**Docs.** `music.md` (the relationships bullet), and a What's New entry in `changelog.php`, shared with GAM's if they ship together.

## IRC — The import cannot read relationships from a column

Add Songs relates a song to another only when the song is kept `<New, separate>` from one the artist already has under that title, and then with the one type chosen for the whole import (`songSeparateRelation` in `addSongs.js`, `related_song_id` in `ajax/song.php`). A paste cannot say that a row is the game version of song 137. So the Grim Fandango cues, which mostly have other titles than their releases, would be linked one card at a time. Read in the code.

**Plan.** A column role, Related songs, holding comma-separated song ids, with one relationship type and end chosen for it in the column-mapping step. `song.php` writes those rows in the transaction that creates the song. It refuses an id that is no song, and checks `musicSongRelationExists()` both ways round. It logs each row through `musicLogEdit()` as a `relation` on the variant, as the card does. Decide what a row matched to an existing song does with the column: add the links to that song, or leave them out.

**Verify.** A test that posts rows with the column and finds the relationships and their `edit_audit` rows, and that an unknown id is refused.

**Docs.** `music.md` (the bulk import bullet), `music-reference.md` (the `song` endpoint), and a For admins entry in `changelog.php`.

## TIP — A tooltip outlives the element it belongs to

`public/modules/music/js/tooltip.js` hides its box on a `mouseout` from a `[data-tooltip]` element, and on scroll. An element removed from the page while the cursor is on it sends no `mouseout`, and a `mouseover` on an element without a tooltip returns early, so the box stays until the cursor crosses another tooltip or the page scrolls. Reproduced 2026-11-04 in headless Chrome: the name box's hint ("Write in another name…") stayed after the box was saved and redrawn as a +. Read in the code, not seen: clicking a card editor's × ("Delete this name", "Remove this relationship") removes its row under the cursor the same way, since `#songCardModal` and `#songCards` count plain titles as tooltips.

**Plan.** In the `mouseover` handler, hide the box when the new element has no tooltip (and when its tooltip is empty), rather than returning.

**Verify.** Headless: hover a card editor's × until its tooltip shows, click it, move the cursor a pixel; the box is gone. Hovering from one tooltip to another still swaps the text.

**Docs.** `music.md` (the tooltip bullet) if the rule needs saying.

## BND — Bandcamp does not embed

Bandcamp links render as plain links, while Spotify, YouTube and SoundCloud embed. An embed needs the numeric id in Bandcamp's `EmbeddedPlayer` URL, which a normal page URL does not contain, and Bandcamp's oEmbed API answered with a bot-detection page when called from the dev environment. The link-only rendering is read in the code; the oEmbed refusal was seen during development (date not recorded).

**Plan.** Either have the admin paste Bandcamp's Share/Embed URL, from which the id can be pulled by regex (beside `spotifyTrackIdIn()` in `links.php`), or retry oEmbed from the real host, which may not be challenged.

**Verify.** A pasted embed URL stores the id, and the card shows the player.

**Docs.** `music.md` (the `song_link` bullet says Bandcamp is link-only, and why), `music-reference.md`.

## GRD — Song table lines are thicker in the header at some display scales

The song table's column lines are box-shadows in the header (a sticky header cannot use borders, see `music.md`) but borders in the body, and the two round differently at a fractional display scale. Some lines come out 2 device pixels wide in the header and 1 in the body. They never sit in a different place: the first pixel always agrees. Reproduced 2026-11-03 in headless Chrome, comparing a header pixel row with the first body row. With the default columns it shows at 150% and 175%, but not at 100% or 125%. Which lines it hits depends on where the boundaries fall, so hiding columns with the column picker can bring it out at 125% too. The rater-edge check logged in DONE.md the same day found no differences at four scales, on different data. The accent edges around the reader's own columns are already shadows in both, for this reason, and still not quite enough: at 125% the accent line right of the reader's note column covers the same two device pixels in header and body, but one of them is a shade fainter in the body (2026-11-04, headless Chrome). Shadows alone may not settle every case.

**Plan.** Draw the body's grid lines as inset shadows too, and drop the `td` borders. A `box-shadow` list does not merge across rules, so every rule that gives a `td` a shadow (the reader's own columns' accent edges, the last row's bottom edge, the statistics block's left edge and its column-picker chain, the left-edge chain on the fixed columns) has to carry the grid lines in its own list.

**Verify.** Header against body, line for line, at 100%, 125%, 150% and 175%, with the default columns and with some hidden.

**Docs.** `music.md` (the sticky-header trap and the rater-edge note in "Cards").

## SWH — Statistics for does not reach the graph or song card

On the album and artist cards, the "Statistics for" control re-reads the track table (album) or the album table (artist) as one rater instead of everyone. It does not change the graph, and the song card has no such control. Deliberate for now: the graph has its own per-rater orders, the per-rater stats table is already the breakdown, and the song card has no statistics table. Read in the code.

**Plan.** None unless it is asked for. Then decide first what "one rater" means for the graph (dim the others, or hide them).

**Docs.** `music.md` (cards) if it changes.

## TOA — Own ratings pop toasts

`TOAST_SHOW_OWN_EVENTS` in `public/modules/music/js/songs.js` is `true`, so every score or note a rater types pops a toast at themself. Deliberately left on in production (owner, 2026-09-20).

**Plan.** Flip it to `false` only when the owner says so.

**Verify.** Rating a song shows no toast to the rater; other raters still get one.

**Docs.** `music-frontend.md` (the live-updates paragraph points here).

## AFL — Audit page links and filters

`/music/audit` shows each song, album and artist as plain text and cannot be filtered by person, by what an entry is about, or by ratings against edits. A song card can be linked now (`/music/songs?songCard=<id>`, see the linking bullet in `music.md`), which gives the names something to point at. Paging (100 rows a page) is already in place. Read in the code. Possible, not planned.

**Plan.** Link each subject to its card (`?songCard=`, `?albumCard=`, `?artistCard=`); add the filters to both halves of the audit query as query parameters, kept across the paging links.

**Verify.** A test that a filter narrows the rows and survives paging.

**Docs.** `music-reference.md` (the audit route).

## CAS — hideandseek 001 breaks the never-cascade rule

The site rule (`conventions.md`, owner 2026-10-06) is that tables declare no `REFERENCES` and no `ON DELETE`. hideandseek's `001_create.sql` predates it. `poi_category.game_map_id` and `poi.poi_category_id` carry `ON DELETE CASCADE`, which `routes/gameMaps.php` and `routes/importPois.php` rely on, and a test in `tests/cases/hideandseek.php` pins that deleting a map takes its POIs. `invite.created_by_account_id` and `invite.used_by_account_id` carry a bare `REFERENCES account(id)`, which with `foreign_keys = ON` would refuse to delete an account that ever issued or used an invite. Read in the code.

**Plan.** While `001` is unshipped this is an edit in place: drop the constraints from `001`, replace each cascade with explicit deletes in those routes (POIs, then categories, then the map, in one transaction), and delete the disposable `data/hideandseek_test.sqlite`. The test keeps its assertion; the code satisfies it instead of the schema. Once `001` ships, the same change needs a table rebuild, which is why this comes before MIG.

**Verify.** The suite, including the delete-map test. `PRAGMA foreign_key_list` is empty on every hideandseek table.

**Docs.** `conventions.md` (drop hideandseek from the "older tables" list), `hideandseek-reference.md` (schema, and the line saying a map delete removes its POIs).

## PRV — No privacy page for hideandseek

hideandseek has accounts and a session cookie, kept for 399 days once signed in, and sends map-tile requests (the visitor's IP and referrer) to OpenFreeMap. No page says so. Tile requests already wait until the visitor presses a button that says it will contact a third party. Read in the code. Wanted before the module is opened to players.

**Plan.** A privacy page (route, view, a link from every page) covering accounts, the session cookie, the tile requests, and pins once PIN stores location data. Serving a `.pmtiles` extract and pointing `sources.openmaptiles.url` in `json/mapStyle.json` at it would drop the third party and shorten the page, but not replace it. Music's embeds raise the same question (EMB), so one shared page may serve both.

**Verify.** The page is reachable from every hideandseek page, and what it lists matches the browser's network panel while the map loads.

**Docs.** `hideandseek.md` (what the module sends where), `hideandseek-reference.md` (route).

## WHO — Delete the whoami example endpoint

`ajax/whoami.php` was written as a worked example of `ajaxGuard.php`'s shape, to be copied and then deleted. Real endpoints exist now (`buurten.php`, `importPois.php`, `exportPois.php`), so it has done its job. The guard tests in `tests/cases/hideandseek.php` (401 signed out, 405 on GET, 403 without a token, 400 on a bad body) use it as their target. Read in the code.

**Plan.** Point those guard tests at a real endpoint, then delete `whoami.php` and its line in `routes.php`.

**Verify.** The suite; `grep -rn whoami app public tests` finds nothing.

**Docs.** `hideandseek.md` (its last bullet), `hideandseek-reference.md` (endpoint table).

## PIN — Map pins are not saved

`hnsMarkers` in `public/modules/hideandseek/js/map.js` holds `{lat, lng, marker}` for each pin placed by clicking the map, and nothing else holds them: a reload loses every pin, and the ◎ overlay, which measures from the latest pin, starts over. Read in the code.

To settle first: are pins shared between the accounts on a game map or private to whoever placed them, and how long are they kept? A pin for "where a person is hiding" is location data about an identified user, which also feeds PRV.

**Plan.** A `pin` table in `001` while it is unshipped, with a `game_map_id` (GMP) and no `REFERENCES` (CAS), and one ajax endpoint dispatching on an action (add, move, delete), shaped like `routes/accounts.php`. `hnsMarkers` loads from it with the page.

**Verify.** A test that adds, lists and deletes pins through the endpoint and refuses another map's pins; by eye, pins survive a reload.

**Docs.** `hideandseek.md` (the ◎ paragraph says pins live only in the page), `hideandseek-reference.md` (schema, endpoint).

## GMP — Only imported POIs attach to a game map

`game_map` is what game data hangs off, and so far only `poi_category` (and through it `poi`) carries a `game_map_id`. `hnsLoadedGameMap()` in `gameMap.php` is the hook: whatever comes next (pins, play-area bounds, a start point) should take a `game_map_id`. The CBS gemeenten, wijken and buurten are the deliberate exception: global, owner's call (2026-10-06); a map points at them through `gemeente_code`. Separately, `routes/index.php` opens the map on a hardcoded centre in Groningen (`mapCentre`) rather than on the loaded map's POIs or gemeente. Read in the code.

**Plan.** Give each new game table a `game_map_id` as it is built (PIN first). For the opening view, fit the map to the loaded map's POIs (they are already in the page), else to its gemeente, else keep Groningen.

**Verify.** By eye: a map whose POIs are elsewhere in the country opens on them, and with no map loaded the page still opens on Groningen.

**Docs.** `hideandseek.md` (the game map bullet), `hideandseek-reference.md`.

## MIG — hideandseek 001 freezes when it first ships

hideandseek's `001_create.sql` has not shipped (owner, 2026-10-06; the deploys of 2026-10-19, 2026-11-02 and 2026-11-05 left the whole module out). New tables therefore go straight into it rather than into a `002`, and applying an edit means deleting the disposable `data/hideandseek_test.sqlite`. The day the module first deploys, music's freeze rule applies: `001` is never edited again, and the next schema change is `002`.

**Plan.** Before that deploy, land what is cheaper in `001` (CAS, PIN's table). The deploy itself is taking `hideandseek` off `$unshippedModules` in `build-deploy.php`, which puts the module back in `_deploy/` and its `$mustExist` checks back in force. After it, confirm the host's hideandseek database lists `001_create.sql` in `schema_migrations`, then close this item.

**Verify.** `schema_migrations` on the host.

**Docs.** `hideandseek.md` (the "has not shipped" sentence), `conventions.md` (the `hideandseek_test.sqlite` exception, whose reason is applying edits to `001`), `CLAUDE.md` if it names the boundary.

## GEO — The geo database is copied by hand

`hideandseek_geo.sqlite` (gemeenten, wijken, buurten) is built at home by `php tools/import-buurten.php` (about 40 s, 31 MB) and uploaded by hand to `/home/lotsofs/data`. Nothing checks that the host has the file or that it holds the current year, and CBS publishes a new dataset every year. A game map's `gemeente_code` is a CBS code, and CBS retires and merges gemeenten, so a stored code can stop existing after a refresh; the game maps page then shows it as plain text. Read in the code. It matters once hideandseek ships (MIG).

**Plan.** Rerun with `--year=` when CBS publishes, and recopy. Optionally store the dataset year in the file and show it beside the CBS credit under the map, so a stale file is visible.

**Verify.** On the host, `/hideandseek/ajax/buurten` returns buurten for a map with a gemeente.

**Docs.** `hideandseek.md` (the geo bullet) if the year is stored.

## LOC — hideandseek has one locale

hideandseek has one locale, `en` (`config.php`), and `nav.php` renders the language menu only when there is more than one. The route and the menu are already built. Read in the code.

**Plan.** Add the code to `locales` in `config.php` and a `lang/<code>.php` overlay with part files, as music has. `tests/cases/strings.php` and `tests/i18n-coverage.php` check music only; widen them in the same change.

**Verify.** The menu appears, and switching changes the page's language.

**Docs.** `hideandseek.md` (the one-locale bullet).

## PAR — No tests for the POI parser or the map

`js/poiImport.js` parses Overpass JSON and XML in the browser, and the suite runs no JS, so only the import endpoint's validation is tested. The same goes for everything `map.js` draws (POIs, overlays, buurten): the map is only ever checked by eye. Read in the code.

**Plan.** Waits on JST. Until then, check the parser by hand against `docs/exampleImport.txt` after changing it.

**Docs.** `testing.md` (what the suite cannot see).

## BUS — No bus stops or routes on the base map

Every base-map label is hidden, bus stops included, so transit has to arrive as imported POIs. Bus lines cannot come from the tiles: a route is a `type=route` relation, and the tileset carries none for buses (only bus-only roads, `busway` and `bus_guideway`). Overpass can supply route geometry, but both public mirrors time out too often to rely on. See `hideandseek.md` for how the tiles were checked.

**Plan.** Only if wanted, as deliberate work: bake routes from a Geofabrik extract or a GTFS feed into a file the page loads, as the buurten are baked into `hideandseek_geo.sqlite`.

**Docs.** `hideandseek.md`.

## FXR — Exchange rates are frozen

The exchange-rates page and the home page show rates from checked-in JSON dated 2025-10-04, and the home page shows them without a date. Both fetch paths in `app/modules/main/exchangeRatesController.php` (`file_get_contents` and curl) are commented out, and the pages' JS reads `public/modules/main/json/exchangeRates.json` directly. Read in the code.

**Plan.** A server-side job that refreshes that JSON file (the host's cron, if it has one), with `cacert.pem` handling restored. At the least, print the date beside the numbers.

**Verify.** The JSON's date moves; the home page shows a date.

**Docs.** `other-modules.md`.

## NAV — ss2 nav links to pages that 404

The ss2 nav (`views/partials/nav.php`) links `/ss2/` and `/ss2/1` to `/ss2/7`, none of which is in `ss2/routes.php`, so they 404. Only `/ss2/11` to `/ss2/18` are routed, and only 11 to 13 have data (`public/modules/ss2/json/` holds `00`, `11`, `12` and `13`). Read in the code, not clicked through.

**Plan.** Remove or disable the nav entries that have no route, or add routes as their data arrives. Decide what `/ss2/` should show.

**Verify.** Every ss2 nav link answers 200. `tests/cases/pages.php` could walk them.

**Docs.** `other-modules.md`.

## SLG — Logins for ss2

`session.php` is module-agnostic, so ss2 could have accounts through `sessionScope('ss2')` and its own account table, as hideandseek did. Possible, not planned.

**Plan.** Copy hideandseek's account system, or wait for SHR to decide what is shared first.

**Docs.** `other-modules.md`.

## TAB — Bass tabs are held back from the deploy

The owner took the module out of the build for now on 2026-11-02, by adding `basstabs` to `$unshippedModules` in `build-deploy.php`. It has never shipped: the 2026-11-05 build has no `basstabs` folder, and `/basstabs` is a 404 on the host (reproduced 2026-11-05).

**Plan.** Take `basstabs` off `$unshippedModules`, which puts the module back in `_deploy/` and its `$mustExist` checks back in force. Put this entry back under Unreleased in `CHANGELOG.md`, under `### Added`, updated for any sheets added since:

> Bass tab sheets at lotsofs.com/basstabs: a list of songs, each opening a sheet of notes for 5-string bass, headed with the song's title, artist and key, with the name of each note under it and fretboards in greys beside the parts they belong to, each showing a key, the notes of its chord, and a hand position to play it from. Each sheet prints on A4 and fits a phone screen. There are seven so far: My Favourite Game by The Cardigans, Amsterdam by Nothing but Thieves, Bring Me to Life by Evanescence, Love Myself by Rondé, whose sheet is its riff and a fretboard for each of its four chords, Monday Morning by Melanie Fiona, which ends with the song's lyric cues in order, Inside by Stiltskin, and Creep by Radiohead. Sheets can show rests and slides. A sheet longer than a page carries on onto the next one without splitting a part in two.

**Verify.** `php build-deploy.php` (run by the owner) passes its checks, and `/basstabs` answers on the host after the deploy.

**Docs.** `basstabs.md`, `architecture.md` and `CLAUDE.md`, which all name it as unshipped.

## CFG — The site-wide config file is loaded by nothing

`app/config.php` returns an empty array and nothing requires it; settings live in each module's `config.php`, read through `moduleConfig()`. It still ships: `build-deploy.php` lists it in `$mustExist`, and its header comment says to leave the host's copy alone "if it ever holds a real secret". Read in the code.

**Plan.** Delete it, with its `$mustExist` line and that comment. If a site-wide setting turns up later, `require` a config file from `util.php` again. A secret belongs in `data/config.php` (never deployed) or an environment variable, with a `config.example.php` beside it.

**Verify.** The suite; `php build-deploy.php` (run by the owner) passes its checks.

**Docs.** `architecture.md` (the file tree line points here).

## MGR — No command to run migrations

Migrations apply as a side effect of the first request after a deploy (`runMusicMigrations()` runs at the top of every music route and ajax file; hideandseek does the same). There is no way to apply one ahead of traffic, or to confirm it succeeded short of using the site. Read in the code.

**Plan.** Find out whether the host offers shell access. If it does, a CLI entry point under `app/` (outside the web root) that runs each module's migrations and prints `schema_migrations`; if not, an admin-only page doing the same.

**Verify.** Running it twice applies nothing the second time and lists the same files.

**Docs.** `architecture.md` (migrations, deploy), `CLAUDE.md` (Commands).

## JST — JavaScript tests

The suite drives PHP over HTTP and runs no JavaScript. The pure functions in `public/modules/music/js/addSongs.js` are testable as they stand, but there is no Node on this machine. Possible, not planned.

**Plan.** Install Node, or have the harness drive a headless browser. Start with `addSongs.js` and hideandseek's parser (PAR).

**Docs.** `testing.md`, `CLAUDE.md` (Commands).
