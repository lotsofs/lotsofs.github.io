# Open decisions

Detail for the **Open decisions** rows in [TODO.md](TODO.md). A decision closes when the owner makes it, and what is decided then becomes work in [tasks.md](tasks.md) or a rule in the module's doc. Until then they block work downstream and keep resurfacing.

## EMB — Do the embeds need consent, and in what shape

The songs page loads real Spotify, YouTube and SoundCloud iframes, which set cookies and send the visitor's IP and referrer to those services whatever the visitor wants (*Fashion ID*, C-40/17, makes the operator jointly responsible). Under ePrivacy (in the Netherlands, Telecommunicatiewet 11.7a) consent has to come before the iframe loads, so a banner over running embeds satisfies nothing. A mandatory signup checkbox is the wrong answer (GDPR 7(4): not freely given; 7(3): withdrawing has to be as easy as granting). Read in the code and the law; not legal advice.

The shape that works: an **account setting, off by default and changeable any time** ("load Spotify/YouTube players inline"), with a plain link in place of the player while it is off, optionally offered unticked at signup. Every viewer is logged in, so a setting covers the whole audience with no banner. It is not urgent while the audience is a few invited friends; it decides whether the embeds can ever be shown more widely.

**If adopted.** An `account` column (migration `009` or later), a toggle in the cog menu beside blind rating, and `songRows.php` rendering the link while it is off.

**Verify.** With the setting off, loading the songs page makes no request to Spotify, YouTube or SoundCloud.

**Docs.** `music.md` (the `song_link` bullet), `music-reference.md` (schema, settings route).

## PER — Is music a personal tool or part of the site

Music is 60+ files and the most developed thing here, yet `main`'s home page still says "There currently isn't much here yet", and only that page links to `/music`. Worth deciding rather than drifting.

**Bears on.** Whether `main` should present music, and how much EMB and PRV matter: a public tool needs both sooner.

**Docs.** `CLAUDE.md` ("What this is"), `other-modules.md`.

## SHR — Should music and hideandseek share code

`rateLimit.php`, `inviteCode.php`, `hue.php`, `auth.php`, `ajaxGuard.php` and `tooltip.js` are near-copies between the two modules. Intentional for now: the codebase has no service layer, and two modules, one of them still boilerplate, are too few to see what is really shared. Until it is decided, a fix to one copy needs checking against the other.

**Revisit** once hideandseek has real pages. The invite code and the rate limiter are the obvious candidates; PWR would add a third if both modules get password reset.

**Docs.** `hideandseek.md` (opening paragraph), `hideandseek-reference.md` (the near-copies line).

## COV — How are covers modelled

Not at all. `song_cover` and `composition` went in the ground-up rewrite (`app/modules/music/notes.txt` has the original sketch). The earlier reasoning: a cover is a separate song sharing a composition, which needs `composition` back. Nothing forces it until a cover needs recording.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).

## VER — When is a recording a separate song

The settled rule: split on edit or performance, not on master or encoding, and override when it would change the score. It is not implemented: `song_version` was dropped, and no constraint stops two same-titled songs by one artist (duplicate detection is an application check in `song.php`). Nothing blocks recording one; it is just not modelled on purpose.

**Docs.** `music.md` (the data model), `music-reference.md` (schema).
