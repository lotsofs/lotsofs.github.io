import { ammoInformation } from "../data/ammo.js";

export const waa = {
	populate(levelData) {
		
		setRatioRow(levelData);
		const tbody = document.getElementById("waa_tbody");
	
		for (const key in ammoInformation) {
			const ammo = ammoInformation[key];
			const tr = appendChildToElement(tbody, "tr", "");
	
			appendChildToElement(tr, "th", ammo.name);
	
			levelData.chapters.forEach((chapter, i) => {
				if (Object.keys(chapter).length === 0) return;
	
	
				const cell = document.createElement("td");
				let ammoCount = chapter.customAmmo[key];
	
				if (ammoCount === undefined) {
					ammoCount = Math.floor(chapter.ammoRatio * ammo.max);
					cell.classList.add("italic");
				}
				else if (ammoCount < 0) {
					ammoCount = "-";
				}
				else {
					cell.classList.add("bold");
				}
				cell.textContent = ammoCount;
				tr.appendChild(cell);
			});
		}
	}
}

function setRatioRow(levelData) {
	levelData.chapters.forEach((chapter, i) => {
		if (Object.keys(chapter).length === 0) return;

		const tr_head = document.getElementById("waa_tr_head");
		const tr_ratio = document.getElementById("waa_tr_ratio");
		appendChildToElement(tr_head, "th", i+1);
		appendChildToElement(tr_ratio, "td", chapter.ammoRatio);
	});
}