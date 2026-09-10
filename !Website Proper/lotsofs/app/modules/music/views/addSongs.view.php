<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('addSongs.heading') ?>
</h1>
<textarea id="pasteInput" placeholder="<?= htmlspecialchars(t('addSongs.pastePlaceholder')) ?>">
</textarea>
<p id="statusMessage"></p>
<table id="artistMatchTable">
	<thead>
		<tr>
			<th class="providedNameCell"><?= t('artist.column.providedName') ?></th>
			<th class="artistSelectCell"><?= t('artist.column.found') ?></th>
			<th class="extrasCell"><?= t('artist.column.nameToStore') ?></th>
			<th class="resultCell"><?= t('artist.column.result') ?></th>
		</tr>
	</thead>
	<tbody id="artistMatchRows">

	</tbody>
</table>
<button id="submitButton"><?= t('artist.submit') ?></button>

<table id="songTable" hidden>
	<thead>
		<tr>
			<th class="songArtistCell"><?= t('song.column.artist') ?></th>
			<th class="songTitleCell"><?= t('song.column.title') ?></th>
			<th class="songSelectCell"><?= t('song.column.found') ?></th>
			<th class="extrasCell"><?= t('song.column.nameToStore') ?></th>
			<th class="resultCell"><?= t('song.column.result') ?></th>
		</tr>
	</thead>
	<tbody id="songRows">

	</tbody>
</table>
<button id="submitSongsButton" hidden><?= t('song.submit') ?></button>

<table id="albumTable" hidden>
	<thead>
		<tr>
			<th class="providedNameCell"><?= t('album.column.providedName') ?></th>
			<th class="albumSelectCell"><?= t('album.column.found') ?></th>
			<th class="extrasCell"><?= t('album.column.nameToStore') ?></th>
			<th class="albumArtistCell"><?= t('album.column.attributedTo') ?></th>
			<th class="albumYearCell"><?= t('album.column.year') ?></th>
			<th class="albumTracksCell"><?= t('album.column.tracks') ?></th>
			<th class="resultCell"><?= t('album.column.result') ?></th>
		</tr>
	</thead>
	<tbody id="albumRows">

	</tbody>
</table>
<button id="submitAlbumsButton" hidden><?= t('album.submit') ?></button>
<div id="albumScrollSpace" hidden></div>

<script id="artistNamesData" type="application/json"><?= json_encode($globalData['artistNames']) ?></script>
<script id="albumNamesData" type="application/json"><?= json_encode($globalData['albumNames']) ?></script>
<script src="/modules/music/js/addSongs.js"></script>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
