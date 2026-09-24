const navColour = document.getElementById("navColour");
const navColourInput = document.getElementById("navColourInput");
const navColourValue = document.getElementById("navColourValue");

// The picker works without this: the form posts the hue and the page comes back
// rendered in it. What this adds is seeing the change while dragging, and
// putting the page back the way it was if the menu is closed without saving -
// otherwise a look around the dial would stick until the next reload.
if (navColour && navColourInput) {
	const savedHue = document.documentElement.style.getPropertyValue("--hue").trim();

	function showHue(hue) {
		document.documentElement.style.setProperty("--hue", hue);
		if (navColourValue) {
			navColourValue.textContent = hue;
		}
	}

	navColourInput.addEventListener("input", () => showHue(navColourInput.value));

	navColour.addEventListener("toggle", () => {
		if (!navColour.open) {
			navColourInput.value = savedHue;
			showHue(savedHue);
		}
	});
}
