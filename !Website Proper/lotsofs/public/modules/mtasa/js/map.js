"use strict";

const MTASA_FIELD_ENDPOINT = "/mtasa/ajax/map-field";
const MTASA_COMMENT_ENDPOINT = "/mtasa/ajax/comment";

const mtasaMap = document.getElementById("mtasaMap");
const mtasaStatus = document.getElementById("mtasaStatus");
const mtasaMapId = Number(mtasaMap.dataset.mapId);

function mtasaSay(text) {
    mtasaStatus.textContent = text;
}

/// A value as the page shows it, built with textContent only: a map id links
/// to that map, a URL links out.
function mtasaShowValue(shown, type, value, text) {
    shown.replaceChildren();
    if (text === "") {
        return;
    }
    if (type === "map" || type === "url") {
        const link = document.createElement("a");
        link.href = type === "map" ? "/mtasa/map?id=" + encodeURIComponent(value) : value;
        if (type === "url") {
            link.target = "_blank";
            link.rel = "noopener";
        }
        link.textContent = text;
        shown.appendChild(link);
        return;
    }
    shown.textContent = text;
}

/// After an owner's field paired with a guess changes (the original name with
/// the title, the gamemode with the category guess): once set it hides the rows
/// that stand in for it, and the ✓ and ! follow whether it matches the guess.
function mtasaSyncPair(pair, value) {
    const field = document.getElementById("field-" + pair);
    if (!field || !("guess" in field.dataset)) {
        return;
    }
    const owned = value.trim();
    const known = owned !== "" && owned !== field.dataset.notFound;
    const guess = field.dataset.guess;
    const differs = known && owned !== guess;
    document.querySelectorAll(`.mtasaHideWhenSet[data-pair="${pair}"]`).forEach(row => {
        row.hidden = known;
    });
    document.querySelectorAll(`.mtasaGuessMismatch[data-pair="${pair}"]`).forEach(mismatch => {
        mismatch.hidden = !differs;
        mismatch.dataset.tooltip = t("map.guessMismatch." + pair, {value: owned});
        mismatch.setAttribute("aria-label", mismatch.dataset.tooltip);
    });
    document.querySelectorAll(`.mtasaUseGuess[data-pair="${pair}"]`).forEach(useGuess => {
        const set = owned === guess;
        const text = set ? t("map.guessIsSet." + pair) : t("map.useGuess." + pair, {value: guess});
        useGuess.setAttribute("aria-disabled", set ? "true" : "false");
        useGuess.dataset.tooltip = text;
        useGuess.setAttribute("aria-label", text);
    });
}

/// Saves one field and shows what the server stored.
function mtasaSaveField(cell, field, value) {
    const button = cell.querySelector(".mtasaEdit");
    const shown = cell.querySelector(".mtasaFieldShown");
    cell.classList.add("mtasaSaving");
    mtasaSay("");
    return postJson(MTASA_FIELD_ENDPOINT, {mapId: mtasaMapId, field, value})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
            button.dataset.value = result.value;
            mtasaShowValue(shown, button.dataset.type, result.value, result.text);
            mtasaSyncPair(field, result.value);
        })
        .catch(error => mtasaSay(t("map.saveFailed", {error: error.message})))
        .finally(() => cell.classList.remove("mtasaSaving"));
}

/// The Finished checkbox and the Status and Gamemode dropdowns save on change,
/// putting the old value back when refused.
function mtasaSaveControl(control) {
    const field = control.closest(".mtasaField").dataset.field;
    const isCheck = control.type === "checkbox";
    const value = isCheck ? (control.checked ? "1" : "0") : control.value;
    const cell = control.closest(".mtasaFieldValue");
    if (!isCheck) {
        control.dataset.choice = value;
    }
    cell.classList.add("mtasaSaving");
    control.disabled = true;
    mtasaSay("");
    postJson(MTASA_FIELD_ENDPOINT, {mapId: mtasaMapId, field, value})
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
            if (!isCheck) {
                control.dataset.saved = result.value;
                control.dataset.choice = result.value;
                mtasaSyncPair(field, result.value);
            }
        })
        .catch(error => {
            if (isCheck) {
                control.checked = !control.checked;
            }
            else {
                control.value = control.dataset.saved;
                control.dataset.choice = control.dataset.saved;
            }
            mtasaSay(t("map.saveFailed", {error: error.message}));
        })
        .finally(() => {
            cell.classList.remove("mtasaSaving");
            control.disabled = false;
        });
}

mtasaMap.addEventListener("change", event => {
    const control = event.target.closest(".mtasaCheckField, .mtasaChoiceSelect");
    if (control) {
        mtasaSaveControl(control);
    }
});

