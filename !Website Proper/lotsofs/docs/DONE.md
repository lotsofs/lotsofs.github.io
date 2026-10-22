# Done

Finished work, newest first; work that never had a code shows `—`. An item's detail section is deleted when it finishes; `git log -S "## CODE " -- docs/` finds the commit that removed it. Items finished before this log began (2026-10-19) were checkbox bullets in `TODO.md` and were not logged; search git history for `[CODE]` to find those.

| Date | Code | Area | Change | Why / how |
|---|---|---|---|---|
| 2026-10-22 | — | Music | Show a rater's name in their own colour on rating toasts | The toast named the rater in plain bold; the poll now carries each rater's hue, and the name is drawn in the shade of the nav's colour swatch. Checked by the suite (a new case covers a picked hue, a changed one and the default); not yet seen in a browser. |
| 2026-10-22 | — | Music | Hold scores to 0–10, and make the score box refuse anything that can't become one | Words typed into the score box were only refused on save, in the off-screen Result column, and the text was lost; the box now undoes any edit that leaves a non-score, and the server refuses scores outside 0–10. Checked in headless Chrome (25 checks) and the suite. |
| 2026-10-22 | OUT | Site | Load only the modules that are present, and leave unshipped modules out of the build | The router included every module by name, so the host, which has no hideandseek, logged two warnings per request; it now loads whichever modules are there, and the build leaves hideandseek out and fails if it leaks in. Reproduced on a local copy without the module, then checked there: no warning, `/hideandseek` 404s. |
| 2026-10-19 | SEC | Site | Confirm the session cookie carries Secure on the live host | The flag follows PHP's view of HTTPS, which a proxy in front of the host could have hidden. Checked live: the login page sets the cookie as secure, HttpOnly, SameSite=Lax. |
