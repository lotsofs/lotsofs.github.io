const musicTooltipBox = document.getElementById("musicTooltip");

const MUSIC_TOOLTIP_OFFSET_X = 30;
const MUSIC_TOOLTIP_OFFSET_Y = 10;
const MUSIC_TOOLTIP_EDGE = 8;

// Delegated rather than bound per element, because most of what wants a
// tooltip here arrives after the page does - the album card is fetched and
// dropped into the modal, so anything bound at load time would miss it. Put
// data-tooltip on an element, server-side or otherwise, and it gets one.
// musicTooltip.link() is there for the imperative case.
function showMusicTooltip(text) {
	musicTooltipBox.textContent = text;
	musicTooltipBox.style.display = "block";
}

function hideMusicTooltip() {
	musicTooltipBox.style.display = "none";
	musicTooltipBox.style.left = "0";
	musicTooltipBox.style.top = "0";
}

/// Follows the cursor, but stops at the edges: the album card opens near the
/// right of the window often enough that a box hanging off it would be the
/// normal case rather than the odd one.
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

/// Two ways in. data-tooltip declares one outright; and inside a region marked
/// data-tooltip-titles, a plain title attribute counts as one too. That second
/// route is what upgrades the song table without touching its markup: those
/// cells are clipped with an ellipsis and have carried their full value in a
/// title since long before this box existed.
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

	/// Moves a native tooltip - an svg <title> child or a title attribute -
	/// into data-tooltip, rather than copying it. Left in place, the browser
	/// would raise its own tooltip a second later underneath ours. It is a
	/// one-way move: from here on the element's tooltip text is the dataset
	/// entry, which is why setCellValue in songs.js writes whichever of the
	/// two a cell currently has.
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

		// An empty title - a song with no album, a rater with no note - is a
		// cell with nothing to say, not a cell that wants an empty box.
		if (!target.dataset.tooltip) {
			return;
		}

		showMusicTooltip(target.dataset.tooltip);
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

	// A tooltip pinned to a spot that has scrolled away, or to a card that has
	// been closed, is worse than none.
	window.addEventListener("scroll", hideMusicTooltip, true);
}
