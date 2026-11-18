"use strict";

const AOE2_CHECK_ENDPOINT = "/games/ajax/aoe2-check";
const AOE2_FOLD_ENDPOINT = "/games/ajax/aoe2-fold";

/// A stretch can be folded once it is this many rows long, so folding it down
/// to one row always gains at least one.
const AOE2_FOLDABLE_ROWS = 2;

/// A folded year's rows are hidden until found, so the browser's find reaches
/// them; a browser that cannot do that hides them outright.
const AOE2_HIDDEN = "onbeforematch" in document.body ? "until-found" : "";

const aoeFilters = document.getElementById("aoeFilters");
const aoeProgress = document.getElementById("aoeProgress");
const aoeStatus = document.getElementById("aoeStatus");

/// The filters apply as soon as one changes; the Apply button is for a browser
/// without this script.
if (aoeFilters) {
    aoeFilters.addEventListener("change", () => aoeFilters.submit());
    aoeFilters.querySelector(".aoeApply").hidden = true;
}

const aoeTimeline = document.querySelector(".aoeTimeline");

if (aoeTimeline && AOE2_HIDDEN === "") {
    aoeTimeline.querySelectorAll('.aoeRow[hidden="until-found"]').forEach(row => row.setAttribute("hidden", ""));
}

function aoeYearText(year) {
    return year < 0 ? t("aoe2.yearBce", {year: -year}) : String(year);
}

/// The timeline's steps, in order: a scenario's row, or to scale a year of
/// several scenarios, its header and the rows under it. A gap follows every
/// step but the last, and the last too when the axis runs on past it.
function aoeSteps() {
    const steps = [];
    for (const item of aoeTimeline.children) {
        if (item.classList.contains("aoeGroup") || (item.classList.contains("aoeRow") && !item.dataset.group)) {
            steps.push({head: item, items: [item]});
        }
        else if (item.classList.contains("aoeRow")) {
            steps[steps.length - 1].items.push(item);
        }
    }
    return steps;
}

function aoeGroupHead(group) {
    return [...aoeTimeline.querySelectorAll(".aoeGroup")].find(head => head.dataset.group === group);
}

function aoeGroupRows(group) {
    return [...aoeTimeline.querySelectorAll(".aoeRow")].filter(row => row.dataset.group === group);
}

