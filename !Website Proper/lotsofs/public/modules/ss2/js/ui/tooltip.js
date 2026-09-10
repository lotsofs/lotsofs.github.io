const tooltip = document.getElementById("popupToolTip");

export const tooltipPopup = {
	link(element, text) {
		element.addEventListener("mouseenter", e => {
			// element.setAttribute("fill", "#ffffff");
			show(text);
		});
		element.addEventListener("mouseleave", e => {
			// element.setAttribute("fill", m.color);
			hide();
		});
		element.addEventListener("mousemove", move);
	}
}

function show(text) {
	tooltip.textContent = text;
	tooltip.style.display = "block";
}

function move(e) {
	tooltip.style.left = `${e.clientX + window.scrollX + 30}px`;
	tooltip.style.top = `${e.clientY + window.scrollY + 10}px`;
}

function hide() {
	tooltip.style.display = "none";
	tooltip.style.left = "0";
	tooltip.style.top = "0";
}