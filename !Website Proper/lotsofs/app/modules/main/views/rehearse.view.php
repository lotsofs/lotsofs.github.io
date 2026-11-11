<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title><?= htmlspecialchars($pageTitle) ?> - LotsOfS</title>
	<link rel="stylesheet" href="<?= asset('/modules/main/css/rehearse.css') ?>">
</head>
<body>

<main class="rehearse">
	<h1><?= htmlspecialchars($scene['title']) ?></h1>
	<p class="rehearseSource"><?= htmlspecialchars($scene['source']) ?></p>

	<details class="rehearseSettings">
		<summary>Voice, speed and text</summary>
		<label>Voice
			<select id="rehearseVoice">
				<option value="">Browser default (English)</option>
			</select>
		</label>
		<label>Speed
			<input type="range" id="rehearseRate" min="0.5" max="1.5" step="0.1" value="1">
			<output id="rehearseRateValue" for="rehearseRate">1.0×</output>
		</label>
		<label><input type="checkbox" id="rehearseShowText" checked> Show the lines</label>
		<button type="button" id="rehearseRestart">Back to the start</button>
	</details>

	<p id="rehearseNoSpeech" class="rehearseWarning" hidden>This browser cannot speak, so the lines can only be read.</p>

	<p class="rehearseDirection rehearseOpening">(<?= htmlspecialchars($scene['opening']) ?>)</p>

	<ol id="rehearseLines" data-scene="the-suspect">
<?php foreach ($sceneLines as $parts): ?>
		<li><?php foreach ($parts as $i => [$kind, $text]): ?><?= $i > 0 ? ' ' : '' ?><span class="<?= $kind === 'say' ? 'rehearseSay' : 'rehearseDirection' ?>"><?= htmlspecialchars($text) ?></span><?php endforeach ?></li>
<?php endforeach ?>
	</ol>

	<p class="rehearseDirection rehearseEnd">(End of scene.)</p>
</main>

<div class="rehearseControls">
	<p id="rehearseStatus" aria-live="polite">You open the scene. Say your line, then press Next line.</p>
	<div class="rehearseButtons">
		<button type="button" id="rehearseBack">Back</button>
		<button type="button" id="rehearseRepeat">Repeat</button>
		<button type="button" id="rehearseNext" class="rehearsePrimary">Next line</button>
	</div>
</div>

<script src="<?= asset('/modules/main/js/rehearse.js') ?>"></script>
</body>
</html>