/// To scale: a year is as long as the shortest row (the owner's choice: one
/// row, one year), and each step sits its years' distance below the first, or
/// straight under the one above when that is lower, so nothing overlaps. A
/// step is one row, or a year of several scenarios with whichever of its rows
/// are unfolded. The room goes in the spacer after each step, which carries
/// the lanes through it, a year axis with every tenth year labelled, and the
/// button that folds it down to one row; everything after a folded stretch
/// moves up by what it gave back. The stretch marked `data-present` runs to
/// the present year, before the undated scenarios or after the last step.
///
/// The gaps are measured around, never cleared first: a row's height does not
/// depend on them, and clearing them shrank the page to a tenth of its length
/// for a moment, which moved the scroll position (the owner saw years and
/// stretches far down the page jump). Everything is measured once and every
/// gap written once, so the page is laid out once rather than once per row.
function layoutAoeScale() {
    if (!aoeTimeline || !aoeTimeline.dataset.scale) {
        return;
    }

    const gaps = [...aoeTimeline.querySelectorAll(".aoeGap")];
    const steps = aoeSteps();
    const heights = steps.map(step => step.items.reduce((sum, item) => sum + item.getBoundingClientRect().height, 0));
    const yearLength = Math.min(...steps.map(step => step.head.getBoundingClientRect().height));
    const present = Number(aoeTimeline.dataset.presentYear);
    const opened = new Map();
    let first = null;
    let cursor = 0;
    let folded = 0;

    const openGap = (gap, year) => {
        const target = (year - first) * yearLength - folded;
        if (target <= cursor) {
            return;
        }
        const size = target - cursor;
        const foldable = size >= AOE2_FOLDABLE_ROWS * yearLength;
        const fold = foldable && gap.hasAttribute("data-collapsed");
        /// Exact, because what follows sits at its own year.
        const from = first + (cursor + folded) / yearLength;
        opened.set(gap, {size, foldable, fold, from});
        if (fold) {
            folded += size - yearLength;
            cursor += yearLength;
        }
        else {
            cursor = target;
        }
    };

    steps.forEach((step, i) => {
        const year = step.head.dataset.year === "" ? null : Number(step.head.dataset.year);
        if (year !== null) {
            first ??= year;
        }
        const gap = gaps[i - 1];
        if (i > 0 && first !== null) {
            if (gap.hasAttribute("data-present")) {
                openGap(gap, present);
            }
            else if (year !== null) {
                openGap(gap, year);
            }
        }
        cursor += heights[i];
    });
    const trailing = gaps[steps.length - 1];
    if (trailing && first !== null && trailing.hasAttribute("data-present")) {
        openGap(trailing, present);
    }

    gaps.forEach(gap => {
        const axis = gap.querySelector(".aoeAxis");
        const stretch = opened.get(gap);
        gap.querySelectorAll(".aoeDecade").forEach(label => label.remove());

        if (!stretch) {
            gap.style.removeProperty("--gap");
            gap.classList.remove("aoeGapCollapsed");
            axis.disabled = true;
            axis.setAttribute("aria-hidden", "true");
            axis.removeAttribute("title");
            axis.removeAttribute("aria-label");
            return;
        }

        const {size, foldable, fold, from} = stretch;
        gap.style.setProperty("--gap", (fold ? yearLength : size) + "px");
        gap.classList.toggle("aoeGapCollapsed", fold);
        axis.disabled = !foldable;
        if (foldable) {
            const label = t(fold ? "aoe2.expand" : "aoe2.collapse", {years: Math.round(size / yearLength)});
            axis.removeAttribute("aria-hidden");
            axis.title = label;
            axis.setAttribute("aria-label", label);
        }
        else {
            axis.setAttribute("aria-hidden", "true");
            axis.removeAttribute("title");
            axis.removeAttribute("aria-label");
        }

        if (fold) {
            return;
        }
        /// Kept half a row clear of either end, where the steps' own years are.
        for (let decade = Math.ceil(from / 10) * 10; (decade - from) * yearLength <= size - yearLength / 2; decade += 10) {
            const offset = (decade - from) * yearLength;
            if (offset < yearLength / 2) {
                continue;
            }
            const label = document.createElement("span");
            label.className = "aoeDecade";
            label.setAttribute("aria-hidden", "true");
            label.textContent = aoeYearText(decade);
            label.style.top = offset + "px";
            axis.append(label);
        }
    });
}

/// Rows wrap differently at another width, and a late font changes their height.
let aoeScaleFrame = null;
window.addEventListener("resize", () => {
    if (aoeScaleFrame === null) {
        aoeScaleFrame = requestAnimationFrame(() => {
            aoeScaleFrame = null;
            layoutAoeScale();
        });
    }
});
document.fonts.ready.then(layoutAoeScale);
layoutAoeScale();

/// What was clicked stays where it was on screen, whatever the layout and the
/// browser's own scroll anchoring did around it.
function keepInView(element, change) {
    const before = element.getBoundingClientRect().top;
    change();
    const moved = element.getBoundingClientRect().top - before;
    if (Math.abs(moved) > 0.5) {
        window.scrollBy(0, moved);
    }
}

/// A folded stretch or an unfolded year is saved to the account, and undone if
/// that fails. One save per place at a time, so two quick clicks cannot land
/// out of order.
const aoeFolding = new Set();

function saveAoeFold(list, key, on, undo) {
    const id = list + " " + key;
    aoeFolding.add(id);
    aoeStatus.textContent = "";

    postJson(AOE2_FOLD_ENDPOINT, {list, key, on})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
        })
        .catch(error => {
            undo();
            aoeStatus.textContent = t("aoe2.saveFailed", {error: error.message});
        })
        .finally(() => aoeFolding.delete(id));
}

