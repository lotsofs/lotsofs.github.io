<div id="albumCardModal" class="albumCardModal cardModal" hidden>
	<div class="albumCardModalDialog cardModalDialog">
		<div class="albumCardModalActions cardModalActions">
			<button type="button" id="albumCardModalClose" class="albumCardModalClose cardModalBtn"><?= htmlspecialchars(t('album.card.close')) ?></button>
		</div>
		<div id="albumCardModalBody"></div>
	</div>
</div>
<?php /* Before albumCard.js and songs.js, which both call into it. */ ?>
<script src="<?= asset('/modules/music/js/cardLink.js') ?>"></script>
<script src="<?= asset('/modules/music/js/albumCard.js') ?>"></script>
