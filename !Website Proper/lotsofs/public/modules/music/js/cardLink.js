// The query parameters naming an open card, so one can be linked to.
const CARD_LINK_PARAMS = ["songCard", "albumCard", "artistCard"];

// Both render into the one modal, so at most one is in the address at a time.
const CARD_LINK_MODAL_PARAMS = ["albumCard", "artistCard"];

function writeCardLink(owned, param, cardId) {
	const url = new URL(location.href);

	owned.forEach(name => url.searchParams.delete(name));
	if (param) {
		url.searchParams.set(param, cardId);
	}

	history.replaceState(null, "", url.pathname + url.search + url.hash);
}

/// The id in one card parameter, or null if it is not a plain id.
function cardLinkValue(param) {
	const raw = new URLSearchParams(location.search).get(param);
	return raw !== null && /^[0-9]+$/.test(raw) ? raw : null;
}
