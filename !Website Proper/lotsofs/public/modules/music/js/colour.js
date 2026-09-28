const navColour = document.getElementById("navColour");
const navColourInput = document.getElementById("navColourInput");
const navColourValue = document.getElementById("navColourValue");

// Live preview while dragging, reverted if the menu closes without saving.
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
