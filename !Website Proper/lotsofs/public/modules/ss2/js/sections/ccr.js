export const ccr = {
	populate(levelData) {
		levelData.chapters.forEach((chapter, i) => {
			if (Object.keys(chapter).length === 0) return;
			addRow(chapter, i);
		})
	}
}

function addRow(chapter, i) {
	const tbody = document.getElementById("ccr_tbody");
	const row = appendChildToElement(tbody, "tr", "");
	
	appendChildToElement(row, "td", i+1);
	appendChildToElement(row, "td", chapter.name || "No description");
	appendChildToElement(row, "td", chapter.passCondition ?? "?");
	appendChildToElement(row, "td", chapter.securityTimer ?? "?");
	
	let requiresStart = "N/A";
	if (i > 0) {
		requiresStart = chapter.requiresStart ? "Yes" : "No";
	}
	const cell = appendChildToElement(row, "td", requiresStart);
	if (chapter.requiresStart === false) {
		cell.classList.add("bold");
	}
}