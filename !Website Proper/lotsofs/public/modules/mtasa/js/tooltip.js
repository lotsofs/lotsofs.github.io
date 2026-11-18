"use strict";

/// The hover box: put data-tooltip on anything, including what is added after
/// load, since the listeners are delegated. A copy of the games module's, itself
/// a copy of music's cut to that one attribute: the modules ship separate
/// stylesheets and share no scripts.
const mtasaTooltipBox = document.getElementById("mtasaTooltip");

const MTASA_TOOLTIP_OFFSET_X = 30;
const MTASA_TOOLTIP_OFFSET_Y = 10;
const MTASA_TOOLTIP_EDGE = 8;

function hideMtasaTooltip() {
    mtasaTooltipBox.style.display = "none";
    mtasaTooltipBox.style.left = "0";
    mtasaTooltipBox.style.top = "0";
}

/// Follows the cursor, clamped to the window edges.
function moveMtasaTooltip(event) {
    const box = mtasaTooltipBox.getBoundingClientRect();

    let x = event.clientX + MTASA_TOOLTIP_OFFSET_X;
    let y = event.clientY + MTASA_TOOLTIP_OFFSET_Y;

    if (x + box.width > window.innerWidth - MTASA_TOOLTIP_EDGE) {
        x = event.clientX - box.width - MTASA_TOOLTIP_OFFSET_Y;
    }
    if (y + box.height > window.innerHeight - MTASA_TOOLTIP_EDGE) {
        y = window.innerHeight - box.height - MTASA_TOOLTIP_EDGE;
    }

    mtasaTooltipBox.style.left = Math.max(MTASA_TOOLTIP_EDGE, x) + window.scrollX + "px";
    mtasaTooltipBox.style.top = Math.max(MTASA_TOOLTIP_EDGE, y) + window.scrollY + "px";
}

/// data-tooltip-table, beside data-tooltip, is the same as a small table,
/// {head, rows, none}, shown instead: built with textContent only, since its
/// values are map authors and descriptions people wrote. A null value is a
/// missing one, drawn as `none` in italics.
function mtasaTooltipTable(data) {
    const table = document.createElement("table");
    const head = table.createTHead().insertRow();
    data.head.forEach(text => {
        const cell = document.createElement("th");
        cell.textContent = text;
        head.appendChild(cell);
    });
    const body = table.createTBody();
    data.rows.forEach(values => {
        const row = body.insertRow();
        values.forEach((text, index) => {
            const cell = document.createElement(index === 0 ? "th" : "td");
            if (text === null) {
                cell.className = "mtasaTooltipNone";
                cell.textContent = data.none;
            }
            else {
                cell.textContent = text;
            }
            row.appendChild(cell);
        });
    });
    return table;
}

if (mtasaTooltipBox) {
    document.addEventListener("mouseover", event => {
        const target = event.target.closest("[data-tooltip]");
        /// An empty one is a cell with nothing to say, not an empty box.
        if (!target || !target.dataset.tooltip) {
            return;
        }

        mtasaTooltipBox.replaceChildren();
        mtasaTooltipBox.classList.toggle("mtasaTooltipTable", "tooltipTable" in target.dataset);
        if ("tooltipTable" in target.dataset) {
            mtasaTooltipBox.appendChild(mtasaTooltipTable(JSON.parse(target.dataset.tooltipTable)));
        }
        else {
            mtasaTooltipBox.textContent = target.dataset.tooltip;
        }
        mtasaTooltipBox.style.display = "block";
        moveMtasaTooltip(event);
    });

    document.addEventListener("mouseout", event => {
        if (event.target.closest("[data-tooltip]")) {
            hideMtasaTooltip();
        }
    });

    document.addEventListener("mousemove", event => {
        if (mtasaTooltipBox.style.display === "block" && event.target.closest("[data-tooltip]")) {
            moveMtasaTooltip(event);
        }
    });

    /// Hidden on scroll, since the box is pinned to a spot rather than an element.
    window.addEventListener("scroll", hideMtasaTooltip, true);
}
