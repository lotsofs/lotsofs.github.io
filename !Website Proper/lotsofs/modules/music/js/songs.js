const songListTable = document.getElementById("songListTable");
const songListBody = songListTable.querySelector("tbody");
const songListHeaders = Array.from(songListTable.querySelectorAll("th[data-sort-key]"));

let songSort = new URLSearchParams(location.search).get("sort") || "id";
let songDir = new URLSearchParams(location.search).get("dir") === "desc" ? "desc" : "asc";

// mirrors the sql: empty sorts first ascending, numbers numerically, text case insensitively
function compareCells(a, b, type) {
	if (a === "" || b === "") {
		return a === b ? 0 : (a === "" ? -1 : 1);
	}
	if (type === "number") {
		return Number(a) - Number(b);
	}
	const x = a.toLowerCase();
	const y = b.toLowerCase();
	return x < y ? -1 : x > y ? 1 : 0;
}

function cellText(row, index) {
	return row.cells[index].textContent.trim();
}

// the sql breaks ties on id ascending whichever way the column is sorted
function sortRows(index, type) {
	const rows = Array.from(songListBody.rows);
	const flip = songDir === "desc" ? -1 : 1;

	rows.sort((rowA, rowB) => {
		const result = compareCells(cellText(rowA, index), cellText(rowB, index), type) * flip;
		return result !== 0 ? result : Number(cellText(rowA, 0)) - Number(cellText(rowB, 0));
	});

	const fragment = document.createDocumentFragment();
	rows.forEach(row => fragment.appendChild(row));
	songListBody.appendChild(fragment);
}

function refreshHeaders() {
	songListHeaders.forEach(header => {
		const link = header.querySelector("a");
		const key = header.dataset.sortKey;
		const isActive = key === songSort;
		const nextDir = isActive && songDir === "asc" ? "desc" : "asc";

		link.textContent = link.dataset.baseLabel + (isActive ? (songDir === "asc" ? " ▲" : " ▼") : "");
		link.href = "?sort=" + key + "&dir=" + nextDir;
		link.title = nextDir === "asc" ? t("songs.sortAscending") : t("songs.sortDescending");
	});
}

songListHeaders.forEach((header, index) => {
	const link = header.querySelector("a");
	link.dataset.baseLabel = link.textContent.replace(/[\s▲▼]+$/, "");

	link.addEventListener("click", event => {
		event.preventDefault();

		const key = header.dataset.sortKey;
		songDir = key === songSort && songDir === "asc" ? "desc" : "asc";
		songSort = key;

		sortRows(index, header.dataset.sortType);
		refreshHeaders();
		history.replaceState(null, "", "?sort=" + songSort + "&dir=" + songDir);
	});
});

const EDITABLE_FIELDS = {
	songTitleCell: "title",
	songNoteCell: "note",
};

function setResult(cell, message) {
	cell.parentElement.querySelector(".songResultCell").textContent = message;

	const anyShown = Array.from(songListBody.querySelectorAll(".songResultCell")).some(resultCell => resultCell.textContent !== "");
	songListTable.classList.toggle("hideResultColumn", !anyShown);
}

function beginCellEdit(cell, field) {
	const original = cell.textContent;
	const input = document.createElement("input");
	input.type = "text";
	input.value = original;

	setResult(cell, "");
	cell.textContent = "";
	cell.appendChild(input);
	input.focus();
	input.select();

	let settled = false;

	function finish(commit) {
		if (settled) {
			return;
		}
		settled = true;

		const value = input.value.trim();
		const keep = commit && (value !== "" || field !== "title");

		cell.textContent = keep ? value : original;

		if (keep && value !== original) {
			saveCell(cell, field, original, value);
		}
	}

	input.addEventListener("keydown", event => {
		if (event.key === "Enter") {
			finish(true);
		}
		if (event.key === "Escape") {
			finish(false);
		}
	});
	input.addEventListener("blur", () => finish(true));
}

function saveCell(cell, field, original, value) {
	const id = cell.parentElement.cells[0].textContent.trim();

	fetch("/modules/music/ajax/songEdit.php", {
		method: "POST",
		headers: {
			"Content-Type": "application/json",
			"X-CSRF-Token": CSRF_TOKEN
		},
		body: JSON.stringify({ id: id, field: field, value: value })
	})
	.then(async response => {
		const body = await response.json().catch(() => null);
		if (!response.ok) {
			throw new Error(body && body.error ? body.error : `HTTP ${response.status}`);
		}
		return body;
	})
	.then(result => {
		cell.textContent = result.value;
		setResult(cell, result.status === "ok" ? "" : result.message);
	})
	.catch(error => {
		cell.textContent = original;
		setResult(cell, t("status.submitFailed", { error: error.message }));
	});
}

function editableField(cell) {
	for (const [className, field] of Object.entries(EDITABLE_FIELDS)) {
		if (cell.classList.contains(className)) {
			return field;
		}
	}
	return null;
}

if (songListTable.dataset.canEdit) {
	songListBody.addEventListener("dblclick", event => {
		const cell = event.target.closest("td");
		if (!cell || cell.querySelector("input")) {
			return;
		}

		const field = editableField(cell);
		if (field) {
			beginCellEdit(cell, field);
		}
	});
}