function applyAoeGroup(head, expanded) {
    head.toggleAttribute("data-expanded", expanded);
    head.querySelector(".aoeGroupToggle").setAttribute("aria-expanded", String(expanded));
    aoeGroupRows(head.dataset.group).forEach(row => {
        if (expanded) {
            row.removeAttribute("hidden");
        }
        else {
            row.setAttribute("hidden", AOE2_HIDDEN);
        }
    });
}

function setAoeGroup(head, expanded) {
    applyAoeGroup(head, expanded);
    layoutAoeScale();
}

function setAoeStretch(gap, collapsed) {
    gap.toggleAttribute("data-collapsed", collapsed);
    layoutAoeScale();
}

function toggleAoeGroup(head, expanded, keep) {
    const key = head.dataset.group;
    if (aoeFolding.has("expanded " + key)) {
        return;
    }
    const set = on => keep ? keepInView(head, () => setAoeGroup(head, on)) : setAoeGroup(head, on);
    set(expanded);
    saveAoeFold("expanded", key, expanded, () => set(!expanded));
}

if (aoeTimeline) {
    aoeTimeline.addEventListener("click", event => {
        const toggle = event.target.closest(".aoeGroupToggle");
        if (toggle) {
            const head = toggle.closest(".aoeGroup");
            toggleAoeGroup(head, !head.hasAttribute("data-expanded"), true);
            return;
        }

        const axis = event.target.closest(".aoeAxis");
        if (!axis || axis.disabled) {
            return;
        }
        const gap = axis.closest(".aoeGap");
        const key = gap.dataset.next;
        if (aoeFolding.has("collapsed " + key)) {
            return;
        }
        const collapsed = !gap.hasAttribute("data-collapsed");
        if (collapsed && gap.getBoundingClientRect().top < 0) {
            /// Scrolled past its start, folding it puts the one row it becomes under
            /// the cursor (the owner's wish). A click from the keyboard has no
            /// cursor, so there it goes a row down from the top.
            setAoeStretch(gap, true);
            const folded = gap.getBoundingClientRect();
            const y = event.detail ? event.clientY : folded.height * 1.5;
            window.scrollBy(0, folded.top + folded.height / 2 - y);
        }
        else {
            keepInView(gap, () => setAoeStretch(gap, collapsed));
        }
        saveAoeFold("collapsed", key, collapsed, () => keepInView(gap, () => setAoeStretch(gap, !collapsed)));
    });

    /// The browser's find has matched a scenario folded into a year: unfold the
    /// year as a click would, before the browser scrolls to the match.
    aoeTimeline.addEventListener("beforematch", event => {
        const row = event.target.closest(".aoeRow[data-group]");
        const head = row ? aoeGroupHead(row.dataset.group) : null;
        if (head && !head.hasAttribute("data-expanded")) {
            toggleAoeGroup(head, true, false);
        }
    });
}

/// Fold or unfold every year, or every stretch, at once: the page is laid out
/// once and saved in one request, and the row nearest the top of the screen
/// stays where it was. Only the places that change are sent, and a stretch
/// only while it is long enough to fold.
const aoeFoldAll = document.querySelector(".aoeFoldAll");

