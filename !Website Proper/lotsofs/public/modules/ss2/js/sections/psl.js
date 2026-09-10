export const psl = {
	populate(levelData) {
		const div = document.getElementById("psl_div");
		levelData.objectives.forEach((o, i) => {
			appendChildToElement(div, "p", o);
		})
		setDescription(levelData);
	}
}

async function setDescription(levelData) {
	const levelId = levelData.id;
	try {
		let levelDsc = await readTextFileAsync(`/modules/ss2/text/${levelId}.dsc`);
		setElementByIdTextContent("psl_p",levelDsc)
	}
	catch (err) {
		setElementByIdTextContent("psl_p","");
		console.error("Level Description file not found", "levelId", err);
	}
}