/// Click to edit, as the games module's fields: the ✎ turns into a box (a
/// select for a list or a yes/no), saved on Enter or on leaving it, abandoned
/// on Escape. A refusal puts the old value back and says why.
function mtasaEditField(button) {
    const cell = button.closest(".mtasaFieldValue");
    if (cell.querySelector(".mtasaFieldInput") || cell.classList.contains("mtasaSaving")) {
        return;
    }
    const field = button.closest(".mtasaField").dataset.field;
    const type = button.dataset.type;
    const shown = cell.querySelector(".mtasaFieldShown");
    const before = button.dataset.value;

    let input;
    if (type === "bool" || "choices" in button.dataset) {
        input = document.createElement("select");
        const options = type === "bool"
            ? [["", t("map.unset")], ["1", t("value.yes")], ["0", t("value.no")]]
            : [["", t("map.unset")], ...JSON.parse(button.dataset.choices).map(choice => [choice, choice])];
        /// A stored value outside the list stays choosable, so opening the box changes nothing.
        if (before !== "" && !options.some(([value]) => value === before)) {
            options.push([before, before]);
        }
        options.forEach(([value, text]) => {
            const option = document.createElement("option");
            option.value = value;
            option.textContent = text;
            input.appendChild(option);
        });
    }
    else if (type === "longtext") {
        input = document.createElement("textarea");
        input.rows = 4;
    }
    else {
        input = document.createElement("input");
        input.type = "text";
        if (type === "map") {
            input.inputMode = "numeric";
        }
        if (type === "url") {
            input.inputMode = "url";
        }
    }
    input.className = "mtasaFieldInput";
    input.value = before;
    input.setAttribute("aria-label", button.getAttribute("aria-label"));

    let finished = false;
    const finish = save => {
        if (finished) {
            return;
        }
        finished = true;
        const value = input.value.trim();
        input.remove();
        shown.hidden = false;
        button.hidden = false;
        if (!save || value === before) {
            button.focus();
            return;
        }

        mtasaSaveField(cell, field, value);
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

    shown.hidden = true;
    button.hidden = true;
    cell.appendChild(input);
    input.focus();
    if (input.select) {
        input.select();
    }
}

/// One field's threads open at a time, unfolded beside the field on a wide
/// screen and under it on a narrow one (the stylesheet decides which).
function mtasaToggleThreads(badge) {
    const field = badge.closest(".mtasaField");
    const panel = field.querySelector(".mtasaThreads");
    const opening = panel.hidden;

    mtasaMap.querySelectorAll(".mtasaField .mtasaThreads:not([hidden])").forEach(other => {
        other.hidden = true;
        other.closest(".mtasaField").classList.remove("mtasaFieldOpen");
        other.closest(".mtasaField").querySelector(".mtasaBadge").setAttribute("aria-expanded", "false");
    });

    panel.hidden = !opening;
    field.classList.toggle("mtasaFieldOpen", opening);
    badge.setAttribute("aria-expanded", opening ? "true" : "false");
    if (opening) {
        const box = panel.querySelector(".mtasaNewThread textarea");
        if (box && !panel.querySelector(".mtasaThread")) {
            box.focus();
        }
    }
}

/// The badge follows what the endpoint says is open now.
function mtasaUpdateBadge(panel, open) {
    const field = panel.closest(".mtasaField");
    if (!field) {
        return;
    }
    const badge = field.querySelector(".mtasaBadge");
    const hasThreads = panel.querySelector(".mtasaThread") !== null;
    badge.classList.toggle("mtasaBadgeEmpty", !hasThreads);
    badge.classList.toggle("mtasaBadgeOpen", open > 0);
    badge.replaceChildren();
    if (hasThreads) {
        badge.append("💬︎ ");
        const count = document.createElement("span");
        count.className = "mtasaBadgeCount";
        count.textContent = open;
        badge.appendChild(count);
    }
    else {
        badge.textContent = "+";
    }
    const label = field.querySelector(".mtasaFieldLabel").textContent;
    badge.setAttribute("aria-label", t(hasThreads ? "comments.badgeLabel" : "comments.addLabel", {field: label, count: open}));
}

/// Sends one change and swaps in the field's threads as the server draws them.
function mtasaSendComment(panel, body, control) {
    if (panel.classList.contains("mtasaSaving")) {
        return Promise.resolve(false);
    }
    panel.classList.add("mtasaSaving");
    if (control) {
        control.disabled = true;
    }
    mtasaSay("");
    return postJson(MTASA_COMMENT_ENDPOINT, body)
        .then(result => {
            if (result.status !== "ok") {
                throw new Error(result.message);
            }
            const keepResolvedOpen = panel.querySelector(".mtasaResolvedThreads[open]") !== null;
            panel.innerHTML = result.html;
            if (keepResolvedOpen) {
                const resolved = panel.querySelector(".mtasaResolvedThreads");
                if (resolved) {
                    resolved.open = true;
                }
            }
            mtasaUpdateBadge(panel, result.open);
            return true;
        })
        .catch(error => {
            mtasaSay(t("comments.saveFailed", {error: error.message}));
            return false;
        })
        .finally(() => {
            panel.classList.remove("mtasaSaving");
            if (control) {
                control.disabled = false;
            }
        });
}

/// Edits a comment in place: its text becomes a box with Save and Cancel.
function mtasaEditComment(comment, panel) {
    if (comment.querySelector(".mtasaCommentEditor")) {
        return;
    }
    const bodyText = comment.querySelector(".mtasaCommentBody");
    const editor = document.createElement("form");
    editor.className = "mtasaCommentEditor";
    const box = document.createElement("textarea");
    box.rows = 3;
    box.value = bodyText.textContent;
    box.setAttribute("aria-label", t("comments.edit"));
    const save = document.createElement("button");
    save.type = "submit";
    save.textContent = t("comments.save");
    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.textContent = t("comments.cancel");
    editor.append(box, save, cancel);

    const close = () => {
        editor.remove();
        bodyText.hidden = false;
    };
    cancel.addEventListener("click", close);
    box.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            event.preventDefault();
            close();
        }
    });
    editor.addEventListener("submit", event => {
        event.preventDefault();
        mtasaSendComment(panel, {action: "edit", id: Number(comment.dataset.commentId), body: box.value}, save);
    });

    bodyText.hidden = true;
    bodyText.after(editor);
    box.focus();
}

