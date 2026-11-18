"use strict";

/// The list's cells an admin edits by clicking them (the owner's wish): the
/// status, the gamemode and the custom description become a box,
/// saved on Enter or on leaving it and abandoned on Escape, as the map page's
/// fields; Finished is a checkbox, ticked by a click anywhere in its cell.
/// Loaded for admins only.
const MTASA_LIST_ENDPOINT = "/mtasa/ajax/map-field";

const mtasaListTable = document.getElementById("mtasaList");
const mtasaListStatus = document.getElementById("mtasaStatus");
const mtasaListChoices = JSON.parse(mtasaListTable.dataset.choices || "{}");

function mtasaListSay(text) {
    mtasaListStatus.textContent = text;
}

function mtasaListSave(cell, field, value) {
    mtasaListSay("");
    return postJson(MTASA_LIST_ENDPOINT, {mapId: Number(cell.closest("tr").dataset.mapId), field, value})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
            return result;
        });
}

/// Draws a cell from what the server stored, with textContent only.
function mtasaListShow(cell, field, result) {
    cell.replaceChildren();
    if (field in mtasaListChoices) {
        cell.dataset.value = result.value;
        if (result.value !== "") {
            const pill = document.createElement("span");
            pill.className = "mtasaChoicePill";
            pill.dataset.choice = result.value;
            pill.textContent = result.value;
            cell.appendChild(pill);
        }
        /// With no gamemode the script's guess shows again, faded.
        else if (cell.dataset.guess) {
            const guess = document.createElement("span");
            guess.className = "mtasaGuess";
            guess.dataset.tooltip = t("list.guess");
            guess.textContent = cell.dataset.guess;
            cell.appendChild(guess);
        }
        return;
    }
    /// The custom description: one line, whole in its hover box.
    cell.dataset.value = result.value;
    cell.dataset.tooltip = result.value;
    cell.textContent = result.value.replace(/\s+/g, " ");
}

function mtasaListEdit(cell) {
    if (cell.classList.contains("mtasaEditing") || cell.classList.contains("mtasaSaving")) {
        return;
    }
    const field = cell.dataset.editField;
    const type = cell.dataset.type;
    const before = cell.dataset.value;

    let input;
    if (type === "choice") {
        input = document.createElement("select");
        const choices = ["", ...mtasaListChoices[field]];
        /// A stored value outside the list stays choosable, so opening the box changes nothing.
        if (before !== "" && !choices.includes(before)) {
            choices.push(before);
        }
        choices.forEach(choice => {
            const option = document.createElement("option");
            option.value = choice;
            option.textContent = choice === "" ? t("map.unset") : choice;
            if (choice !== "") {
                option.dataset.choice = choice;
            }
            input.appendChild(option);
        });
    }
    else if (type === "longtext") {
        input = document.createElement("textarea");
        input.rows = 3;
    }
    else {
        input = document.createElement("input");
        input.type = "text";
    }
    input.className = "mtasaFieldInput mtasaListInput";
    input.value = before;
    input.setAttribute("aria-label", mtasaListTable.querySelectorAll("thead th")[cell.cellIndex].textContent.trim());

    const shown = [...cell.childNodes];
    const tooltip = cell.dataset.tooltip;
    delete cell.dataset.tooltip;
    hideMtasaTooltip();

    let finished = false;
    const finish = save => {
        if (finished) {
            return;
        }
        finished = true;
        const value = input.value.trim();
        cell.classList.remove("mtasaEditing");
        cell.replaceChildren(...shown);
        if (tooltip !== undefined) {
            cell.dataset.tooltip = tooltip;
        }
        if (!save || value === before) {
            return;
        }
        cell.classList.add("mtasaSaving");
        mtasaListSave(cell, field, value)
            .then(result => mtasaListShow(cell, field, result))
            .catch(error => mtasaListSay(t("map.saveFailed", {error: error.message})))
            .finally(() => cell.classList.remove("mtasaSaving"));
    };

    input.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            event.preventDefault();
            finish(false);
        }
        else if (event.key === "Enter" && !(type === "longtext" && event.shiftKey)) {
            event.preventDefault();
            finish(true);
        }
    });
    if (input.tagName === "SELECT") {
        input.addEventListener("change", () => finish(true));
    }
    input.addEventListener("blur", () => finish(true));

    cell.classList.add("mtasaEditing");
    cell.replaceChildren(input);
    input.focus();
    if (input.select) {
        input.select();
    }
}

function mtasaListSaveCheck(box) {
    const cell = box.closest("td");
    cell.classList.add("mtasaSaving");
    box.disabled = true;
    mtasaListSave(cell, "finished", box.checked ? "1" : "0")
        .catch(error => {
            box.checked = !box.checked;
            mtasaListSay(t("map.saveFailed", {error: error.message}));
        })
        .finally(() => {
            cell.classList.remove("mtasaSaving");
            box.disabled = false;
        });
}

mtasaListTable.addEventListener("click", event => {
    const finishedCell = event.target.closest("td.mtasaFinished");
    if (finishedCell) {
        const box = finishedCell.querySelector(".mtasaListCheck");
        /// A click on the box itself ticks it; anywhere else in the cell does too.
        if (box && event.target !== box && !box.disabled) {
            box.checked = !box.checked;
            mtasaListSaveCheck(box);
        }
        return;
    }
    const cell = event.target.closest("td[data-edit-field]");
    if (cell && !event.target.closest(".mtasaListInput")) {
        mtasaListEdit(cell);
    }
});

mtasaListTable.addEventListener("change", event => {
    if (event.target.matches(".mtasaListCheck")) {
        mtasaListSaveCheck(event.target);
    }
});
