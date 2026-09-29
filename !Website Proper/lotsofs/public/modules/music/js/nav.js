const navBar = document.querySelector("nav");

// Measured rather than hardcoded, since the nav wraps. Not offsetHeight, which rounds.
function navPublishHeight() {
	document.documentElement.style.setProperty("--navHeight", navBar.getBoundingClientRect().height + "px");
}

navPublishHeight();

new ResizeObserver(navPublishHeight).observe(navBar);
