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
			<?php if ($release['entries']): ?>
				<ul>
					<?php foreach ($release['entries'] as $entry): ?>
						<li><?= $entry ?></li>
					<?php endforeach ?>
				</ul>
			<?php endif ?>
			<?php if ($release['adminEntries']): ?>
				<h3><?= t('changes.forAdmins') ?></h3>
				<ul>
					<?php foreach ($release['adminEntries'] as $entry): ?>
						<li><?= $entry ?></li>
					<?php endforeach ?>
				</ul>
			<?php endif ?>
		</section>
	<?php endforeach ?>
</div>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
