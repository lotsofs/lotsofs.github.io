<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<h1>
	<?= t('addSongs.heading') ?>
</h1>
<textarea id="pasteInput" placeholder="<?= htmlspecialchars(t('addSongs.pastePlaceholder')) ?>">
</textarea>
<p id="statusMessage"></p>
<table id="artistMatchTable">
	<thead>
		<tr>
			<th class="providedNameCell"><?= t('artists.column.providedName') ?></th>
			<th class="artistSelectCell"><?= t('artists.column.foundArtist') ?></th>
			<th class="extrasCell"><?= t('artists.column.nameToStore') ?></th>
			<th class="resultCell"><?= t('artists.column.result') ?></th>
		</tr>
	</thead>
	<tbody id="artistMatchRows">

	</tbody>
</table>
<button id="submitButton"><?= t('artists.submit') ?></button>

<table id="songTable" hidden>
	<thead>
		<tr>
			<th class="songArtistCell"><?= t('songs.column.artist') ?></th>
			<th class="songTitleCell"><?= t('songs.column.title') ?></th>
			<th class="resultCell"><?= t('songs.column.result') ?></th>
		</tr>
	</thead>
	<tbody id="songRows">

	</tbody>
</table>
<button id="submitSongsButton" hidden><?= t('songs.submit') ?></button>

<script id="artistNamesData" type="application/json"><?= json_encode($globalData['artistNames']) ?></script>
<script src="/modules/music/js/addSongs.js"></script>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
