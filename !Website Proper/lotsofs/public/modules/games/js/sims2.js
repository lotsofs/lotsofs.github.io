"use strict";

const SIMS2_CHECK_ENDPOINT = "/games/ajax/sims2-check";

const simsStatus = document.getElementById("simsStatus");
const simsLists = document.querySelector(".simsLists");

/// The packed layout's row height, in px (see .simsPacked).
const SIMS2_ROW = 2;

/// Packs the lists, in page order, onto the page's columns with no gaps
/// (the owner's wish): a grid's card spans as many columns as its boxes need,
/// and every card spans as many 2px rows as its height and the gap under it
/// take, so it ends where the next one in its column begins. The CSS grid
/// places them, filling holes with later cards (`dense`). Measured once and
/// written once: spans first, then the heights, which can follow from them.
function packSimsLists() {
    if (!simsLists) {
        return;
    }
    simsLists.classList.add("simsPacked");
    const style = getComputedStyle(simsLists);
    const columns = style.gridTemplateColumns.split(" ").map(parseFloat);
    const gap = parseFloat(style.columnGap);
    const cards = [...simsLists.children];

    cards.filter(card => card.classList.contains("simsGridList")).forEach(card => {
        const box = getComputedStyle(card);
        const chrome = ["paddingLeft", "paddingRight", "borderLeftWidth", "borderRightWidth"].reduce((sum, side) => sum + parseFloat(box[side]), 0);
        const needed = card.querySelector(".simsGrid").getBoundingClientRect().width + chrome;
        /// Less a hair, so a grid exactly two columns wide is not given three for a rounding.
        const span = Math.ceil((needed + gap) / (columns[0] + gap) - 0.01);
        card.style.gridColumn = "span " + Math.min(columns.length, Math.max(1, span));
    });

    const heights = cards.map(card => card.getBoundingClientRect().height);
    cards.forEach((card, i) => {
        card.style.gridRowEnd = "span " + Math.ceil((heights[i] + gap) / SIMS2_ROW);
    });
}

let simsPackFrame = null;
function schedulePackSimsLists() {
    if (simsPackFrame === null) {
        simsPackFrame = requestAnimationFrame(() => {
            simsPackFrame = null;
            packSimsLists();
        });
    }
}

packSimsLists();
document.fonts.ready.then(packSimsLists);
window.addEventListener("resize", schedulePackSimsLists);

/// A list's "N of M" follows its boxes as they are ticked.
function updateSimsProgress(list) {
    const boxes = list.querySelectorAll(".simsCheck");
    const done = [...boxes].filter(box => box.checked).length;
    list.querySelector(".simsProgress").textContent = t("sims2.progress", {done, total: boxes.length});
}

/// A tick is saved at once, and undone with a message if that fails.
document.addEventListener("change", event => {
    const box = event.target.closest(".simsCheck");
    if (!box) {
        return;
    }

    const list = box.closest(".simsList");
    const done = box.checked;

    box.disabled = true;
    simsStatus.textContent = "";
    updateSimsProgress(list);
    /// A done item is bold, so it may wrap onto another line.
    schedulePackSimsLists();

    postJson(SIMS2_CHECK_ENDPOINT, {key: box.value, done})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
        })
        .catch(error => {
            box.checked = !done;
            updateSimsProgress(list);
            schedulePackSimsLists();
            simsStatus.textContent = t("sims2.saveFailed", {error: error.message});
        })
        .finally(() => {
            box.disabled = false;
        });
});

/// The written-up vehicles, which admins edit in place, as the Age of Empires
/// page's fields are: a double click on a name or an occasion swaps it for a
/// box, saved on Enter or on leaving it and abandoned on Escape. A − removes
/// one, and a + opens a box for another, where Enter saves and opens the next,
/// as music's other names do. Every save answers with the vehicle's row as the
/// page draws it, which replaces the old one.
const SIMS2_VEHICLE_ENDPOINT = "/games/ajax/sims2-vehicle";
const SIMS2_NAME_MAX = 100;
const SIMS2_OCCASION_MAX = 200;
/// How long a click on an admin's vehicle name waits to tick it, in case it
/// is the first of a double click, which edits instead.
const SIMS2_DOUBLE_CLICK_MS = 250;

const simsVehicles = document.querySelector(".simsVehicles");
const simsVehiclesEditable = simsVehicles?.dataset.editable === "1";

/// After the card's rows change: its count, and the packing, since its height did.
function simsVehiclesChanged() {
    updateSimsProgress(simsVehicles.closest(".simsList"));
    schedulePackSimsLists();
}

/// Posts a change and puts the answer's row in place of `row`, or at the end
/// for a new vehicle, or takes `row` away for a delete. Resolves to the new
/// row, or to null, with the reason shown when it failed.
function saveSimsVehicle(body, row) {
    simsStatus.textContent = "";
    row?.classList.add("simsSaving");
    return postJson(SIMS2_VEHICLE_ENDPOINT, body)
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message || result.error);
            }
            if (!result.html) {
                row.remove();
                simsVehiclesChanged();
                return null;
            }
            const template = document.createElement("template");
            template.innerHTML = result.html.trim();
            const fresh = template.content.firstElementChild;
            if (row) {
                row.replaceWith(fresh);
            }
            else {
                simsVehicles.appendChild(fresh);
            }
            simsVehiclesChanged();
            return fresh;
        })
        .catch(error => {
            row?.classList.remove("simsSaving");
            simsStatus.textContent = t("sims2.saveFailed", {error: error.message});
            return null;
        });
}

