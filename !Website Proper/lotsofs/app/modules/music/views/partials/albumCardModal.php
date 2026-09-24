<div id="albumCardModal" class="albumCardModal cardModal" hidden>
	<div class="albumCardModalDialog cardModalDialog">
		<div class="albumCardModalActions cardModalActions">
			<button type="button" id="albumCardModalClose" class="albumCardModalClose cardModalBtn"><?= htmlspecialchars(t('album.card.close')) ?></button>
		</div>
		<div id="albumCardModalBody"></div>
	</div>
</div>
<script src="<?= asset('/modules/music/js/albumCard.js') ?>"></script>
