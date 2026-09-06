const LANG_STRINGS_ELEMENT = document.getElementById("langStrings");
const LANG_STRINGS = LANG_STRINGS_ELEMENT ? JSON.parse(LANG_STRINGS_ELEMENT.textContent) : {};

function t(key, params = {}) {
    let text = LANG_STRINGS[key] ?? key;
    Object.entries(params).forEach(([name, value]) => {
        text = text.split("{" + name + "}").join(value);
    });
    return text;
}

const HTML_ESCAPE_MAP = {
    '&': "&amp;",
    '<': "&lt;",
    '>': "&gt;",
    '\'': "&quot;",
    '"': "&apos;",
};

const SVG_NS = "http://www.w3.org/2000/svg";

function escapeHtml(text) {
    return text.replace(/[&<>"']/g, char => HTML_ESCAPE_MAP[char]);
}

async function readJsonFileAsync(path) {
    const response = await fetchFileAsync(path);
    return await response.json();
}

async function readTextFileAsync(path) {
    const response = await fetchFileAsync(path);
    return await response.text();
}

async function fetchFileAsync(path) {
    const response = await fetch(path);
    if (!response.ok) {
        throw new Error(`Error loading file: ${path}, status: ${response.status}`);
    }
    return response;
}

// ---------------- --
// DOM manipulation --
// ---------------- --

function appendChildToElement(parent, childTag, childTextContent = "") {
    const element = document.createElement(childTag)
	element.textContent = childTextContent
	parent.appendChild(element)
	return element
}

function setElementByIdTextContent(id, text) {
	const element = document.getElementById(id);
	element.textContent = text;
}

function createSVGElement(tag) {
	return document.createElementNS(SVG_NS, tag);
}

// --------------- --
// Time formatting --
// --------------- --

function convertSecondsToTimestamp(seconds) {
	const hrs = Math.floor(seconds / 3600);
	const mins = Math.floor((seconds % 3600) / 60);
	const secs = seconds % 60;
	return [
		hrs.toString().padStart(2, '0'),
		mins.toString().padStart(2, '0'),
		secs.toString().padStart(2, '0')
	].join(':');
}