mtasaMap.addEventListener("click", event => {
    const edit = event.target.closest(".mtasaEdit");
    if (edit) {
        mtasaEditField(edit);
        return;
    }

    /// A ✓ beside a guess says "this is correct": it becomes the owner's value.
    const useGuess = event.target.closest(".mtasaUseGuess");
    if (useGuess) {
        if (useGuess.getAttribute("aria-disabled") === "true") {
            return;
        }
        const owner = document.getElementById("field-" + useGuess.dataset.pair);
        const cell = owner.querySelector(".mtasaFieldValue");
        /// The gamemode is a dropdown rather than a ✎ box: the guess is chosen in it.
        const select = cell.querySelector(".mtasaChoiceSelect");
        if (select) {
            if (!select.disabled) {
                hideMtasaTooltip();
                select.value = owner.dataset.guess;
                mtasaSaveControl(select);
            }
            return;
        }
        if (!cell.classList.contains("mtasaSaving") && !cell.querySelector(".mtasaFieldInput")) {
            /// Its hover box changes under the cursor with no mouseout, so it would go stale.
            hideMtasaTooltip();
            mtasaSaveField(cell, useGuess.dataset.pair, owner.dataset.guess);
        }
        return;
    }

    /// Hovering shows the mismatch; a tap, where there is no hover, says it.
    const mismatch = event.target.closest(".mtasaGuessMismatch");
    if (mismatch) {
        mtasaSay(mismatch.dataset.tooltip);
        return;
    }

    const badge = event.target.closest(".mtasaBadge");
    if (badge) {
        mtasaToggleThreads(badge);
        return;
    }

    const action = event.target.closest("[data-comment-action]");
    if (action) {
        const panel = action.closest(".mtasaThreads");
        const kind = action.dataset.commentAction;
        if (kind === "edit") {
            mtasaEditComment(action.closest(".mtasaComment"), panel);
            return;
        }
        const target = kind === "delete" ? action.closest(".mtasaComment") : action.closest(".mtasaThread");
        const id = Number(kind === "delete" ? target.dataset.commentId : target.dataset.threadId);
        if (kind === "delete" && !confirm(t("comments.confirmDelete"))) {
            return;
        }
        mtasaSendComment(panel, {action: kind, id}, action);
    }
});

mtasaMap.addEventListener("submit", event => {
    const form = event.target.closest(".mtasaCommentForm");
    if (!form) {
        return;
    }
    event.preventDefault();
    const panel = form.closest(".mtasaThreads");
    const box = form.querySelector("textarea");
    const field = panel.dataset.field;
    mtasaSendComment(panel, {
        action: "add",
        mapId: mtasaMapId,
        field: field === "" ? null : field,
        parent: "parent" in form.dataset ? Number(form.dataset.parent) : null,
        body: box.value,
    }, form.querySelector("button"));
});

/// Enter sends a comment, Shift+Enter breaks the line, as music's note editor.
mtasaMap.addEventListener("keydown", event => {
    if (event.key !== "Enter" || event.shiftKey) {
        return;
    }
    const box = event.target.closest(".mtasaCommentForm textarea, .mtasaCommentEditor textarea");
    if (box) {
        event.preventDefault();
        box.form.requestSubmit();
    }
});

/// A link to a field (from the Comments page) opens its threads.
function mtasaOpenFromHash() {
    const match = location.hash.match(/^#field-([a-z_]+)$/);
    if (!match) {
        return;
    }
    const field = document.getElementById("field-" + match[1]);
    const badge = field && field.querySelector(".mtasaBadge");
    if (badge && field.querySelector(".mtasaThreads").hidden) {
        mtasaToggleThreads(badge);
        field.scrollIntoView({block: "center"});
    }
}
mtasaOpenFromHash();
window.addEventListener("hashchange", mtasaOpenFromHash);
