import { tooltipPopup } from "../ui/tooltip.js";

const PIN_WIDTH = 150;
const TIMELINE_SHRINK_FACTOR = 13;

const div = document.getElementById("satdiv");
let usingV090 = false;

let levelData;
let scrollPositions = [];

const toggle = document.getElementById("sat_checkbox");
toggle.addEventListener("change", e => { 
	sat.toggleVersion(e);
})

export const sat = {
	toggleVersion(e) {
		usingV090 = e.target.checked;
		purge();
		build();
	},

	populate(ld) {
		levelData = ld;
		build();
	}
}

function build() {
	levelData.spawnerGroups.forEach((spawnerGroup, _) => {
		appendChildToElement(div, "h4", spawnerGroup.groupName);

		let svgWidth = 0;
		let svgHeight = 0;

		const svg = createSVGElement("svg");
		const g = createSVGElement("g");
		g.setAttribute("id", "markers");
		svg.appendChild(g);

		const spawners = spawnerGroup.spawners;

		spawners.forEach((spawner, spawnerIdx) => {
			let spawnees = [];
			let elapsedTime = 0;
			for (let i = 0; i < spawner.totalNumber; i++) {
				let spawnee = generateSpawnee(spawner, elapsedTime, i);
				elapsedTime = spawnee.effect;
				spawnees.push(spawnee);

				const pin = generateTimelinePin(spawnee, spawner, spawnerIdx);
				g.appendChild(pin);
			}
			div.appendChild(generateTable(spawner, spawnees));
			svgWidth = Math.max(svgWidth, elapsedTime*1000 + PIN_WIDTH);
			svgHeight = spawnerIdx * 100 + 100;
		});
		appendChildToElement(div, "p", spawnerGroup.note);

		svg.setAttribute("width", svgWidth / TIMELINE_SHRINK_FACTOR);
		svg.setAttribute("height", "100%");
		svg.setAttribute("preserveAspectRatio", "none");
		svg.setAttribute("viewBox", `0 0 ${svgWidth} ${svgHeight}`);
		svg.setAttribute("xmlns", SVG_NS);

		generateTimelineBackdrop(svg, svgWidth, svgHeight);

		const svgContainerDiv = document.createElement("div");
		svgContainerDiv.classList.add("svgContainer");
		if (usingV090) {
			svgContainerDiv.classList.add("v090");
		}
		svgContainerDiv.appendChild(svg);
		svgContainerDiv.addEventListener("scroll", () => {
			scrollPositions[spawnerGroup.groupName] = svgContainerDiv.scrollLeft;
		})
		div.appendChild(svgContainerDiv);
		if (scrollPositions[spawnerGroup.groupName] !== undefined) {
			svgContainerDiv.scrollLeft = scrollPositions[spawnerGroup.groupName];
		}
	})
}
	
function purge() {
	div.innerHTML = "";
}

function generateSpawnee(s, elapsedTime, i) {
	let spawnee = {
		effect: 0,
		egg: 0,
		puppet: 0,
		death: 0
	};
	if (i == 0) {
		elapsedTime += s.initialDelay + s.initialDelayByScript + s.otherInitialDelay;
	}
	else if (usingV090 && s.spawnType == "simple") {
		elapsedTime += s.singleDelay;
	}
	else if (usingV090 && s.spawnType == "maintainGroup") {
		if (i < s.numberInGroup) {
			elapsedTime += s.groupDelay;
		}
		else {
			elapsedTime += s.singleDelay;
		}
	}
	else if (i % s.numberInGroup == 0 && s.spawnType == "simple") {
		elapsedTime += Math.max(s.singleDelay, s.groupDelay);
	}
	else {
		elapsedTime += s.singleDelay;
	}
	// TODO:
	// Check the spawnEffectDuration + spawnLaunchDuration + spawneeDeathDelay + timeToKill for the numberInGroup'th enemy spawned before this one.
	// If it is greater than theoreticalSpawnTime, this enemy won't spawn just yet... (in case of maintaingroup)
	// Spawnee death delay only comes into play if an entire group is able to spawn before a kill occurs, and is cumulative with single delay.
	// Group delay does nothing on spawnmaintaingroup, SDD does nothing for simple spawn.
	spawnee.effect = elapsedTime;
	spawnee.egg = spawnee.effect + (s.spawnEffectDelay??0);
	spawnee.puppet = spawnee.egg + (s.spawnLaunchDuration??0);
	spawnee.death = spawnee.puppet + (s.timeToKill??0);
	return spawnee;
}

function generateTable(spawner, spawnees) {
	const template = document.getElementById("satTemplateTable");
	const clone = template.cloneNode(true);
	clone.removeAttribute("id");

	clone.querySelectorAll("[data-field]").forEach(elem => {
		const key = elem.getAttribute("data-field");
		switch(key) {
			case "unitType":
				elem.textContent = spawner.name;
				break;
			case "spawnEffectConfiguration":
				const appendSEC = spawner.spawnEffectType ? ` (${spawner.spawnEffectType})` : " (none)"
				elem.textContent = `${spawner.spawnEffectDelay??0} s ${appendSEC}`;
				break;
			case "launcher":
				const appendL = spawner.spawnLaunchType ? ` (${spawner.spawnLaunchType})` : " (none)"
				elem.textContent = `${spawner.spawnEffectDelay??0} s ${appendL}`;
				break;
			case "spawnFormation":
				 elem.textContent = spawner.spawnFormation ? spawner.spawnFormation : "(none)";
				 break;
			case "initialDelay":
			case "initialDelayByScript":
			case "singleDelay":
			case "groupDelay":
			case "spawneeDeathDelay":
			case "otherInitialDelay":
				elem.textContent = `${spawner[key]??0} s`;
				break;
			case "totalSpawntime": 
				elem.textContent = `${spawnees[spawnees.length-1].puppet} s`;
				if (usingV090) {
					elem.classList.add("v090");
				}
				break;
			default:
				elem.textContent = spawner[key] ?? "";
				break;
		}
	});
	return clone;
}

function generateTimelinePin(spawnee, s, spawnerIdx) {
	let tooltipText = `${spawnee.puppet}s: ${s.name}`;

	const pin = createSVGElement("rect");
	pin.setAttribute("x", spawnee.puppet * 1000);
	pin.setAttribute("width", PIN_WIDTH);
	pin.setAttribute("y", spawnerIdx * 100);
	pin.setAttribute("height", 100);
	pin.setAttribute("fill", s.color);
	tooltipPopup.link(pin, tooltipText);
	pin.addEventListener("mouseenter", e => {
		pin.setAttribute("fill", "#ffffff");
	});
	pin.addEventListener("mouseleave", e => {
		pin.setAttribute("fill", s.color);
	});
	return pin;
}

function generateTimelineBackdrop(svg, svgWidth, svgHeight) {
	for (let i = PIN_WIDTH * 0.5; i < svgWidth; i+=1000) {
		const timeline = createSVGElement("rect");
		timeline.setAttribute("x", i);
		if (i + 1000 > svgWidth) {
			timeline.setAttribute("width", svgWidth % 1000 - PIN_WIDTH);	
		}
		else {
			timeline.setAttribute("width", 1000); 
		}
		timeline.setAttribute("y", 0);
		timeline.setAttribute("height", svgHeight);
		timeline.setAttribute("fill", i % 2000 < 1000 ? "#067095" : "#023951");
		svg.insertBefore(timeline, svg.firstChild);
	}
}