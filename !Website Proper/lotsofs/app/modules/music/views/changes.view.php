<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<div class="changes">
	<h1>
		<?= t('changes.title') ?>
	</h1>

	<p><?= t('changes.intro') ?></p>

	<?php foreach ($globalData['releases'] as $release): ?>
		<section class="changesRelease">
			<h2><?= htmlspecialchars($release['heading']) ?></h2>
			<?php foreach ($release['groups'] as $group): ?>
				<h3><?= $group['label'] ?></h3>
				<ul>
					<?php foreach ($group['entries'] as $entry): ?>
						<li><?= $entry ?></li>
					<?php endforeach ?>
				</ul>
			<?php endforeach ?>
		</section>
	<?php endforeach ?>
</div>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
