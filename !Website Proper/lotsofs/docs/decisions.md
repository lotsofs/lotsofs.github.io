# Open decisions

Detail for the **Open decisions** rows in [TODO.md](TODO.md), grouped by module in the index's order. A decision closes when the owner makes it, and what is decided then becomes work in [tasks.md](tasks.md) or a rule in the module's doc. Until then they block work downstream and keep resurfacing.

## COV — How are covers modelled

Not at all. `song_cover` and `composition` went in the ground-up rewrite (`app/modules/music/notes.txt` has the original sketch). The earlier reasoning: a cover is a separate song sharing a composition, which needs `composition` back. Nothing forces it until a cover needs recording. A `cover` type in `musicSongRelationTypes()` would now link a cover to its original on both cards, though it shares no composition.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).

## VER — When is a recording a separate song

The settled rule: split on edit or performance, not on master or encoding, and override when it would change the score. Partly built since 2026-11-04: the import's Song step can add a song under a title its artist already has (`<New, separate>`, which skips the duplicate check in `song.php`), and `song_relationship` says how two songs relate, shown on both cards (only a `remaster` type so far). What is left is the rule itself: nothing applies it or suggests a split, rename and adding a name still refuse a name another song by the artist has.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).

## PER — Is music a personal tool or part of the site

Music is 60+ files and the most developed thing here, yet `main`'s home page still says "There currently isn't much here yet", and only that page links to `/music`. Worth deciding rather than drifting.

**Bears on.** Whether `main` should present music, and how much PRV matters: a public tool needs it sooner.

**Docs.** `CLAUDE.md` ("What this is"), `other-modules.md`.

## SHR — Should music and hideandseek share code

`rateLimit.php`, `inviteCode.php`, `hue.php`, `auth.php`, `ajaxGuard.php` and `tooltip.js` are near-copies between the two modules. Intentional for now: the codebase has no service layer, and two modules, one of them still boilerplate, are too few to see what is really shared. Until it is decided, a fix to one copy needs checking against the other.

**Revisit** once hideandseek has real pages. The invite code and the rate limiter are the obvious candidates; PWR would add a third if both modules get password reset.

**Docs.** `hideandseek.md` (opening paragraph), `hideandseek-reference.md` (the near-copies line).
