import { initLevel, getLevelData } from "./levelLoader.js";
import { psl } from "./sections/psl.js";
import { waa } from "./sections/waa.js";
import { ccr } from "./sections/ccr.js";
import { sat } from "./sections/sat.js";
import { ess } from "./sections/ess.js";
import { sbd } from "./sections/sbd.js";
import { map } from "./sections/map.js";
import { mps } from "./sections/mps.js";

let levelData;

async function run() {
	await initLevel();
	levelData = getLevelData();

	setElementByIdTextContent("mapName", levelData.name);

	const sections = [psl, waa, ccr, sat, ess, sbd, map, mps];
	sections.forEach(section => {
		try {
			section.populate(levelData);
		}
		catch (err) {
			console.error(`Failed to populate section`, section, err);
		}
	})
}

run();