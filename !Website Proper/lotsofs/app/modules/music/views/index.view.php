<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<div class="home">
	<h1>
		<?= t('music.title') ?>
	</h1>

	<?php if ($globalData['links']): ?>
		<p class="homeIntro"><?= t('home.intro') ?></p>

		<ul class="homeLinks">
			<?php foreach ($globalData['links'] as $link): ?>
				<li>
					<a href="<?= htmlspecialchars($link['href']) ?>"><?= htmlspecialchars($link['text']) ?></a>
					<?php if ($link['note'] !== null): ?>
						<p class="homeLinkNote"><?= htmlspecialchars($link['note']) ?></p>
					<?php endif ?>
					<?php if ($link['stats'] !== null): ?>
						<p class="homeLinkStats"><?= htmlspecialchars($link['stats']) ?></p>
					<?php endif ?>
				</li>
			<?php endforeach ?>
		</ul>
	<?php else: ?>
		<p><?= t('home.empty') ?></p>
	<?php endif ?>

	<?php if ($globalData['isAdmin']): ?>
		<details class="homeLinkEditor" id="homeLinkEditor"<?= $globalData['editing'] ? ' open' : '' ?>>
			<summary><?= t('home.edit') ?></summary>

			<?php foreach ($globalData['links'] as $fieldLink): ?>
				<form method="post" action="/music" class="homeLinkRow">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<input type="hidden" name="link_id" value="<?= (int)$fieldLink['id'] ?>">
					<?php require(__MODULES__ . '/music/views/partials/homeLinkFields.php') ?>
					<div class="homeLinkButtons">
						<button type="submit" name="action" value="save"><?= t('home.save') ?></button>
						<button type="submit" name="action" value="up" title="<?= t('home.moveUp') ?>" aria-label="<?= t('home.moveUp') ?>">↑</button>
						<button type="submit" name="action" value="down" title="<?= t('home.moveDown') ?>" aria-label="<?= t('home.moveDown') ?>">↓</button>
						<button type="submit" name="action" value="delete"><?= t('home.delete') ?></button>
					</div>
				</form>
			<?php endforeach ?>

			<form method="post" action="/music" class="homeLinkRow homeLinkNew">
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
				<?php $fieldLink = null ?>
				<?php require(__MODULES__ . '/music/views/partials/homeLinkFields.php') ?>
				<div class="homeLinkButtons">
					<button type="submit" name="action" value="add"><?= t('home.add') ?></button>
				</div>
			</form>
		</details>
	<?php endif ?>
</div>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
