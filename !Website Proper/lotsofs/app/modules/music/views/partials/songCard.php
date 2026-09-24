<dl class="songCard card" data-song-id="<?= (int)$song['id'] ?>" data-artist-id="<?= (int)($song['artist_id'] ?? 0) ?>" data-artist-ids="<?= $song['artistIdsAttr'] ?>" data-album-ids="<?= $song['albumIdsAttr'] ?>" data-links="<?= $song['linksAttr'] ?>" data-song-year="<?= $song['songYearAttr'] ?>" data-fallback-year="<?= $song['fallbackYearAttr'] ?>" data-duration="<?= $song['durationAttr'] ?>"<?= $song['hiddenAttr'] ?>>
	<div class="songCardIdRow cardIdRow">
		<dd class="songIdCell cardIdCell" data-field="id"><?= htmlspecialchars($song['id']) ?></dd>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<button type="button" class="songCardEditBtn cardEditBtn"><?= htmlspecialchars(t('song.list.cardModalEdit')) ?></button>
		<?php endif ?>
	</div>
	<dd class="cardTitle songTitleCell<?= $song['titleAliasedClass'] ?>" data-field="title" data-canonical-title="<?= $song['canonicalTitleAttr'] ?>"<?= $song['titleTooltipAttr'] ?>><?= $song['titleValue'] ?></dd>
	<div class="songCardMeta">
		<div class="songCardInfo">
			<dt><?= $artistLabel ?></dt>
			<dd class="songArtistCell" data-field="artist"><?= $song['artistValue'] ?></dd>
			<dt><?= $albumLabel ?></dt>
			<dd class="songAlbumCell" data-field="album" title="<?= $song['albumsValue'] ?>"><?= $song['albumsHtml'] ?></dd>
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
							<a class="songLinkChip" href="<?= $chip['href'] ?>" target="_blank" rel="noopener" title="<?= $chip['url'] ?>"><?= $chip['isOther'] ? $chip['url'] : $chip['label'] ?></a>
						<?php endif ?>
					<?php endforeach ?>
				</div>
			<?php endif ?></div>
		</div>
	</div>
	<?php foreach ($globalData['raters'] ?? [] as $rater): ?>
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
