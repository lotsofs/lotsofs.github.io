<?php require(__MODULES__ . '/basstabs/views/partials/head.php') ?>

<main class="bassTabSheet">
	<header class="bassTabHeader">
		<div class="bassTabCorner">
			<a class="bassTabBack" href="/basstabs">← All songs</a>
			<?php if ($globalData['subtitle'] !== ''): ?>
				<p class="bassTabSubtitle"><?= htmlspecialchars($globalData['subtitle']) ?></p>
			<?php endif; ?>
		</div>
		<h1 class="bassTabTitle"><?= htmlspecialchars($globalData['title']) ?></h1>
	</header>

	<?php foreach ($globalData['blocks'] as $block): ?>
		<?php if ($block['kind'] === 'boards'): ?>
			<div class="bassTabBoards<?= $block['right'] ? ' bassTabBoardsRight' : '' ?>">
				<?php foreach ($block['sections'] as $section): ?>
					<section class="bassTabBoardOnly">
						<?php if ($section['name'] !== ''): ?>
							<h2 class="bassTabSectionName"><?= htmlspecialchars($section['name']) ?></h2>
						<?php endif; ?>
						<?php require(__MODULES__ . '/basstabs/views/partials/fretboard.php') ?>
					</section>
				<?php endforeach; ?>
			</div>
		<?php elseif ($block['kind'] === 'row'): ?>
			<div class="bassTabRow">
				<?php foreach ($block['sections'] as $section): ?>
					<?php require(__MODULES__ . '/basstabs/views/partials/section.php') ?>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<?php $section = $block['section'] ?>
			<?php require(__MODULES__ . '/basstabs/views/partials/section.php') ?>
		<?php endif; ?>
	<?php endforeach; ?>
</main>

<?php require(__MODULES__ . '/basstabs/views/partials/foot.php') ?>
