export const ess = {
	populate(levelData) {
		const div = document.getElementById("essdiv");
		levelData.imagery.forEach((img, i) => {
			appendChildToElement(div, "h4", img.title);
			appendChildToElement(div, "p", img.description);
			const imgElement = appendChildToElement(div, "img", "");
			imgElement.src = `/modules/ss2/img/levels/${levelData.id}/${img.fileName}`;
		})
	}
}