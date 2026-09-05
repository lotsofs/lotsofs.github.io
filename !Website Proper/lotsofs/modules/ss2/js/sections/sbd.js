import { scoreInformation } from "../data/score.js";
import { tooltipPopup } from "../ui/tooltip.js";

let cumulativeLevelScore = 0;
let cumulativeLevelKills = 0;
let previousCumulativeLevelKills = 0;

const tbody = document.getElementById("sbd_tbody");

let levelData;
let enemyMultiplier = 1;

const leftButton = document.getElementById("sbd_button_l");
const rightButton = document.getElementById("sbd_button_r");
leftButton.addEventListener("click", () => { 
	sbd.changeMultiplier(-1);
})
rightButton.addEventListener("click", () => { 
	sbd.changeMultiplier(1);
})

export const sbd = {
	populate(ld) {
		levelData = ld;
		build();
	},

	changeMultiplier(change) {
		enemyMultiplier += change;
		enemyMultiplier %= 11;
		if (enemyMultiplier < 1) enemyMultiplier = 11;
		let span = document.getElementById("sbd_multiplierSpan");
		span.textContent = enemyMultiplier == 1 ? "NONE" : enemyMultiplier + "X";
		purge();
		build();
	}
}

function build() {
	levelData.chapters.forEach((chapter, i) => {
		if (Object.keys(chapter).length == 0) return;
		addSection(chapter, i);
	});
	setEndTable();
}

function setEndTable() {
	const table = document.getElementById("sbd_endScreenTable");
	if (enemyMultiplier > 1 && !table.classList.contains("multiplierActive")) {
		table.classList.add("multiplierActive");
	}
	else if (enemyMultiplier == 1 && table.classList.contains("multiplierActive")) {
		table.classList.remove("multiplierActive");
	}

	const killScore = cumulativeLevelKills * 10;
	const secretScore = !levelData.secrets ? 0 : 1000; // 1000/total*collected (floored?)
	const livesCount = Math.floor(cumulativeLevelScore/10000)+3;
	const livesScore = livesCount * 1000;
	const playTime = 0;
	const playTimeText = convertSecondsToTimestamp(playTime);
	const estimatedTimeText = convertSecondsToTimestamp(levelData.eta);
	const timeScore = (levelData.eta - playTime) * 10;
	const totalBonus = killScore + secretScore + livesScore + timeScore;
	const totalScore = totalBonus + cumulativeLevelScore;
	
	table.querySelectorAll("[data-field]").forEach(elem => {
		const key = elem.getAttribute("data-field");
		switch(key) {
			case "baseScore":
				elem.textContent = cumulativeLevelScore;
				break;
			// case "difficultyName":
			// 	elem.textContent = "Serious";
			// 	break;
			// case "difficultyScore":
			// 	elem.textContent = 10000;
			// 	break;
			case "killCount":
				elem.textContent = cumulativeLevelKills;
				break;
			case "killScore":
				elem.textContent = killScore;
				break;
			case "secretCount":
				elem.textContent = `${levelData.secrets}/${levelData.secrets}`;
				break;
			case "secretScore":
				elem.textContent = secretScore
				break;
			case "livesCount":
				elem.textContent = livesCount;
				break;
			case "livesScore":
				elem.textContent = livesScore;
				break;
			case "playtime":
				elem.textContent = playTimeText;
				break;
			case "estimatedTime":
				elem.textContent = estimatedTimeText;
				break;
			case "timeScore":
				elem.textContent = timeScore;
				break;
			case "totalBonus":
				elem.textContent = totalBonus;
				break;
			case "totalScore":
				elem.textContent = totalScore;
				break;
		}
	})
}

function addSection(chapter, i) {
	let row = document.createElement("tr");
	row.classList.add("sbd_firstOfChapter");
	tbody.appendChild(row);
	const td_chapter = appendChildToElement(row, "td", i+1);
	let itemCount = 0;

	for (const [areaName, items] of Object.entries(chapter.points)) {
		const td_area = appendChildToElement(row, "td", areaName);
		td_area.rowSpan = items.length;
		for (const item of items) {
			itemCount++;

			let multiplier = item.countsAsKill ? enemyMultiplier : 1;
			if (multiplier > 1) {
				row.classList.add("multiplierActive");
				if (item.maxMultiplier) {
					multiplier = Math.max(Math.floor(item.maxMultiplier/11 * multiplier), 1);
				}
			}

			let name = item.name;
			if (item.note) {
				name += ` (${item.note.toLowerCase()})`;
			}

			const worth = item.worth ?? scoreInformation[item.name] ?? "????";
			const count = (item.count ?? 1) * multiplier;
			const countsAsKill = item.countsAsKill ?? false;

			const totalWorth = worth * count;

			if (!item.impossible) {
				cumulativeLevelScore += totalWorth;
				cumulativeLevelKills += countsAsKill ? count : 0;
			}
			else if (item.killsItself) {
				cumulativeLevelKills += countsAsKill ? count : 0;
			}

			appendChildToElement(row, "td", count);
			appendChildToElement(row, "td", name);
			const tdCumKills = appendChildToElement(row, "td", cumulativeLevelKills == previousCumulativeLevelKills ? "" : cumulativeLevelKills);
			appendChildToElement(row, "td", worth);
			appendChildToElement(row, "td", totalWorth);
			const tdCumScore = appendChildToElement(row, "td", cumulativeLevelScore);
			if (item.impossible) {
				if (countsAsKill && !item.killsItself) {
					tdCumKills.textContent = "*";
					tooltipPopup.link(tdCumKills, item.impossible);
				}
				tdCumScore.textContent = "*";
				tooltipPopup.link(tdCumScore, item.impossible);
			}
			
			if (cumulativeLevelKills > previousCumulativeLevelKills) {
				previousCumulativeLevelKills = cumulativeLevelKills;
			}
			row = appendChildToElement(tbody, "tr", "");
		}
		row.classList.add("sbd_nextarea");
	}
	td_chapter.rowSpan = itemCount;
}

function purge() {
	tbody.innerHTML = "";
	cumulativeLevelScore = 0;
	cumulativeLevelKills = 0;
	previousCumulativeLevelKills = 0;
}