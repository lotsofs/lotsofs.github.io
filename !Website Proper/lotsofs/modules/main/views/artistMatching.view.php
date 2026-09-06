<?php require(__MAIN__ . '/views/partials/head.php') ?>

<?php require(__MAIN__ . '/views/partials/nav.php') ?>

<h1>
	Artist Matching
</h1>
<textarea id="jsonInput" placeholder='Example:
[
  {"Artist":"Dream Theater","Title":"Bridges in the Sky"},
  {"Artist":"Paramore","Title":"The Only Exception"}
]'>
</textarea>
<p id="jsonFormat_Message"></p>
<table id="jsonFormat_ArtistMatchTable">
	<thead>
		<tr>
			<th class="providedNameCell">Provided Artist Name</th>
			<th class="artistSelectCell">Found Artist Name</th>
			<th class="extrasCell">Name To Store</th>
			<th class="resultCell">Result</th>
		</tr>
	</thead>
	<tbody id="jsonFormat_ArtistTable">

	</tbody>
</table>
<button id="jsonFormat_SubmitButton">Submit Artists</button>

<script id="artistNamesData" type="application/json"><?= json_encode($globalData['artistNames']) ?></script>
<script src="modules/main/js/artistMatching.js"></script>

<?php require(__MAIN__ . '/views/partials/foot.php') ?>
