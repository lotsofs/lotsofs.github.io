<?php require(__MODULES__ . '/basstabs/views/partials/head.php') ?>

<main class="bassTabSheet">
	<h1 class="bassTabListTitle">Bass Tabs</h1>
	<ul class="bassTabList">
		<?php foreach ($globalData['songs'] as $slug => $song): ?>
			<li>
				<a href="/basstabs/<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($song['title']) ?></a>
				<?php if (($song['artist'] ?? '') !== ''): ?>
					<span class="bassTabArtist">— <?= htmlspecialchars($song['artist']) ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</main>

<?php require(__MODULES__ . '/basstabs/views/partials/foot.php') ?>