/// A box in place of `element` (a name, an occasion or a + button), which
/// comes back when it closes. commit(value, viaEnter) gets the trimmed value
/// on Enter or on leaving the box, when it changed.
function openSimsBox(element, {value = "", placeholder = "", maxLength}, commit) {
    const input = document.createElement("input");
    input.type = "text";
    input.className = "simsEditInput";
    input.value = value;
    input.placeholder = placeholder;
    input.maxLength = maxLength;

    let finished = false;
    const finish = (save, viaEnter) => {
        if (finished) {
            return;
        }
        finished = true;
        const typed = input.value.trim();
        input.remove();
        element.hidden = false;
        schedulePackSimsLists();
        if (save && typed !== value) {
            commit(typed, viaEnter);
        }
    };
    input.addEventListener("keydown", event => {
        if (event.key === "Enter" || event.key === "Escape") {
            event.preventDefault();
            finish(event.key === "Enter", event.key === "Enter");
        }
    });
    input.addEventListener("blur", () => finish(true, false));

    element.hidden = true;
    element.after(input);
    schedulePackSimsLists();
    input.focus();
    input.select();
}

function simsOccasionSpans(row) {
    return [...row.querySelectorAll(".simsOccasionText")];
}

/// The whole list goes with every change, so an edit, an addition and a
/// removal are one write each. An occasion emptied is dropped by the server.
function saveSimsOccasions(row, occasions) {
    return saveSimsVehicle({action: "occasions", id: Number(row.dataset.id), occasions}, row);
}

function renameSimsVehicle(name) {
    const row = name.closest(".simsVehicle");
    openSimsBox(name, {value: name.textContent, maxLength: SIMS2_NAME_MAX}, value => {
        saveSimsVehicle({action: "rename", id: Number(row.dataset.id), name: value}, row);
    });
}

function editSimsOccasion(span) {
    const row = span.closest(".simsVehicle");
    const spans = simsOccasionSpans(row);
    const index = spans.indexOf(span);
    openSimsBox(span, {value: span.textContent, maxLength: SIMS2_OCCASION_MAX}, value => {
        const occasions = spans.map(each => each.textContent);
        occasions[index] = value;
        saveSimsOccasions(row, occasions);
    });
}

function addSimsOccasion(button) {
    const row = button.closest(".simsVehicle");
    openSimsBox(button, {placeholder: t("sims2.occasionPlaceholder"), maxLength: SIMS2_OCCASION_MAX}, (value, viaEnter) => {
        const occasions = simsOccasionSpans(row).map(each => each.textContent);
        saveSimsOccasions(row, [...occasions, value]).then(fresh => {
            if (fresh && viaEnter) {
                addSimsOccasion(fresh.querySelector(".simsOccasionAdd"));
            }
        });
    });
}

function addSimsVehicle(button) {
    openSimsBox(button, {placeholder: t("sims2.vehicleNamePlaceholder"), maxLength: SIMS2_NAME_MAX}, (value, viaEnter) => {
        saveSimsVehicle({action: "add", name: value}, null).then(fresh => {
            if (fresh && viaEnter) {
                addSimsVehicle(button);
            }
        });
    });
}

if (simsVehiclesEditable) {
    let tickTimer = null;

    document.addEventListener("click", event => {
        if (event.target.closest(".simsSaving")) {
            return;
        }
        /// A name is inside its tick's label: a single click still ticks, a
        /// moment late, and a double click edits instead.
        const name = event.target.closest(".simsVehicleName");
        if (name) {
            event.preventDefault();
            clearTimeout(tickTimer);
            if (event.detail <= 1) {
                const box = name.closest(".simsItem").querySelector(".simsCheck");
                tickTimer = setTimeout(() => box.click(), SIMS2_DOUBLE_CLICK_MS);
            }
            return;
        }

        const removeVehicle = event.target.closest(".simsVehicleRemove");
        if (removeVehicle) {
            const row = removeVehicle.closest(".simsVehicle");
            const label = row.querySelector(".simsVehicleName").textContent;
            if (confirm(t("sims2.vehicleDeleteConfirm", {name: label}))) {
                saveSimsVehicle({action: "delete", id: Number(row.dataset.id)}, row);
            }
            return;
        }

        const removeOccasion = event.target.closest(".simsOccasionRemove");
        if (removeOccasion) {
            const row = removeOccasion.closest(".simsVehicle");
            const gone = removeOccasion.closest(".simsOccasion").querySelector(".simsOccasionText");
            const occasions = simsOccasionSpans(row).filter(span => span !== gone).map(span => span.textContent);
            saveSimsOccasions(row, occasions);
            return;
        }

        const addOccasion = event.target.closest(".simsOccasionAdd");
        if (addOccasion) {
            addSimsOccasion(addOccasion);
            return;
        }

        const addVehicle = event.target.closest(".simsVehicleAdd");
        if (addVehicle) {
            addSimsVehicle(addVehicle);
        }
    });

    document.addEventListener("dblclick", event => {
        if (event.target.closest(".simsSaving")) {
            return;
        }
        const name = event.target.closest(".simsVehicleName");
        if (name) {
            clearTimeout(tickTimer);
            renameSimsVehicle(name);
            return;
        }
        const occasion = event.target.closest(".simsOccasionText");
        if (occasion) {
            editSimsOccasion(occasion);
        }
    });
}
