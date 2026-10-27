<?php foreach ($section['staves'] as $line => $staff): ?>
<?php $bleeds = $section['bleed'] > 0 && $line === array_key_last($section['staves']) ?>
<div class="bassTabStaffClip<?= $bleeds ? ' bassTabBleed' : '' ?>"<?= $bleeds ? ' style="--bleed: min(' . $section['bleed'] . 'px, ' . round($section['bleed'] * 100 / $globalData['widest'], 3) . 'cqw)"' : '' ?>>
	<svg class="bassTabStaff" viewBox="0 0 <?= $staff['width'] ?> <?= $staff['height'] ?>" preserveAspectRatio="xMinYMin meet" style="width: min(<?= $staff['width'] ?>px, <?= round(100 * $staff['width'] / $globalData['widest'], 3) ?>cqw)" <?= $line === 0 ? 'role="img" aria-label="' . htmlspecialchars($section['notation']) . '"' : 'aria-hidden="true"' ?>>
		<?php foreach ($staff['lineY'] as $string => $y): ?>
			<text class="bassTabStringName" x="0" y="<?= $y ?>"><?= $string ?></text>
			<line class="bassTabLine" data-string="<?= $string ?>" x1="<?= $staff['lineStart'] ?>" y1="<?= $y ?>" x2="<?= $staff['lineEnd'] ?>" y2="<?= $y ?>" />
		<?php endforeach; ?>
		<?php foreach ($staff['bars'] as $bar): ?>
			<line class="bassTabBar" x1="<?= $bar['x'] ?>" y1="<?= $bar['y1'] ?>" x2="<?= $bar['x'] ?>" y2="<?= $bar['y2'] ?>" />
			<?php foreach ($bar['dots'] as [$cx, $cy]): ?>
				<circle class="bassTabRepeatDot" cx="<?= $cx ?>" cy="<?= $cy ?>" r="2" />
			<?php endforeach; ?>
		<?php endforeach; ?>
		<?php foreach ($staff['notes'] as $note): ?>
			<?php if ($note['hold'] !== null): ?>
				<line class="bassTabHold" x1="<?= $note['hold'][0] ?>" y1="<?= $note['y'] ?>" x2="<?= $note['hold'][1] ?>" y2="<?= $note['y'] ?>" />
			<?php endif; ?>
			<text class="bassTabFret" data-string="<?= $note['string'] ?>"<?= $note['mark'] !== null ? ' data-mark="' . $note['mark'] . '"' : '' ?> x="<?= $note['x'] ?>" y="<?= $note['y'] ?>"><?= $note['fret'] ?></text>
			<text class="bassTabNoteName" x="<?= $note['x'] ?>" y="<?= $staff['nameY'] ?>"><?= htmlspecialchars($note['name']) ?></text>
		<?php endforeach; ?>
		<?php foreach ($staff['notes'] as $note): ?>
			<?php foreach ($note['markDots'] as [$cx, $cy]): ?>
				<circle class="bassTabMarkDot" cx="<?= $cx ?>" cy="<?= $cy ?>" r="1.5" />
			<?php endforeach; ?>
			<?php if ($note['markTilde'] !== null): ?>
				<path class="bassTabMarkTilde" d="<?= $note['markTilde'] ?>" />
			<?php endif; ?>
		<?php endforeach; ?>
		<?php foreach ($staff['rests'] as $rest): ?>
			<path class="bassTabRest" d="<?= $rest['path'] ?>" />
			<?php if ($rest['dot'] !== null): ?>
				<circle class="bassTabRestDot" cx="<?= $rest['dot'][0] ?>" cy="<?= $rest['dot'][1] ?>" r="2" />
			<?php endif; ?>
		<?php endforeach; ?>
		<?php foreach ($staff['hammers'] as $hammer): ?>
			<path class="bassTabHammer" d="<?= $hammer['arc'] ?>" />
			<text class="bassTabHammerLabel" x="<?= $hammer['labelX'] ?>" y="<?= $hammer['labelY'] ?>">h</text>
		<?php endforeach; ?>
		<?php foreach ($staff['slides'] as $slide): ?>
			<line class="bassTabSlide" data-slide="<?= $slide['direction'] ?>" x1="<?= $slide['x1'] ?>" y1="<?= $slide['y1'] ?>" x2="<?= $slide['x2'] ?>" y2="<?= $slide['y2'] ?>" />
		<?php endforeach; ?>
		<?php foreach ($staff['brackets'] as $bracket): ?>
			<polyline class="bassTabBracket" points="<?= $bracket['points'] ?>" />
			<text class="bassTabLabel" x="<?= $bracket['labelX'] ?>" y="<?= $bracket['labelY'] ?>"><?= htmlspecialchars($bracket['label']) ?></text>
		<?php endforeach; ?>
		<?php foreach ($staff['arrows'] as $arrow): ?>
			<polygon class="bassTabArrowHead" points="<?= $arrow['head'] ?>" />
			<line class="bassTabArrow" x1="<?= $arrow['x'] ?>" y1="<?= $arrow['shaft'][0] ?>" x2="<?= $arrow['x'] ?>" y2="<?= $arrow['shaft'][1] ?>" />
			<text class="bassTabLabel" x="<?= $arrow['x'] ?>" y="<?= $arrow['labelY'] ?>"><?= htmlspecialchars($arrow['label']) ?></text>
		<?php endforeach; ?>
	</svg>
</div>
<?php endforeach; ?>
