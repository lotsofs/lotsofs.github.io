const musicTooltipBox = document.getElementById("musicTooltip");

const MUSIC_TOOLTIP_OFFSET_X = 30;
const MUSIC_TOOLTIP_OFFSET_Y = 10;
const MUSIC_TOOLTIP_EDGE = 8;

// Delegated, so it covers elements injected after load. Put data-tooltip on anything.
function showMusicTooltip(text) {
	musicTooltipBox.textContent = text;
	musicTooltipBox.style.display = "block";
}

/* data-tooltip-rich: the same text as [[piece, bold], ...], for a tooltip with
   bold parts. Built with textContent, never markup, since a piece can be a song
   title or a note somebody typed. data-tooltip still holds the plain text. */
function showMusicTooltipRich(pieces) {
	musicTooltipBox.textContent = "";
	pieces.forEach(([text, bold]) => {
		if (bold) {
			const strong = document.createElement("strong");
			strong.textContent = text;
			musicTooltipBox.appendChild(strong);
		}
		else {
			musicTooltipBox.appendChild(document.createTextNode(text));
		}
	});
	musicTooltipBox.style.display = "block";
}

function hideMusicTooltip() {
	musicTooltipBox.style.display = "none";
	musicTooltipBox.style.left = "0";
	musicTooltipBox.style.top = "0";
}

/// Follows the cursor, clamped to the window edges.
function moveMusicTooltip(event) {
	const box = musicTooltipBox.getBoundingClientRect();

	let x = event.clientX + MUSIC_TOOLTIP_OFFSET_X;
	let y = event.clientY + MUSIC_TOOLTIP_OFFSET_Y;

	if (x + box.width > window.innerWidth - MUSIC_TOOLTIP_EDGE) {
		x = event.clientX - box.width - MUSIC_TOOLTIP_OFFSET_Y;
	}
	if (y + box.height > window.innerHeight - MUSIC_TOOLTIP_EDGE) {
		y = window.innerHeight - box.height - MUSIC_TOOLTIP_EDGE;
	}

	musicTooltipBox.style.left = Math.max(MUSIC_TOOLTIP_EDGE, x) + window.scrollX + "px";
	musicTooltipBox.style.top = Math.max(MUSIC_TOOLTIP_EDGE, y) + window.scrollY + "px";
}

/// data-tooltip declares one; inside data-tooltip-titles a plain title counts too.
function musicTooltipTarget(element) {
	const declared = element.closest("[data-tooltip]");
	if (declared) {
		return declared;
	}

	const titled = element.closest("[title]");

	return titled && titled.closest("[data-tooltip-titles]") ? titled : null;
}

const musicTooltip = {
	link(element, text) {
		element.dataset.tooltip = text;
		musicTooltip.adopt(element);
	},

	/// Moves a native title or svg <title> into data-tooltip. A one-way move.
	adopt(element) {
		const child = element.querySelector(":scope > title");
		if (child) {
			if (!("tooltip" in element.dataset)) {
				element.dataset.tooltip = child.textContent;
			}
			child.remove();
		}

		if (element.hasAttribute("title")) {
			if (!("tooltip" in element.dataset)) {
				element.dataset.tooltip = element.getAttribute("title");
			}
			element.removeAttribute("title");
		}
	},
};

if (musicTooltipBox) {
	document.addEventListener("mouseover", event => {
		const target = musicTooltipTarget(event.target);
		if (!target) {
			return;
		}

		musicTooltip.adopt(target);

		// An empty title is a cell with nothing to say, not an empty box.
		if (!target.dataset.tooltip) {
			return;
		}

		if ("tooltipRich" in target.dataset) {
			showMusicTooltipRich(JSON.parse(target.dataset.tooltipRich));
		}
		else {
			showMusicTooltip(target.dataset.tooltip);
		}
		moveMusicTooltip(event);
	});

	document.addEventListener("mouseout", event => {
		if (event.target.closest("[data-tooltip]")) {
			hideMusicTooltip();
		}
	});

	document.addEventListener("mousemove", event => {
		if (musicTooltipBox.style.display === "block" && event.target.closest("[data-tooltip]")) {
			moveMusicTooltip(event);
		}
	});

	// Hidden on scroll, since the box is pinned to a spot rather than an element.
	window.addEventListener("scroll", hideMusicTooltip, true);
}
