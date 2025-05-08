<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $pageTitle ?> - LotsOfS</title>
	<!-- <link rel="stylesheet" href="/css/styles.css"> -->
</head>
<body>

<style>
	body {
		background: black;
		color: white;
	}
	#mapContainer {
		/* border: solid 1px magenta; */
		width: 50%;
		margin: 0;
		padding: 0;
		float: left;
	}
	#mapContainer svg {
		width: 100%;
		height: auto;
		margin: 0;
	}
</style>

<div>
	<h1>Fairfax Residence</h1>
</div>

<div id="mapContainer"></div>

<div id="mapControls">
	<h3>Floors</h3>
	<input type="checkbox" onclick="hideLayer(this, 'floor1')" checked>Floor 1</input>
	<input type="checkbox" onclick="hideLayer(this, 'floor0')" checked>Floor 0</input>
</div>

<script>
	let mapContainer = document.getElementById('mapContainer');

	fetch('/swat4/svg/02Fairfax_Map.svg')
		.then(response => response.text())
		.then(svgText => {
			mapContainer.innerHTML = svgText;
			let svgDoc = mapContainer.querySelector('svg');
			let elementToHide = svgDoc.getElementById("f1Patrols");
			// elementToHide.style.display = 'none';
		});

	function hideLayer(button, name) {
		
		layer = document.getElementById(name);
		layer.style.visibility = button.checked ? "visible" : "hidden";
	}

</script>

<?php require('views/partials/foot.php') ?>
