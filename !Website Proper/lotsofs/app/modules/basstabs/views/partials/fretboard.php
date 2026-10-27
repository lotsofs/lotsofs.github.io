<?php $board = $section['board'] ?>
<svg class="bassTabBoard" width="<?= $board['width'] ?>" viewBox="0 0 <?= $board['width'] ?> <?= $board['height'] ?>" preserveAspectRatio="xMinYMin meet" role="img" aria-label="<?= htmlspecialchars($board['label']) ?>">
	<rect class="bassTabBoardFrame" x="0.5" y="0.5" width="<?= $board['width'] - 1 ?>" height="<?= $board['height'] - 1 ?>" rx="6" />
	<g transform="translate(<?= $board['inset'] ?>, <?= $board['inset'] ?>)">
		<?php foreach ($board['keyLines'] as [$y, $word]): ?>
			<text class="bassTabBoardKey" x="0" y="<?= $y ?>"><?= htmlspecialchars($word) ?></text>
		<?php endforeach; ?>
		<?php foreach ($board['inlays'] as [$x, $width]): ?>
			<rect class="bassTabBoardInlay" x="<?= $x ?>" y="<?= $board['top'] ?>" width="<?= $width ?>" height="<?= $board['bottom'] - $board['top'] ?>" />
		<?php endforeach; ?>
		<?php foreach ($board['stringY'] as $string => $y): ?>
			<text class="bassTabStringName" x="<?= $board['nameX'] ?>" y="<?= $y ?>"><?= $string ?></text>
			<line class="bassTabBoardString" data-string="<?= $string ?>" x1="<?= $board['lineStart'] ?>" y1="<?= $y ?>" x2="<?= $board['boardEnd'] ?>" y2="<?= $y ?>" />
		<?php endforeach; ?>
		<line class="bassTabNut" x1="<?= $board['nutX'] ?>" y1="<?= $board['top'] ?>" x2="<?= $board['nutX'] ?>" y2="<?= $board['bottom'] ?>" />
		<?php foreach ($board['wires'] as $x): ?>
			<line class="bassTabFretWire" x1="<?= $x ?>" y1="<?= $board['top'] ?>" x2="<?= $x ?>" y2="<?= $board['bottom'] ?>" />
		<?php endforeach; ?>
		<?php foreach ($board['numbers'] as [$x, $fret]): ?>
			<text class="bassTabFretNumber" x="<?= $x ?>" y="<?= $board['numberY'] ?>"><?= $fret ?></text>
		<?php endforeach; ?>
		<?php foreach ($board['dots'] as $dot): ?>
			<circle class="bassTabBoardDot bassTabBoard<?= ucfirst($dot['tier']) ?><?= $dot['chord'] ? ' bassTabBoardChord' : '' ?>" data-string="<?= $dot['string'] ?>" data-fret="<?= $dot['fret'] ?>" cx="<?= $dot['x'] ?>" cy="<?= $dot['y'] ?>" r="10" />
			<text class="bassTabBoardName<?= $dot['tier'] !== 'scale' ? ' bassTabBoardNameOnDark' : '' ?>" x="<?= $dot['x'] ?>" y="<?= $dot['y'] ?>"><?= htmlspecialchars($dot['name']) ?></text>
		<?php endforeach; ?>
	</g>
</svg>
