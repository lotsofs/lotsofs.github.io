<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1 id="songListHeading">
	<?= htmlspecialchars($globalData['listHeading']) ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('song.list.empty') ?></p>
<?php else: ?>
	<?php
		$artistLabel = htmlspecialchars(t('song.column.artist'));
		$albumLabel = htmlspecialchars(t('song.column.album'));
		$yearLabel = htmlspecialchars(t('song.column.year'));
		$durationLabel = htmlspecialchars(t('song.column.duration'));
		$linksLabel = htmlspecialchars(t('song.column.links'));
		$resultLabel = htmlspecialchars(t('song.column.result'));
		$tapToEnterAttr = ' data-placeholder="' . htmlspecialchars(t('song.list.tapToEnter')) . '"';

		$raterLabels = [];
		foreach ($globalData['raters'] as $rater) {
			$raterLabels[(int)$rater['id']] = [
				'score' => htmlspecialchars(t('song.list.raterScoreLabel', ['name' => $rater['account_name']])),
				'note' => htmlspecialchars(t('song.list.raterNoteLabel', ['name' => $rater['account_name']])),
				'name' => htmlspecialchars($rater['account_name']),
				'placeholder' => $rater['isMine'] ? $tapToEnterAttr : '',
			];
		}

		$canEditAttr = $globalData['isAdmin'] ? ' data-can-edit="1"' : '';
		$emptyLinks = array_fill_keys(array_column($globalData['linkFields'], 'key'), null);

		// One pass to derive every value/class/attribute either tree needs, so the
		// table and card loops below just read $song['x'] rather than recomputing it.
		$songRows = [];
		$visibleCount = 0;
		foreach ($globalData['songs'] as $song) {
			$albumIds = $song['album_ids'] === null ? [] : explode(',', $song['album_ids']);
			$artistIds = $song['artist_ids'] === null ? [] : explode(',', $song['artist_ids']);
			if ($globalData['filterAlbum'] !== null) {
				$song['hidden'] = !in_array((string)$globalData['filterAlbum'], $albumIds, true);
			}
			else {
				$song['hidden'] = $globalData['filterArtist'] !== null && !in_array((string)$globalData['filterArtist'], $artistIds, true);
			}
			if (!$song['hidden']) {
				$visibleCount++;
			}

			$song['hiddenAttr'] = $song['hidden'] ? ' hidden' : '';
			$song['albumIdsAttr'] = htmlspecialchars($song['album_ids'] ?? '');
			$song['artistValue'] = htmlspecialchars($song['artist'] ?? '');

			$canonicalTitle = $song['title'] ?? '';
			$listedAs = $globalData['listedAsBySong'][(int)$song['id']] ?? null;
			$allNames = $song['all_names'] ?? '';
			$song['titleAliasedClass'] = $listedAs === null ? '' : ' songTitleAliased';
			$song['canonicalTitleAttr'] = htmlspecialchars($canonicalTitle);
			$song['titleTooltipAttr'] = $allNames === $canonicalTitle ? '' : ' title="' . htmlspecialchars($allNames) . '"';
			$song['titleValue'] = htmlspecialchars($listedAs ?? $canonicalTitle);

			$song['albumsValue'] = htmlspecialchars($song['albums'] ?? '');
			$song['artistIdsAttr'] = htmlspecialchars($song['artist_ids'] ?? '');

			$song['songYearAttr'] = htmlspecialchars($song['song_year'] !== null ? (string)(int)$song['song_year'] : '');
			$song['fallbackYearAttr'] = htmlspecialchars($song['fallback_year'] !== null ? (string)(int)$song['fallback_year'] : '');
			$displayYear = $song['song_year'] ?? $song['fallback_year'];
			$song['displayYearValue'] = htmlspecialchars($displayYear !== null ? (string)(int)$displayYear : '');

			$duration = $song['duration'] !== null ? (int)$song['duration'] : null;
			$song['durationAttr'] = htmlspecialchars($duration !== null ? (string)$duration : '');
			$song['durationValue'] = $duration === null ? '' : sprintf('%d:%02d', intdiv($duration, 60), $duration % 60);

			$song['spotifyTrackId'] = null;
			if (!empty($song['spotify_url'])) {
				if (preg_match('#/track/([A-Za-z0-9]+)#', $song['spotify_url'], $spotifyMatch)) {
					$song['spotifyTrackId'] = $spotifyMatch[1];
				}
				elseif (preg_match('#^[A-Za-z0-9]+$#', trim($song['spotify_url']))) {
					$song['spotifyTrackId'] = trim($song['spotify_url']);
				}
			}

			$song['youtubeTrackId'] = null;
			if (!empty($song['youtube_url'])) {
				if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]+)#', $song['youtube_url'], $youtubeMatch)) {
					$song['youtubeTrackId'] = $youtubeMatch[1];
				}
				elseif (preg_match('#^[A-Za-z0-9_-]+$#', trim($song['youtube_url']))) {
					$song['youtubeTrackId'] = trim($song['youtube_url']);
				}
			}

			$song['soundcloudEmbedUrl'] = null;
			if (!empty($song['soundcloud_url'])) {
				$song['soundcloudEmbedUrl'] = 'https://w.soundcloud.com/player/?url=' . urlencode($song['soundcloud_url'])
					. '&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true';
			}

			$songLinks = $globalData['songLinksBySong'][$song['id']] ?? $emptyLinks;
			$song['linksAttr'] = htmlspecialchars(json_encode($songLinks));

			$song['linkChips'] = [];
			foreach ($globalData['linkFields'] as $field) {
				$linkValue = $songLinks[$field['key']] ?? null;
				if ($linkValue === null || $linkValue === '') {
					continue;
				}
				if ($field['key'] === 'spotify_url' && $song['spotifyTrackId'] !== null) {
					continue;
				}
				if ($field['key'] === 'youtube_url' && $song['youtubeTrackId'] !== null) {
					continue;
				}
				if ($field['key'] === 'soundcloud_url' && $song['soundcloudEmbedUrl'] !== null) {
					continue;
				}
				$song['linkChips'][] = [
					'label' => htmlspecialchars($field['label']),
					'url' => htmlspecialchars($linkValue),
					'isPath' => $field['key'] === 'filepath',
					'isOther' => $field['key'] === 'other_url',
				];
			}

			$song['linkAbbrs'] = [];
			foreach ($globalData['linkFields'] as $field) {
				$linkValue = $songLinks[$field['key']] ?? null;
				$song['linkAbbrs'][] = [
					'abbr' => htmlspecialchars($field['abbr']),
					'label' => htmlspecialchars($field['label']),
					'url' => $linkValue !== null && $linkValue !== '' ? htmlspecialchars($linkValue) : null,
					'isPath' => $field['key'] === 'filepath',
				];
			}

			$song['ratings'] = [];
			foreach ($globalData['raters'] as $rater) {
				$raterId = (int)$rater['id'];

				$score = $song['score_' . $raterId];
				$score = $score === null ? '' : (string)(float)$score;

				$note = $song['note_' . $raterId] ?? '';

				$song['ratings'][$raterId] = [
					'scoreValue' => htmlspecialchars($score),
					'scoreEmptyClass' => $score === '' ? ' songCellEmpty' : '',
					'noteValue' => htmlspecialchars($note),
					'noteEmptyClass' => $note === '' ? ' songCellEmpty' : '',
				];
			}

			$songRows[] = $song;
		}
	?>
	<script>
		if (window.matchMedia("(max-width: 700px)").matches) {
			document.documentElement.classList.add("songCardView");
		}
	</script>
	<div class="songLayout">
		<form id="songFilterForm" class="songFilters" method="get" action="/music/songs">
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
			<div id="songNotePreview" class="songNotePreview songNotePreviewEmpty">
				<strong id="songNotePreviewHeader" class="songNotePreviewHeader"></strong>
				<span id="songNotePreviewText"><?= t('song.list.notePreviewEmpty') ?></span>
				<button type="button" id="songNotePreviewClear" class="songNotePreviewClear"><?= t('song.list.notePreviewClear') ?></button>
			</div>
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
				<table id="songListTable" class="hideResultColumn"<?= $canEditAttr ?>>
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
								<td class="songArtistCell" data-field="artist"><?= $song['artistValue'] ?></td>
								<td class="songTitleCell<?= $song['titleAliasedClass'] ?>" data-field="title" data-canonical-title="<?= $song['canonicalTitleAttr'] ?>"<?= $song['titleTooltipAttr'] ?>><?= $song['titleValue'] ?></td>
								<td class="songAlbumCell" data-field="album" title="<?= $song['albumsValue'] ?>"><?= $song['albumsValue'] ?></td>
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
												<a class="songLinkAbbr" href="<?= $abbr['url'] ?>" target="_blank" rel="noopener" title="<?= $abbr['label'] ?>"><?= $abbr['abbr'] ?></a>
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
				<div id="songCards" class="hideResultColumn">
					<?php foreach ($songRows as $song): ?>
						<dl class="songCard" data-song-id="<?= (int)$song['id'] ?>" data-artist-id="<?= (int)$song['artist_id'] ?>" data-artist-ids="<?= $song['artistIdsAttr'] ?>" data-album-ids="<?= $song['albumIdsAttr'] ?>" data-links="<?= $song['linksAttr'] ?>" data-song-year="<?= $song['songYearAttr'] ?>" data-fallback-year="<?= $song['fallbackYearAttr'] ?>" data-duration="<?= $song['durationAttr'] ?>"<?= $song['hiddenAttr'] ?>>
							<div class="songCardIdRow">
								<dd class="songIdCell" data-field="id"><?= htmlspecialchars($song['id']) ?></dd>
								<?php if ($globalData['isAdmin']): ?>
									<button type="button" class="songCardEditBtn"><?= htmlspecialchars(t('song.list.cardModalEdit')) ?></button>
								<?php endif ?>
							</div>
							<dd class="songTitleCell<?= $song['titleAliasedClass'] ?>" data-field="title" data-canonical-title="<?= $song['canonicalTitleAttr'] ?>"<?= $song['titleTooltipAttr'] ?>><?= $song['titleValue'] ?></dd>
							<div class="songCardMeta">
								<div class="songCardInfo">
									<dt><?= $artistLabel ?></dt>
									<dd class="songArtistCell" data-field="artist"><?= $song['artistValue'] ?></dd>
									<dt><?= $albumLabel ?></dt>
									<dd class="songAlbumCell" data-field="album" title="<?= $song['albumsValue'] ?>"><?= $song['albumsValue'] ?></dd>
									<dt><?= $yearLabel ?></dt>
									<dd class="songYearCell" data-field="year"><?= $song['displayYearValue'] ?></dd>
									<dt><?= $durationLabel ?></dt>
									<dd class="songDurationCell" data-field="duration"><?= $song['durationValue'] ?></dd>
								</div>
								<div class="songCardLinksCol">
									<dt><?= $linksLabel ?></dt>
									<div class="songLinksArea" data-field="links"><?php if ($song['spotifyTrackId'] !== null): ?>
										<div class="songSpotifyEmbed">
											<iframe src="https://open.spotify.com/embed/track/<?= htmlspecialchars($song['spotifyTrackId']) ?>?theme=0" width="280" height="80" frameborder="0" loading="lazy" allow="encrypted-media; clipboard-write" title="Spotify"></iframe>
										</div>
									<?php endif ?><?php if ($song['youtubeTrackId'] !== null): ?>
										<div class="songYoutubeEmbed">
											<iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($song['youtubeTrackId']) ?>" width="280" height="158" frameborder="0" loading="lazy" allowfullscreen title="YouTube"></iframe>
										</div>
									<?php endif ?><?php if ($song['soundcloudEmbedUrl'] !== null): ?>
										<div class="songSoundcloudEmbed">
											<iframe src="<?= htmlspecialchars($song['soundcloudEmbedUrl']) ?>" width="280" height="166" frameborder="0" loading="lazy" allow="autoplay" title="SoundCloud"></iframe>
										</div>
									<?php endif ?><?php if ($song['linkChips']): ?>
										<div class="songLinkChips">
											<?php foreach ($song['linkChips'] as $chip): ?>
												<?php if ($chip['isPath']): ?>
													<span class="songLinkChip songLinkChipWrap" title="<?= $chip['url'] ?>"><?= $chip['label'] ?>: <?= $chip['url'] ?></span>
												<?php else: ?>
													<a class="songLinkChip" href="<?= $chip['url'] ?>" target="_blank" rel="noopener" title="<?= $chip['url'] ?>"><?= $chip['isOther'] ? $chip['url'] : $chip['label'] ?></a>
												<?php endif ?>
											<?php endforeach ?>
										</div>
									<?php endif ?></div>
								</div>
							</div>
							<?php foreach ($globalData['raters'] as $rater): ?>
								<?php
									$raterId = (int)$rater['id'];
									$labels = $raterLabels[$raterId];
									$rating = $song['ratings'][$raterId];
								?>
								<dt><?= $labels['score'] ?></dt>
								<dd class="<?= $rater['scoreClass'] ?><?= $rating['scoreEmptyClass'] ?>" data-field="score_<?= $raterId ?>" data-account-id="<?= $raterId ?>"<?= $labels['placeholder'] ?>><?= $rating['scoreValue'] ?></dd>
								<dt><?= $labels['note'] ?></dt>
								<dd class="<?= $rater['noteClass'] ?><?= $rating['noteEmptyClass'] ?>" data-field="note_<?= $raterId ?>" data-account-id="<?= $raterId ?>" data-rater-name="<?= $labels['name'] ?>"<?= $labels['placeholder'] ?> title="<?= $rating['noteValue'] ?>"><span class="ratingNoteText"><?= $rating['noteValue'] ?></span></dd>
							<?php endforeach ?>
							<dt class="songResultCell"><?= $resultLabel ?></dt>
							<dd class="songResultCell" data-field="result"></dd>
						</dl>
					<?php endforeach ?>
				</div>
				<div id="songCardModal" class="songCardModal hideResultColumn" hidden>
					<div class="songCardModalDialog">
						<div class="songCardModalActions">
							<button type="button" id="songCardModalClose" class="songCardModalClose"><?= htmlspecialchars(t('song.list.cardModalClose')) ?></button>
						</div>
						<div id="songCardModalBody"></div>
					</div>
				</div>
			</div>
			<p id="songNoMatch"<?= $visibleCount > 0 ? ' hidden' : '' ?>><?= t('song.list.noMatch') ?></p>
		</div>
	</div>
	<div id="songToasts" class="songToasts" role="status" aria-live="polite"></div>
	<script id="songArtistData" type="application/json"><?= json_encode($globalData['artistOptions'], JSON_HEX_TAG) ?></script>
	<script id="songAlbumData" type="application/json"><?= json_encode($globalData['albumOptions'], JSON_HEX_TAG) ?></script>
	<script id="songLinkFieldData" type="application/json"><?= json_encode($globalData['linkFields'], JSON_HEX_TAG) ?></script>
	<script id="songTrackAliases" type="application/json"><?= json_encode($globalData['trackAliases'], JSON_HEX_TAG) ?></script>
	<script id="songRatingCursor" type="application/json"><?= (int)$globalData['ratingCursor'] ?></script>
	<script id="songAuditCursor" type="application/json"><?= (int)$globalData['auditCursor'] ?></script>
	<script src="/modules/music/js/songs.js"></script>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
