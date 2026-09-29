const hnsTooltipBox = document.getElementById("hnsTooltip");

const HNS_TOOLTIP_OFFSET_X = 30;
const HNS_TOOLTIP_OFFSET_Y = 10;
const HNS_TOOLTIP_EDGE = 8;

// Delegated, so it covers elements injected after load. Put data-tooltip on anything.
function showHnsTooltip(text) {
	hnsTooltipBox.textContent = text;
	hnsTooltipBox.style.display = "block";
}

function hideHnsTooltip() {
	hnsTooltipBox.style.display = "none";
	hnsTooltipBox.style.left = "0";
	hnsTooltipBox.style.top = "0";
}

/// Follows the cursor, clamped to the window edges.
function moveHnsTooltip(event) {
	const box = hnsTooltipBox.getBoundingClientRect();

	let x = event.clientX + HNS_TOOLTIP_OFFSET_X;
	let y = event.clientY + HNS_TOOLTIP_OFFSET_Y;

	if (x + box.width > window.innerWidth - HNS_TOOLTIP_EDGE) {
		x = event.clientX - box.width - HNS_TOOLTIP_OFFSET_Y;
	}
	if (y + box.height > window.innerHeight - HNS_TOOLTIP_EDGE) {
		y = window.innerHeight - box.height - HNS_TOOLTIP_EDGE;
	}

	hnsTooltipBox.style.left = Math.max(HNS_TOOLTIP_EDGE, x) + window.scrollX + "px";
	hnsTooltipBox.style.top = Math.max(HNS_TOOLTIP_EDGE, y) + window.scrollY + "px";
}

/// data-tooltip declares one; inside data-tooltip-titles a plain title counts too.
function hnsTooltipTarget(element) {
	const declared = element.closest("[data-tooltip]");
	if (declared) {
		return declared;
	}

	const titled = element.closest("[title]");

	return titled && titled.closest("[data-tooltip-titles]") ? titled : null;
}

const hnsTooltip = {
	link(element, text) {
		element.dataset.tooltip = text;
		hnsTooltip.adopt(element);
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

if (hnsTooltipBox) {
	document.addEventListener("mouseover", event => {
		const target = hnsTooltipTarget(event.target);
		if (!target) {
			return;
		}

		hnsTooltip.adopt(target);

		// An empty title is a cell with nothing to say, not an empty box.
		if (!target.dataset.tooltip) {
			return;
		}

		showHnsTooltip(target.dataset.tooltip);
		moveHnsTooltip(event);
	});

	document.addEventListener("mouseout", event => {
		if (event.target.closest("[data-tooltip]")) {
			hideHnsTooltip();
		}
	});

	document.addEventListener("mousemove", event => {
		if (hnsTooltipBox.style.display === "block" && event.target.closest("[data-tooltip]")) {
			moveHnsTooltip(event);
		}
	});

	// Hidden on scroll, since the box is pinned to a spot rather than an element.
	window.addEventListener("scroll", hideHnsTooltip, true);
}
