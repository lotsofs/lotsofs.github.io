"use strict";

/// The hover box: put data-tooltip on anything, including what is added after
/// load, since the listeners are delegated. A copy of music's tooltip.js cut to
/// that one attribute, as music's is of ss2's: the modules ship separate
/// stylesheets and share no scripts.
const gamesTooltipBox = document.getElementById("gamesTooltip");

const GAMES_TOOLTIP_OFFSET_X = 30;
const GAMES_TOOLTIP_OFFSET_Y = 10;
const GAMES_TOOLTIP_EDGE = 8;

function hideGamesTooltip() {
    gamesTooltipBox.style.display = "none";
    gamesTooltipBox.style.left = "0";
    gamesTooltipBox.style.top = "0";
}

/// Follows the cursor, clamped to the window edges.
function moveGamesTooltip(event) {
    const box = gamesTooltipBox.getBoundingClientRect();

    let x = event.clientX + GAMES_TOOLTIP_OFFSET_X;
    let y = event.clientY + GAMES_TOOLTIP_OFFSET_Y;

    if (x + box.width > window.innerWidth - GAMES_TOOLTIP_EDGE) {
        x = event.clientX - box.width - GAMES_TOOLTIP_OFFSET_Y;
    }
    if (y + box.height > window.innerHeight - GAMES_TOOLTIP_EDGE) {
        y = window.innerHeight - box.height - GAMES_TOOLTIP_EDGE;
    }

    gamesTooltipBox.style.left = Math.max(GAMES_TOOLTIP_EDGE, x) + window.scrollX + "px";
    gamesTooltipBox.style.top = Math.max(GAMES_TOOLTIP_EDGE, y) + window.scrollY + "px";
}

if (gamesTooltipBox) {
    document.addEventListener("mouseover", event => {
        const target = event.target.closest("[data-tooltip]");
        /// An empty one is a cell with nothing to say, not an empty box.
        if (!target || !target.dataset.tooltip) {
            return;
        }

        gamesTooltipBox.textContent = target.dataset.tooltip;
        gamesTooltipBox.style.display = "block";
        moveGamesTooltip(event);
    });

    document.addEventListener("mouseout", event => {
        if (event.target.closest("[data-tooltip]")) {
            hideGamesTooltip();
        }
    });

    document.addEventListener("mousemove", event => {
        if (gamesTooltipBox.style.display === "block" && event.target.closest("[data-tooltip]")) {
            moveGamesTooltip(event);
        }
    });

    /// Hidden on scroll, since the box is pinned to a spot rather than an element.
    window.addEventListener("scroll", hideGamesTooltip, true);
}
