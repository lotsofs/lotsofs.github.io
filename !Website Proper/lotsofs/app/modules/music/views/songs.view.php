<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1 id="songListHeading">
	<?= htmlspecialchars($globalData['listHeading']) ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('song.list.empty') ?></p>
<?php else: ?>
	<?php require(__MODULES__ . '/music/views/partials/songRows.php') ?>
	<script>
		if (window.matchMedia("(max-width: 700px)").matches) {
			document.documentElement.classList.add("songCardView");
		}
	</script>
	<div class="songLayout">
		<form class="songFilters" method="get" action="/music/songs">
			<h2><?= t('song.list.filterHeading') ?></h2>
			<input type="hidden" name="sort" value="<?= htmlspecialchars($globalData['sort']) ?>">
			<input type="hidden" name="dir" value="<?= htmlspecialchars($globalData['dir']) ?>">
			<div class="filterRow">
				<div class="filterGroup">
					<label for="filterArtist"><?= t('song.list.filterArtist') ?></label>
					<select id="filterArtist" name="artist">
						<option value=""><?= t('song.list.filterAllArtists') ?></option>
						<?php foreach ($globalData['artistOptions'] as $option): ?>
							<?php
								$selectedAttr = (int)$option['id'] === $globalData['filterArtist'] ? ' selected' : '';
								$optionLabel = $option['name'] === null ? t('artist.list.noName') : htmlspecialchars($option['name']);
							?>
							<option value="<?= (int)$option['id'] ?>"<?= $selectedAttr ?>><?= $optionLabel ?></option>
						<?php endforeach ?>
					</select>
				</div>
				<div class="filterGroup">
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
								<?php
									$selectedAttr = (int)$option['id'] === $globalData['filterAlbum'] ? ' selected' : '';
									$optionLabel = $option['name'] === null ? t('album.list.noName') : htmlspecialchars($option['name']);
								?>
								<option value="<?= (int)$option['id'] ?>"<?= $selectedAttr ?>><?= $optionLabel ?></option>
							<?php endif ?>
						<?php endforeach ?>
					</select>
				</div>
			</div>
			<button type="submit"><?= t('song.list.filterApply') ?></button>
		</form>

		<div class="songSidePanel">
			<button type="button" id="songCardViewToggle" class="songCardViewToggle" aria-pressed="false"><?= t('song.list.cardViewToggleOn') ?></button>
			<p id="songEditHint"><?= t('song.list.editHint') ?></p>
		</div>

		<div class="songListArea">
			<div class="songMobileSort">
				<label for="songMobileSortKey"><?= t('song.list.sortBy') ?></label>
				<select id="songMobileSortKey"></select>
				<select id="songMobileSortDir">
					<option value="asc"><?= t('song.list.sortAscending') ?></option>
					<option value="desc"><?= t('song.list.sortDescending') ?></option>
				</select>
			</div>
			<div class="songListScroll">
				<table id="songListTable" class="hideResultColumn" data-tooltip-titles>
					<thead>
						<tr>
							<?php foreach ($globalData['columns'] as $column): ?>
								<?php if (!isset($column['group'])): ?>
									<th rowspan="2" class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>">
										<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
									</th>
								<?php endif ?>
							<?php endforeach ?>
							<th rowspan="2" class="songLinksCell"><?= $linksLabel ?></th>
							<?php foreach ($globalData['columns'] as $column): ?>
								<?php if (isset($column['group']) && ($column['groupStart'] ?? false)): ?>
									<th colspan="2" class="<?= $column['groupClass'] ?>"><?= htmlspecialchars($column['groupLabel']) ?></th>
								<?php endif ?>
							<?php endforeach ?>
							<th rowspan="2" class="songResultCell"><?= $resultLabel ?></th>
						</tr>
						<tr>
							<?php foreach ($globalData['columns'] as $column): ?>
								<?php if (isset($column['group'])): ?>
									<th class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>">
										<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
									</th>
								<?php endif ?>
							<?php endforeach ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($songRows as $song): ?>
							<tr data-song-id="<?= (int)$song['id'] ?>" data-artist-id="<?= (int)$song['artist_id'] ?>" data-artist-ids="<?= $song['artistIdsAttr'] ?>" data-album-ids="<?= $song['albumIdsAttr'] ?>"<?= $song['hiddenAttr'] ?>>
								<td class="songIdCell" data-field="id"><?= htmlspecialchars($song['id']) ?></td>
								<td class="songArtistCell" data-field="artist" title="<?= $song['artistValue'] ?>"><span class="songCellText"><?= $song['artistValue'] ?></span></td>
								<td class="songTitleCell<?= $song['titleAliasedClass'] ?>" data-field="title" data-canonical-title="<?= $song['canonicalTitleAttr'] ?>"<?= $song['titleTooltipAttr'] ?>><span class="songCellText"><?= $song['titleValue'] ?></span></td>
								<td class="songAlbumCell" data-field="album" title="<?= $song['albumsValue'] ?>"><span class="songCellText"><?= $song['albumsHtml'] ?></span></td>
								<td class="songYearCell" data-field="year"><?= $song['displayYearValue'] ?></td>
								<td class="songDurationCell" data-field="duration"><?= $song['durationValue'] ?></td>
								<td class="songLinksCell" data-field="links">
									<?php foreach ($song['linkAbbrs'] as $abbr): ?>
										<?php if ($abbr['isPath']): ?>
											<?php if ($abbr['url'] !== null): ?>
												<abbr class="songLinkAbbr" data-path="<?= $abbr['url'] ?>" title="<?= $abbr['label'] ?>: <?= $abbr['url'] ?>"><?= $abbr['abbr'] ?></abbr>
											<?php else: ?>
												<abbr class="songLinkAbbr songLinkAbbrEmpty"><?= $abbr['abbr'] ?></abbr>
											<?php endif ?>
										<?php else: ?>
											<?php if ($abbr['url'] !== null): ?>
												<a class="songLinkAbbr" href="<?= $abbr['href'] ?>" target="_blank" rel="noopener" title="<?= $abbr['label'] ?>"><?= $abbr['abbr'] ?></a>
											<?php else: ?>
												<span class="songLinkAbbr songLinkAbbrEmpty"><?= $abbr['abbr'] ?></span>
											<?php endif ?>
										<?php endif ?>
									<?php endforeach ?>
								</td>
								<?php foreach ($globalData['raters'] as $rater): ?>
									<?php
										$raterId = (int)$rater['id'];
										$labels = $raterLabels[$raterId];
										$rating = $song['ratings'][$raterId];
									?>
									<td class="<?= $rater['scoreClass'] ?><?= $rating['scoreEmptyClass'] ?>" data-field="score_<?= $raterId ?>" data-account-id="<?= $raterId ?>"<?= $labels['placeholder'] ?>><?= $rating['scoreValue'] ?></td>
									<td class="<?= $rater['noteClass'] ?><?= $rating['noteEmptyClass'] ?>" data-field="note_<?= $raterId ?>" data-account-id="<?= $raterId ?>" data-rater-name="<?= $labels['name'] ?>"<?= $labels['placeholder'] ?> title="<?= $rating['noteValue'] ?>"><span class="ratingNoteText"><?= $rating['noteValue'] ?></span></td>
								<?php endforeach ?>
								<td class="songResultCell" data-field="result"></td>
							</tr>
						<?php endforeach ?>
					</tbody>
				</table>
				<div id="songCards" class="hideResultColumn" data-tooltip-titles>
					<?php foreach ($songRows as $song): ?>
						<?php require(__MODULES__ . '/music/views/partials/songCard.php') ?>
					<?php endforeach ?>
				</div>
				<div id="songCardModal" class="songCardModal cardModal hideResultColumn" data-tooltip-titles hidden>
					<div class="songCardModalDialog cardModalDialog">
						<div class="songCardModalActions cardModalActions">
							<button type="button" id="songCardModalPrev" class="songCardModalNav cardModalBtn"><?= htmlspecialchars(t('song.list.cardModalPrev')) ?></button>
							<button type="button" id="songCardModalNext" class="songCardModalNav cardModalBtn"><?= htmlspecialchars(t('song.list.cardModalNext')) ?></button>
							<button type="button" id="songCardModalClose" class="songCardModalClose cardModalBtn"><?= htmlspecialchars(t('song.list.cardModalClose')) ?></button>
						</div>
						<div id="songCardModalBody"></div>
					</div>
				</div>
			</div>
			<p id="songNoMatch"<?= $visibleCount > 0 ? ' hidden' : '' ?>><?= t('song.list.noMatch') ?></p>
		</div>
	</div>
	<div id="songToasts" class="songToasts" role="status" aria-live="polite"></div>
	<?php require(__MODULES__ . '/music/views/partials/albumCardModal.php') ?>
	<script id="songArtistData" type="application/json"><?= json_encode($globalData['artistOptions'], JSON_HEX_TAG) ?></script>
	<script id="songAlbumData" type="application/json"><?= json_encode($globalData['albumOptions'], JSON_HEX_TAG) ?></script>
	<script id="songLinkFieldData" type="application/json"><?= json_encode($globalData['linkFields'], JSON_HEX_TAG) ?></script>
	<script id="songTrackAliases" type="application/json"><?= json_encode($globalData['trackAliases'], JSON_HEX_TAG) ?></script>
	<script id="songRatingCursor" type="application/json"><?= (int)$globalData['ratingCursor'] ?></script>
	<script id="songAuditCursor" type="application/json"><?= (int)$globalData['auditCursor'] ?></script>
	<script src="<?= asset('/modules/music/js/songs.js') ?>"></script>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
