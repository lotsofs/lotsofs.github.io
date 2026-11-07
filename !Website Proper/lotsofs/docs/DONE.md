# Done

Finished work, one row per commit, newest first; `—` where the work had no code. The detail is in each row's commit, and a finished item's deleted section is found with `git log -S "## CODE " -- docs/`. Items finished before 2026-10-19 were checkbox bullets in `TODO.md`; search git history for `[CODE]`.

| Date | Code | Area | Change | Why / how |
|---|---|---|---|---|
| 2026-11-07 | — | Music | Keep a YouTube link's timestamp, so its embedded player and its link start there | A pasted link was cut to the bare video id; the id is now stored with `&t=` seconds. Checked by the suite and in headless Chrome. |
| 2026-11-07 | NAM | Docs | Drop NAM and track the Grim Fandango follow-ups: game-version and alternate-take relations, an import relation column, and song-list fixes for linking, versions and sorting | The owner stopped tracking NAM's second names; its album listings were already gone. The new items came from importing the game cues and the Director's Cut. |
| 2026-11-06 | — | Music | Split the song list's Other names & versions into Other names and two narrow id columns after Links, Remastered by and Remaster of | The owner wanted versions as ids, a version's names among a song's own, and a hover where a name differs. Checked by the suite and in headless Chrome. |
| 2026-11-05 | — | Site | Date the deployed release and freeze music migration 009 | The owner pushed to prod. Checked on the host: `/music` now sends you to login, and the login page, which runs migrations, answers. |
| 2026-11-05 | — | Music | Fill the music home page with featured links into the song list, each with how much of it is rated, picked on the page, and land there on login | `/music` was an empty public page; it now needs a login, welcomes you and lists song list views an admin picks there. Checked by the suite and in headless Chrome. |
| 2026-11-05 | — | Music | Let the song list's text columns be dragged wider or narrower, and widen Album to 18em | Long names were clipped at fixed widths; each text column's header now has a grip, the width kept in the browser, and Album starts at 18em instead of 10. Checked by the suite and headless Chrome at 100% and 125%. |
| 2026-11-05 | — | Music | Draw the nav's settings and language icons in Noto Emoji | The system's stand-in font drew the cog smaller than the globe. Checked in headless Chrome at three scales. |
| 2026-11-05 | — | Music | Shorten the What's New entries, drop their kinds, and list admin-only changes apart | The owner found the entries too long and Added/Changed pointless; admin-only items now sit under For admins. Checked by the page's tests. |
| 2026-11-05 | TWN | Music | Make Add Songs tell two songs with one title apart, and show links in full | A re-import matched every title to the 2015 song and overwrote it; the Song step now picks by album or asks. Checked by an import and re-import in headless Chrome. |
| 2026-11-05 | — | Music | Merge Also Known As and Related into one Other names & versions column | The owner wanted one narrower column; the card keeps both rows. Checked by the suite and in headless Chrome. |
| 2026-11-05 | — | Music | Log admins' edits on the History page, and fold the unshipped migrations into one | Admin edits left no trace; every admin write now logs its before and after. Checked by the suite. |
| 2026-11-05 | — | Music | Run the migrations on every music ajax request | A tab left open across a deploy wrote against the old schema until a page loaded. Checked by the suite. |
| 2026-11-04 | — | Music | Open a card's box for another name with a +, and keep each name's buttons beside it | The always-open box cluttered edit mode. Checked in headless Chrome. |
| 2026-11-04 | VER (partial) | Music | Relate songs to each other, let a song borrow its versions' ratings, and let the import add another recording of a title | The 1998 Grim Fandango soundtrack had to go in beside its 2015 remaster without overwriting it. Checked by the suite and in headless Chrome. |
| 2026-11-04 | — | Docs | Log one row per commit, and merge the earlier rows to match | One change was spread over several rows. Checked against the git log. |
| 2026-11-04 | — | Music | Take the raters' colour back off their names and columns on the song list | The owner wanted neither. Checked by the suite and in headless Chrome. |
| 2026-11-04 | — | Music | Let a card's edit mode write in another name | Another name could only come from a rename or an import. Checked by the suite and in headless Chrome. |
| 2026-11-03 | ALI | Music | Let a card's edit mode delete a name, or make it the current one again | A typo'd rename stayed for good. Checked by the suite and in headless Chrome. |
| 2026-11-03 | — | Music | Add a column picker and an Also Known As column to the song list | Every column was always on, and other names were only in a tooltip. Checked by the suite and in headless Chrome. |
| 2026-11-03 | — | Music | Show each rater in their own hue on the song list | The hue reached toasts and the graph but not the list. Checked by the suite and in headless Chrome. |
| 2026-11-02 | — | Site | Date the deployed release and freeze music migration 008 | The owner pushed to prod. Checked on the host. |
| 2026-11-02 | — | Bass tabs | Keep the bass tabs out of search results, and out of the deploy for now | The owner wanted them reachable by link only, and not shipped yet. Checked by the module's tests. |
| 2026-11-02 | — | Site | Keep a login for 399 days from the last visit | Sessions expired after 24 idle minutes, so the owner kept being signed out. Checked by the suite and in headless Chrome. |
| 2026-10-27 | — | Music | Give the music pages a What's New page | The owner wanted music's changes where its users are. Checked by the suite and in headless Chrome. |
| 2026-10-27 | — | Docs | Group `docs/TODO.md` by module, then by kind | The owner's choice. Checked that every row and link resolves. |
| 2026-10-26 | — | Tests | Let `tests/run.php` run only the case files it is named | The full suite stalled the conversation. Checked by a filtered and a full run. |
| 2026-10-26 | — | Bass tabs | Add Creep by Radiohead | Needed flats on a sharp sheet, slides, and a section beside another. Checked by the suite and in headless Chrome. |
| 2026-10-26 | — | Bass tabs | Mark each fretboard's chord tones | The owner wanted them picked out. Checked by the suite and in headless Chrome. |
| 2026-10-25 | — | Bass tabs | Add Inside by Stiltskin | Needed rests, a one-dot mark and stacked boards. Checked by the suite and in headless Chrome. |
| 2026-10-23 | — | Bass tabs | Add Monday Morning | Needed a section of plain lines. Checked by the suite and in headless Chrome. |
| 2026-10-23 | — | Bass tabs | Add Bring Me to Life, on one page | Needed hammer-ons, line breaks and tighter spacing. Checked by the suite and in headless Chrome. |
| 2026-10-23 | — | Bass tabs | Give Love Myself a full-size fretboard per chord | A section can now be only a board. Checked by the suite and in headless Chrome. |
| 2026-10-23 | — | Bass tabs | Add Amsterdam | Needed marks over notes, held notes and a Phrygian board. Checked by the suite and in headless Chrome. |
| 2026-10-23 | — | Bass tabs | Add a fretboard of the song's key beside a section | The owner wanted to see where to go beyond root notes. Checked by the suite and in headless Chrome. |
| 2026-10-23 | MFG | Bass tabs | Add the basstabs module, starting with My Favourite Game | The owner's bass sheets were handwritten. Checked by the suite and in headless Chrome. |
| 2026-10-22 | — | Music | Let each account choose how long rating toasts stay, or turn them off | Toasts always lasted 3 minutes. Checked by the suite and in headless Chrome. |
| 2026-10-22 | — | Music | Show a rater's name in their own colour on rating toasts | The name was plain bold. Checked by the suite and in headless Chrome. |
| 2026-10-22 | — | Music | Hold scores to 0–10 in the score box and on the server | A typed word was refused only on save, off screen, and lost. Checked by the suite and in headless Chrome. |
| 2026-10-22 | OUT | Site | Load only the modules that are present, and leave unshipped ones out of the build | The host, without hideandseek, logged two warnings per request. Reproduced locally, then checked there. |
| 2026-10-19 | SEC | Site | Confirm the session cookie carries Secure on the live host | A proxy could have hidden HTTPS from PHP. Checked live. |
