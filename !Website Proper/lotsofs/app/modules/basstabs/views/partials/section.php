<section class="bassTabSection<?= $section['board'] !== null ? ' bassTabWithBoard' : '' ?>">
	<div class="bassTabSectionMain">
		<?php if ($section['name'] !== ''): ?>
			<h2 class="bassTabSectionName"><?= htmlspecialchars($section['name']) ?></h2>
		<?php endif; ?>
		<?php if ($section['text'] !== ''): ?>
			<p class="bassTabSectionText"><?= htmlspecialchars($section['text']) ?></p>
		<?php endif; ?>
		<?php require(__MODULES__ . '/basstabs/views/partials/staff.php') ?>
		<?php if ($section['lines'] !== []): ?>
			<ul class="bassTabLines">
				<?php foreach ($section['lines'] as $text): ?>
					<li><?= htmlspecialchars($text) ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php if ($section['board'] !== null): ?>
		<div class="bassTabSectionBoard">
			<?php require(__MODULES__ . '/basstabs/views/partials/fretboard.php') ?>
		</div>
	<?php endif; ?>
</section>