function foldAllAoe(list, on) {
    const places = list === "expanded"
        ? [...aoeTimeline.querySelectorAll(".aoeGroup")].filter(head => head.hasAttribute("data-expanded") !== on)
        : [...aoeTimeline.querySelectorAll(".aoeGap")].filter(gap => !gap.querySelector(".aoeAxis").disabled && gap.hasAttribute("data-collapsed") !== on);
    const keys = places.map(place => list === "expanded" ? place.dataset.group : place.dataset.next);
    if (!keys.length || keys.some(key => aoeFolding.has(list + " " + key))) {
        return;
    }

    const apply = state => {
        places.forEach(place => {
            if (list === "expanded") {
                applyAoeGroup(place, state);
            }
            else {
                place.toggleAttribute("data-collapsed", state);
            }
        });
        layoutAoeScale();
    };
    const anchor = [...aoeTimeline.querySelectorAll(".aoeRow:not([hidden]), .aoeGroup")].find(item => item.getBoundingClientRect().bottom > 0);
    const keep = state => anchor ? keepInView(anchor, () => apply(state)) : apply(state);

    keep(on);
    aoeStatus.textContent = "";
    keys.forEach(key => aoeFolding.add(list + " " + key));
    aoeFoldAll.querySelectorAll("button").forEach(button => {
        button.disabled = true;
    });

    postJson(AOE2_FOLD_ENDPOINT, {list, keys, on})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
        })
        .catch(error => {
            keep(!on);
            aoeStatus.textContent = t("aoe2.saveFailed", {error: error.message});
        })
        .finally(() => {
            keys.forEach(key => aoeFolding.delete(list + " " + key));
            aoeFoldAll.querySelectorAll("button").forEach(button => {
                button.disabled = false;
            });
        });
}

if (aoeFoldAll) {
    aoeFoldAll.addEventListener("click", event => {
        const button = event.target.closest("button[data-list]");
        if (button) {
            foldAllAoe(button.dataset.list, button.dataset.on === "1");
        }
    });
}

function updateAoeProgress() {
    const boxes = [...document.querySelectorAll(".aoeCheck")];
    const done = boxes.filter(box => box.checked).length;
    aoeProgress.textContent = t("aoe2.progress", {done, total: boxes.length});
}

/// A folded year has a dot per scenario, filled while that scenario is ticked,
/// and its header greys out once every one of them is.
function refreshAoeGroup(row) {
    const group = row.dataset.group;
    if (!group) {
        return;
    }
    const head = aoeGroupHead(group);
    const rows = aoeGroupRows(group);
    const ticked = new Set(rows.filter(item => item.querySelector(".aoeCheck").checked).map(item => item.dataset.key));
    head.querySelectorAll(".aoeDot[data-key]").forEach(dot => {
        dot.classList.toggle("aoeDotDone", ticked.has(dot.dataset.key));
    });
    head.classList.toggle("aoeGroupDone", ticked.size === rows.length);
}

document.addEventListener("change", event => {
    const box = event.target.closest(".aoeCheck");
    if (!box) {
        return;
    }

    const row = box.closest(".aoeRow");
    const done = box.checked;

    box.disabled = true;
    row.classList.add("aoeSaving");
    aoeStatus.textContent = "";
    updateAoeProgress();
    refreshAoeGroup(row);
    /// Done text is bold, so a row may wrap differently and change height.
    keepInView(row, layoutAoeScale);

    postJson(AOE2_CHECK_ENDPOINT, {key: box.value, done})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
        })
        .catch(error => {
            box.checked = !done;
            updateAoeProgress();
            refreshAoeGroup(row);
            keepInView(row, layoutAoeScale);
            aoeStatus.textContent = t("aoe2.saveFailed", {error: error.message});
        })
        .finally(() => {
            box.disabled = false;
            row.classList.remove("aoeSaving");
        });
});

/// What the account writes about a scenario: a cell holds a button showing the
/// text, and a click swaps it for a box, saved on Enter or on leaving it and
/// abandoned on Escape. A descriptor or note is a textarea, where Shift+Enter
/// is a line break, as in music's note editor; the cell cuts it off, so the
/// whole of it is its hover box. A YouTube link is a ▶ that opens it and a ✎
/// holding it in data-value, which edits it. The cell sits above the layer
/// that ticks the row, so a click on it edits rather than ticks. The editor is
/// taller than the row, so every change lays the to-scale timeline out again.
const AOE2_ENTRY_ENDPOINT = "/games/ajax/aoe2-entry";
const AOE2_ENTRY_LIMITS = {descriptor: 100, rating: 10, note: 2000, youtube: 200};
const AOE2_MULTILINE_FIELDS = ["descriptor", "note"];

function aoeFieldValue(button) {
    return "value" in button.dataset ? button.dataset.value : button.textContent;
}

