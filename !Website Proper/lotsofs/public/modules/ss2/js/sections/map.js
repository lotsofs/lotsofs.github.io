import { tooltipPopup } from "../ui/tooltip.js";

export const map = {
	populate(levelData) {
		const imageHref = `/modules/ss2/img/maps/${levelData.id}.png`;
		const width = levelData.map.width;
		const height = levelData.map.height;

		const markers = levelData.map.markers;

		const svg = createSVGElement("svg");
		svg.setAttribute("width", width);
		svg.setAttribute("height", height);
		svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
		svg.setAttribute("xmlns", SVG_NS);
		
		const image = createSVGElement("image");
		image.setAttribute("href", imageHref);
		image.setAttribute("x", 0);
		image.setAttribute("y", 0);
		image.setAttribute("width", width);
		image.setAttribute("height", height);
		svg.appendChild(image);

		const g = createSVGElement("g");
		g.setAttribute("id", "markers");
		markers.forEach(m => {
			const circle = createSVGElement("circle");
			circle.setAttribute("cx", m.x);
			circle.setAttribute("cy", m.y);
			circle.setAttribute("r", m.r);
			circle.setAttribute("fill", m.color);
			circle.setAttribute("stroke", "#000000");

			tooltipPopup.link(circle, m.tooltip);

			circle.addEventListener("mouseenter", e => {
				circle.setAttribute("fill", "#ffffff");
			});
			circle.addEventListener("mouseleave", e => {
				circle.setAttribute("fill", m.color);
			});

			g.appendChild(circle);
		})
		svg.appendChild(g);

		document.getElementById("mapSvgContainer").appendChild(svg);
	}
}