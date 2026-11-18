# Open decisions

Detail for the **Open decisions** rows in [TODO.md](TODO.md), grouped by module in the index's order. A decision closes when the owner makes it, and what is decided then becomes work in [tasks.md](tasks.md) or a rule in the module's doc. Until then they block work downstream and keep resurfacing.

## COV — How are covers modelled

Not at all. `song_cover` and `composition` went in the ground-up rewrite (`app/modules/music/notes.txt` has the original sketch). The earlier reasoning: a cover is a separate song sharing a composition, which needs `composition` back. Nothing forces it until a cover needs recording. A `cover` type in `musicSongRelationTypes()` would now link a cover to its original on both cards, though it shares no composition.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).

## VER — When is a recording a separate song

The settled rule: split on edit or performance, not on master or encoding, and override when it would change the score. Partly built since 2026-11-04: the import's Song step can add a song under a title its artist already has (`<New, separate>`, which skips the duplicate check in `song.php`), and `song_relationship` says how two songs relate, shown on both cards (only a `remaster` type so far). What is left is the rule itself: nothing applies it or suggests a split, rename and adding a name still refuse a name another song by the artist has.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).

## GAM — What the games module holds

The owner decides what `/games` tracks game by game, as each game needs it. The first is Age of Empires (2026-11-12): a timeline of every scenario from a public spreadsheet, with a tick per scenario per account, and filters the owner expects to add to (`games.md`).

The owner's first idea for the module as a whole was a smaller version of music's song list: Game → Level, with each account giving every level a ranking and a note, keeping music's alternate names (one current name, the others kept as an alias table keeps them), "and maybe some other features". It may still suit a later game.

**Open when it comes up.** Whether a ranking is a 0–10 score as in music, an order within the game, or a tier. Which of the song list's features come along: statistics, live updates and toasts, blind rating, the column picker, cards, a paste import, History. Music's are written around songs, so each would be copied, not shared (SHR).

**Docs.** `games.md`, `CLAUDE.md` ("What this is").

## PER — Is music a personal tool or part of the site

Music is 60+ files and the most developed thing here, yet `main`'s home page still says "There currently isn't much here yet", and only that page links to `/music`. Worth deciding rather than drifting.

**Bears on.** Whether `main` should present music, and how much PRV matters: a public tool needs it sooner.

**Docs.** `CLAUDE.md` ("What this is"), `other-modules.md`.

## SHR — Should the modules with accounts share code

`rateLimit.php`, `inviteCode.php`, `hue.php`, `auth.php`, `ajaxGuard.php` and `tooltip.js` are near-copies between music and hideandseek. Games (2026-11-12) adds a third copy of `rateLimit.php`, `inviteCode.php`, `auth.php`, `ajaxGuard.php`, `db.php`, `migrate.php` and the account routes and views, all taken from hideandseek, and mtasa (2026-11-16) a fourth, taken from games, with `tooltip.js` copied from games too (2026-11-17). Intentional for now: the codebase has no service layer, and with two of the three modules still boilerplate it is too early to see what is really shared. Until it is decided, a fix to one copy needs checking against the others.

**Revisit** now that games and mtasa have real pages. The invite code, the rate limiter and the account routes are the obvious candidates; PWR would add another if the modules get password reset.

**Docs.** `hideandseek.md` (opening paragraph), `hideandseek-reference.md` (the near-copies line), `games.md`, `mtasa.md`.
