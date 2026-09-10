let levelData = null;

export function getLevelData() {
	return levelData;
}


export async function initLevel() {
	const levelElement = document.getElementById('levelInfo');
	const levelId = levelElement.dataset.id;
	try {
		const jsonData = await readJsonFileAsync(`/modules/ss2/json/${levelId}.json`);
		levelData = jsonData;
	}
	catch (err) {
		console.error(err);
	}
}