function setAoeField(cell, text) {
    const button = cell.querySelector(".aoeFieldText");
    if ("value" in button.dataset) {
        button.dataset.value = text;
        button.textContent = text === "" ? "" : "✎";
        let link = cell.querySelector(".aoeFieldLink");
        if (text !== "" && !link) {
            link = document.createElement("a");
            link.className = "aoeFieldLink";
            link.target = "_blank";
            link.rel = "noopener";
            link.textContent = "▶";
            link.dataset.tooltip = t("aoe2.watchYoutube");
            link.setAttribute("aria-label", t("aoe2.watchYoutubeFor", {scenario: button.dataset.scenario}));
            cell.insertBefore(link, button);
        }
        if (text === "" && link) {
            link.remove();
        }
        if (link && text !== "") {
            link.href = text;
        }
    }
    else {
        button.textContent = text;
    }
    if ("tooltip" in button.dataset) {
        button.dataset.tooltip = text;
    }
    button.setAttribute("aria-label", t(text === "" ? "aoe2.fieldLabel" : "aoe2.fieldLabelValue", {
        column: t("aoe2.column." + cell.dataset.field),
        scenario: button.dataset.scenario,
        value: text,
    }));
}

function editAoeField(cell) {
    if (cell.querySelector(".aoeFieldInput") || cell.classList.contains("aoeSaving")) {
        return;
    }
    /// The button it hovered goes away under the box, with no mouseout to hide it.
    if (typeof hideGamesTooltip === "function") {
        hideGamesTooltip();
    }

    const row = cell.closest(".aoeRow");
    const button = cell.querySelector(".aoeFieldText");
    const field = cell.dataset.field;
    const before = aoeFieldValue(button);
    const multiline = AOE2_MULTILINE_FIELDS.includes(field);
    const input = document.createElement(multiline ? "textarea" : "input");
    if (multiline) {
        input.rows = 3;
    }
    else {
        input.type = "text";
    }
    input.className = "aoeFieldInput";
    input.value = before;
    input.maxLength = AOE2_ENTRY_LIMITS[field];
    if (field === "rating") {
        input.inputMode = "decimal";
    }
    if (field === "youtube") {
        input.inputMode = "url";
    }
    /// A YouTube link's ▶ makes way for the box while it is open.
    const link = cell.querySelector(".aoeFieldLink");

    let finished = false;
    const finish = (save, refocus) => {
        if (finished) {
            return;
        }
        finished = true;
        const value = input.value.trim();
        const changed = save && value !== before;
        keepInView(row, () => {
            input.remove();
            button.hidden = false;
            if (link) {
                link.hidden = false;
            }
            setAoeField(cell, changed ? value : before);
            layoutAoeScale();
        });
        if (refocus) {
            button.focus();
        }
        if (!changed) {
            return;
        }

        cell.classList.add("aoeSaving");
        aoeStatus.textContent = "";
        const settle = text => keepInView(row, () => {
            setAoeField(cell, text);
            layoutAoeScale();
        });
        postJson(AOE2_ENTRY_ENDPOINT, {key: row.dataset.key, field, value})
            .then(result => {
                if (result.status !== "ok") {
                    throw new Error(result.message);
                }
                settle(result.value);
            })
            .catch(error => {
                settle(before);
                aoeStatus.textContent = t("aoe2.saveFailed", {error: error.message});
            })
            .finally(() => cell.classList.remove("aoeSaving"));
    };

    input.addEventListener("keydown", event => {
        if ((event.key === "Enter" && !event.shiftKey) || event.key === "Escape") {
            event.preventDefault();
            finish(event.key === "Enter", true);
        }
    });
    input.addEventListener("blur", () => finish(true, false));

    keepInView(row, () => {
        button.hidden = true;
        if (link) {
            link.hidden = true;
        }
        cell.appendChild(input);
        layoutAoeScale();
    });
    input.focus();
    input.select();
}

document.addEventListener("click", event => {
    const button = event.target.closest(".aoeFieldText");
    if (button) {
        editAoeField(button.closest(".aoeField"));
    }
});
