<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1 id="songListHeading">
	<?= htmlspecialchars($globalData['listHeading']) ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('song.list.empty') ?></p>
<?php else: ?>
	<?php
		$songRows = [];
		$visibleCount = 0;
		foreach ($globalData['songs'] as $song) {
			$albumIds = $song['album_ids'] === null ? [] : explode(',', $song['album_ids']);
			if ($globalData['filterAlbum'] !== null) {
				$song['hidden'] = !in_array((string)$globalData['filterAlbum'], $albumIds, true);
			}
			else {
				$song['hidden'] = $globalData['filterArtist'] !== null && (int)$song['artist_id'] !== $globalData['filterArtist'];
			}
			if (!$song['hidden']) {
				$visibleCount++;
			}
			$songRows[] = $song;
		}
	?>
	<div class="songLayout">
		<form id="songFilterForm" class="songFilters" method="get" action="/music/songs">
			<h2><?= t('song.list.filterHeading') ?></h2>
			<input type="hidden" name="sort" value="<?= htmlspecialchars($globalData['sort']) ?>">
			<input type="hidden" name="dir" value="<?= htmlspecialchars($globalData['dir']) ?>">
			<label for="filterArtist"><?= t('song.list.filterArtist') ?></label>
			<select id="filterArtist" name="artist">
				<option value=""><?= t('song.list.filterAllArtists') ?></option>
				<?php foreach ($globalData['artistOptions'] as $option): ?>
					<option value="<?= (int)$option['id'] ?>"<?= (int)$option['id'] === $globalData['filterArtist'] ? ' selected' : '' ?>><?= $option['name'] === null ? t('artist.list.noName') : htmlspecialchars($option['name']) ?></option>
				<?php endforeach ?>
			</select>
			<label for="filterAlbum"><?= t('song.list.filterAlbum') ?></label>
			<select id="filterAlbum" name="album">
				<option value=""><?= t('song.list.filterAnyAlbum') ?></option>
				<?php foreach ($globalData['albumOptions'] as $option): ?>
					<?php
						$inScope = $globalData['filterArtist'] !== null
							? (int)$option['artist_id'] === $globalData['filterArtist']
							: $option['artist_id'] === null;
					?>
					<?php if ($inScope): ?>
						<option value="<?= (int)$option['id'] ?>"<?= (int)$option['id'] === $globalData['filterAlbum'] ? ' selected' : '' ?>><?= $option['name'] === null ? t('album.list.noName') : htmlspecialchars($option['name']) ?></option>
					<?php endif ?>
				<?php endforeach ?>
			</select>
			<button type="submit"><?= t('song.list.filterApply') ?></button>
		</form>

		<div class="songListArea">
			<p id="songEditHint"><?= $globalData['isAdmin'] ? t('song.list.editHintAdmin') : t('song.list.editHint') ?></p>
			<table id="songListTable" class="hideResultColumn"<?= $globalData['isAdmin'] ? ' data-can-edit="1"' : '' ?>>
				<thead>
					<tr>
						<?php foreach ($globalData['columns'] as $column): ?>
							<?php if (isset($column['group'])): ?>
								<?php if ($column['groupStart'] ?? false): ?>
									<th colspan="2" class="<?= $column['groupClass'] ?>"><?= htmlspecialchars($column['groupLabel']) ?></th>
								<?php endif ?>
							<?php else: ?>
								<th rowspan="2" class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>" data-sort-index="<?= $column['index'] ?>">
									<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
								</th>
							<?php endif ?>
						<?php endforeach ?>
						<th rowspan="2" class="songResultCell"><?= t('song.column.result') ?></th>
					</tr>
					<tr>
						<?php foreach ($globalData['columns'] as $column): ?>
							<?php if (isset($column['group'])): ?>
								<th class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>" data-sort-index="<?= $column['index'] ?>">
									<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
								</th>
							<?php endif ?>
						<?php endforeach ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($songRows as $song): ?>
						<tr data-artist-id="<?= (int)$song['artist_id'] ?>" data-album-ids="<?= htmlspecialchars($song['album_ids'] ?? '') ?>"<?= $song['hidden'] ? ' hidden' : '' ?>>
							<td class="songIdCell"><?= htmlspecialchars($song['id']) ?></td>
							<td class="songArtistCell"><?= htmlspecialchars($song['artist'] ?? '') ?></td>
							<?php
								$canonicalTitle = $song['title'] ?? '';
								$listedAs = $globalData['listedAsBySong'][(int)$song['id']] ?? null;
								$allNames = $song['all_names'] ?? '';
							?>
							<td class="songTitleCell<?= $listedAs === null ? '' : ' songTitleAliased' ?>" data-canonical-title="<?= htmlspecialchars($canonicalTitle) ?>"<?= $allNames === $canonicalTitle ? '' : ' title="' . htmlspecialchars($allNames) . '"' ?>><?= htmlspecialchars($listedAs ?? $canonicalTitle) ?></td>
							<td class="songAlbumCell" title="<?= htmlspecialchars($song['albums'] ?? '') ?>"><?= htmlspecialchars($song['albums'] ?? '') ?></td>
							<?php if ($globalData['showSharedNote']): ?>
								<td class="songNoteCell"><?= htmlspecialchars($song['objective_note'] ?? '') ?></td>
							<?php endif ?>
							<?php foreach ($globalData['raters'] as $rater): ?>
								<?php
									$score = $song['score_' . (int)$rater['id']];
									$note = $song['note_' . (int)$rater['id']] ?? '';
								?>
								<td class="<?= $rater['scoreClass'] ?>"><?= htmlspecialchars($score === null ? '' : (float)$score) ?></td>
								<td class="<?= $rater['noteClass'] ?>" title="<?= htmlspecialchars($note) ?>"><span class="ratingNoteText"><?= htmlspecialchars($note) ?></span></td>
							<?php endforeach ?>
							<td class="songResultCell"></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
			<p id="songNoMatch"<?= $visibleCount > 0 ? ' hidden' : '' ?>><?= t('song.list.noMatch') ?></p>
		</div>
	</div>
	<script id="songAlbumData" type="application/json"><?= json_encode($globalData['albumOptions'], JSON_HEX_TAG) ?></script>
	<script id="songTrackAliases" type="application/json"><?= json_encode($globalData['trackAliases'], JSON_HEX_TAG) ?></script>
	<script src="/modules/music/js/songs.js"></script>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
