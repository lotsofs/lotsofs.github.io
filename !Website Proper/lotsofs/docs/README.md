# docs/ index

Reference material for working in this repo. `CLAUDE.md` (repo root) is the always-loaded summary and imports `music.md` and `hideandseek.md`; everything else here is read on demand.

| File | Read it when |
|---|---|
| [architecture.md](architecture.md) | Touching the router, `util.php`, `session.php`, `Database`, i18n, assets, or deploy. Start here for any cross-cutting change. |
| [conventions.md](conventions.md) | Writing any code. Style, naming, the owner's standing preferences, and the traps that have cost debugging cycles. |
| [testing.md](testing.md) | Running or writing tests. Harness internals, helpers, what each case file covers, and what the suite cannot see. |
| [music.md](music.md) | Design rules and traps of the music module (imported by CLAUDE.md). The *why*. |
| [music-reference.md](music-reference.md) | Looking up music facts: schema, every route and endpoint with its request/response shape, helper functions, file map. The *what*. |
| [music-frontend.md](music-frontend.md) | Editing `songs.js`, `albumCard.js`, `addSongs.js`, or the card/modal DOM. |
| [hideandseek.md](hideandseek.md) | Design rules and traps of the hideandseek module (imported by CLAUDE.md). |
| [hideandseek-reference.md](hideandseek-reference.md) | Looking up hideandseek facts: schema, routes, endpoints, import/export formats, JS file map. |
| [other-modules.md](other-modules.md) | Working on `main`, `swat4`, or `ss2` (ss2 has a data-driven JS front end worth understanding). |
| [TODO.md](TODO.md) | Before assuming a rough edge is unintentional: open tasks, unresolved design decisions, environment gotchas. |
| [exampleImport.txt](exampleImport.txt) | Real Overpass Turbo JSON paste, for the hideandseek POI importer. |
| [exampleExport.txt](exampleExport.txt) | Real `hideandseek-pois` export, which the same import box accepts. |

## Keeping these current

docs/TODO.md is maintained freely (see CLAUDE.md). The docs here should be updated in the same pass as the code they describe. A fact that is only true of one moment ("X is not shipped yet") should carry a date.
