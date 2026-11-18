<?php require(__MODULES__ . '/mtasa/views/partials/head.php') ?>
<?php
$map = $globalData['map'];
$id = (int)$map['id'];
$duplicates = $globalData['duplicates'];
$isAdmin = $globalData['isAdmin'];
$signedIn = $globalData['accountId'] > 0;
$threadsByField = $globalData['threads'];
$openByField = mtasaOpenThreadsByField($threadsByField);

$sections = [];
foreach (mtasaFields() as $key => $field) {
	$sections[$field['section']][] = $key;
}

/// A value as the page shows it: a map id is a link to that map, a URL a link
/// out, the rest text.
$valueHtml = function ($key) use ($map, $globalData) {
	$value = $map[$key];
	/// Worked out only in the browser, from the map's files (js/preview.js).
	if ($key === 'zones_on_route' && $value === null) {
		return $globalData['preview'] !== null ? '<span class="mtasaAfterDetail">' . t('map.afterDetail') . '</span>' : '';
	}
	$text = mtasaFieldText($key, $value);
	if ($text === '') {
		return '';
	}
	/// Ring checkpoints with their share of all checkpoints, "1 (4%)" (the
	/// owner's wish); js/preview.js shows GitHub's the same way.
	if ($key === 'ring_checkpoints') {
		$total = (int)($map['checkpoints'] ?? 0);

		return $total > 0 ? (int)$value . ' (' . round(100 * (int)$value / $total) . '%)' : htmlspecialchars($text);
	}
	$type = mtasaFields()[$key]['type'];
	if ($type === 'map') {
		return '<a href="/mtasa/map?id=' . (int)$value . '">' . (int)$value . '</a>';
	}
	if ($type === 'url') {
		return '<a href="' . htmlspecialchars($text) . '" target="_blank" rel="noopener">' . htmlspecialchars($text) . '</a>';
	}

	return htmlspecialchars($text);
};

/// An owner's field paired with what the script guessed for it: the original
/// name with the title, the gamemode with the category guess, Duplicate of with
/// the closest copy the script found. Once the owner's
/// is set, the rows that stand in for it are hidden (the values stay). An admin
/// takes the guess over with a ✓, greyed out for everyone once the two match,
/// and a guess that differs from the owner's value is flagged with a !.
$pairs = [];
foreach (['original_name' => 'title', 'gamemode' => 'category_guess', 'duplicate_of' => 'duplicate_of_closest'] as $owner => $guess) {
	$value = trim((string)$map[$owner]);
	$pairs[$owner] = [
		'guess' => $guess,
		'value' => $value,
		'guessValue' => trim((string)$map[$guess]),
		'known' => $owner === 'original_name' ? mtasaOriginalNameKnown($value) : $value !== '',
	];
}
$guessPair = ['title' => 'original_name', 'category_guess' => 'gamemode', 'duplicate_of_closest' => 'duplicate_of'];

$mismatchButton = function ($owner) use ($pairs) {
	$pair = $pairs[$owner];
	if ($pair['guessValue'] === '') {
		return '';
	}
	$text = htmlspecialchars(t('map.guessMismatch.' . $owner, ['value' => $pair['value']]));
	$differs = $pair['known'] && $pair['value'] !== $pair['guessValue'];

	return '<button type="button" class="mtasaGuessMismatch" data-pair="' . $owner . '" data-tooltip="' . $text . '" aria-label="' . $text . '"' . ($differs ? '' : ' hidden') . '>!</button>';
};

/// aria-disabled rather than disabled, which would swallow the hover that shows
/// its tooltip.
$useGuessButton = function ($owner) use ($pairs, $isAdmin) {
	$pair = $pairs[$owner];
	$set = $pair['value'] === $pair['guessValue'];
	if ($pair['guessValue'] === '' || (!$isAdmin && !$set)) {
		return '';
	}
	$text = htmlspecialchars($set ? t('map.guessIsSet.' . $owner) : t('map.useGuess.' . $owner, ['value' => $pair['guessValue']]));

	return '<button type="button" class="mtasaUseGuess" data-pair="' . $owner . '" data-tooltip="' . $text . '" aria-label="' . $text . '"' . ($set ? ' aria-disabled="true"' : '') . '>✓&#xFE0E;</button>';
};

/// Rows hidden once their pair's owner field is set.
$hiddenWhenSet = ['custom_name' => 'original_name', 'hypothesized_name' => 'original_name'];

/// Two sections repeat the script's guesses among the owner's fields while the
/// owner's is unset, read-only and without comments, which stay on the Guesses
/// rows: [key, label, the pair whose owner field hides it once set]. A key is
/// that field's own row, and every field the registry puts in the section must
/// be listed. The custom description goes under the script's (the owner's wish).
$sectionItems = [
	'about' => [
		'original_name', 'custom_name', 'hypothesized_name',
		['title', t('about.gatheredName'), 'original_name'],
		['title_source', t('about.gatheredNameSource'), 'original_name'],
		'author', 'description', 'custom_description', 'folder', 'version',
	],
	'classification' => [
		'category', 'gamemode',
		['category_guess', t('field.category_guess'), 'gamemode'],
		['category_confidence', t('field.category_confidence'), 'gamemode'],
		['category_reason', t('field.category_reason'), 'gamemode'],
		'race_mode', 'vehicle_classes',
	],
	'duplicates' => [
		'duplicate_of',
		['duplicate_of_closest', t('field.duplicate_of_closest'), 'duplicate_of'],
		['duplicate_confidence', t('field.duplicate_confidence'), 'duplicate_of'],
		['duplicate_similarity', t('field.duplicate_similarity'), 'duplicate_of'],
		['duplicate_differences', t('field.duplicate_differences'), 'duplicate_of'],
	],
];

/// Under the Guesses heading the gamemode and duplicate guesses' rows need not
/// say "guess" (the owner's wish); everywhere else, the Comments page included,
/// they do.
$shortLabels = [
	'category_guess' => t('guesses.gamemode'),
	'category_confidence' => t('guesses.gamemodeConfidence'),
	'category_reason' => t('guesses.gamemodeReason'),
	'duplicate_of_closest' => t('guesses.duplicate'),
	'duplicate_confidence' => t('guesses.duplicateConfidence'),
	'duplicate_similarity' => t('guesses.duplicateSimilarity'),
	'duplicate_differences' => t('guesses.duplicateDifferences'),
];

$label = mtasaDuplicateLabel($duplicates, $id);
?>

<div class="mtasaMapHead">
	<h1><?= htmlspecialchars(mtasaMapName($map)) ?></h1>
	<p class="mtasaMapMeta">
		<span>z(<?= $id ?>)</span>
		<?php if ($label !== null): ?>
			<span><a href="/mtasa/map?id=<?= (int)$duplicates['root'][$id] ?>"><?= htmlspecialchars($label) ?></a></span>
		<?php endif ?>
	</p>
	<nav class="mtasaMapSteps">
		<?php if ($globalData['previous'] !== null): ?>
			<a href="/mtasa/map?id=<?= $globalData['previous'] ?>" rel="prev"><?= t('map.previous') ?></a>
		<?php endif ?>
		<a href="/mtasa"><?= t('map.backToList') ?></a>
		<?php if ($globalData['next'] !== null): ?>
			<a href="/mtasa/map?id=<?= $globalData['next'] ?>" rel="next"><?= t('map.next') ?></a>
		<?php endif ?>
	</nav>
</div>

<p class="mtasaStatus" id="mtasaStatus" role="status"></p>

<div class="mtasaMap" id="mtasaMap" data-map-id="<?= $id ?>" data-signed-in="<?= $signedIn ? '1' : '0' ?>">
	<?php /* The route preview heads the page, above About (the owner's wish). */ ?>
	<?php if ($globalData['preview'] !== null): ?>
		<section class="mtasaSection">
			<h2><?= t('section.map') ?></h2>
			<?php $folder = 'z(' . $id . ')' ?>
			<?php /* The settings sit right of the picture, and below it where the
			         column has no room for both (a wrapping flexbox). */ ?>
			<div class="mtasaPreviewLayout">
				<figure class="mtasaPreviewFigure" id="preview" data-github="<?= htmlspecialchars(MTASA_GITHUB_RAW . $folder . '/') ?>" data-zones="<?= htmlspecialchars(asset('/modules/mtasa/data/zones.json')) ?>" data-github-root="<?= htmlspecialchars(MTASA_GITHUB_RAW) ?>">
					<?= $globalData['preview'] ?>
					<?php /* The height profile, drawn by js/preview.js on Increase detail: only the map's files have heights. */ ?>
					<div class="mtasaProfile" id="mtasaProfile" hidden></div>
				</figure>
				<div class="mtasaPreviewSide">
					<?php /* The points js/preview.js draws from, the sheet's. */ ?>
					<script type="application/json" id="previewData"><?= json_encode($globalData['previewData'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
					<?php /* The stored values of the sections js/analysis.js works out again
					         from GitHub, with their types, for js/preview.js to compare. */ ?>
					<?php
					$stored = [];
					foreach (mtasaFields() as $key => $field) {
						if ($field['table'] !== 'map') {
							$stored[$key] = ['type' => $field['type'], 'value' => $map[$key] ?? null];
						}
					}
					?>
					<?php /* The vehicle names, the one list (fields.php), for the hover boxes and the reworked rows. */ ?>
					<script type="application/json" id="mtasaVehicleNames"><?= json_encode(mtasaVehicleNames(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
					<script type="application/json" id="mapStored"><?= json_encode($stored, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
					<?php /* The settings are the script's, so they show only where it runs. */ ?>
					<form class="mtasaPreviewSettings" hidden>
						<div class="mtasaPreviewDetail">
							<?php /* What clicking gives away, read before the button (the owner's wish). */ ?>
							<p class="mtasaPreviewHint"><?= t('map.preview.loadHint') ?></p>
							<button type="button" class="mtasaPreviewLoad"><?= t('map.preview.load') ?></button>
							<label class="mtasaPreviewCheck"><input type="checkbox" name="always"> <?= t('map.preview.always') ?></label>
							<p class="mtasaPreviewNote" aria-live="polite"></p>
						</div>
						<label class="mtasaPreviewSetting">
							<span><?= t('map.preview.colours') ?></span>
							<select name="colours">
								<option value="map" selected><?= t('map.preview.colours.map') ?></option>
								<option value="gradient"><?= t('map.preview.colours.gradient') ?></option>
								<option value="rainbow"><?= t('map.preview.colours.rainbow') ?></option>
								<option value="blue"><?= t('map.preview.colours.blue') ?></option>
							</select>
						</label>
						<?php /* Shown by js/preview.js while the colours wait for Increase detail. */ ?>
						<p class="mtasaPreviewSettingNote" id="mtasaColoursNote" hidden><?= t('map.preview.coloursNeedDetail') ?></p>
						<label class="mtasaPreviewSetting">
							<span><?= t('map.preview.frame') ?></span>
							<select name="frame">
								<option value="track" selected><?= t('map.preview.frame.track') ?></option>
								<option value="all"><?= t('map.preview.frame.all') ?></option>
							</select>
						</label>
						<?php foreach (MTASA_PREVIEW_SLIDERS as $name => [$min, $max, $step, $default]): ?>
							<label class="mtasaPreviewSetting">
								<span><?= t('map.preview.slider.' . $name) ?></span>
								<input type="range" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= $default ?>">
								<output><?= $default ?></output>
							</label>
						<?php endforeach ?>
						<button type="button" class="mtasaPreviewReset"><?= t('map.preview.reset') ?></button>
					</form>
				</div>
			</div>
		</section>
	<?php endif ?>
	<?php foreach ($sections as $section => $keys): ?>
		<section class="mtasaSection">
			<h2><?= t('section.' . $section) ?></h2>
			<?php
			$items = $sectionItems[$section] ?? $keys;
			/// Problems shows only what the script found (the owner's wish): a blank
			/// row is left out unless it has comments, and with nothing left one row
			/// says None.
			if ($section === 'problems') {
				$items = array_values(array_filter($items, fn($key) => mtasaFieldText($key, $map[$key]) !== '' || !empty($threadsByField[$key])));
			}
			?>
			<?php if (!$items): ?>
				<div class="mtasaField mtasaFieldNone">
					<div class="mtasaFieldLabel"><?= t('map.none') ?></div>
				</div>
			<?php endif ?>
			<?php foreach ($items as $item): ?>
				<?php if (is_array($item)): ?>
					<?php [$key, $copyLabel, $copyPair] = $item ?>
					<div class="mtasaField mtasaFieldCopy<?= $copyPair !== null ? ' mtasaHideWhenSet' : '' ?>" data-copy-of="<?= $key ?>"<?= $copyPair !== null ? ' data-pair="' . $copyPair . '"' . ($pairs[$copyPair]['known'] ? ' hidden' : '') : '' ?>>
						<div class="mtasaFieldLabel"><?= htmlspecialchars($copyLabel) ?></div>
						<div class="mtasaFieldValue<?= mtasaFields()[$key]['type'] === 'longtext' ? ' mtasaLongValue' : '' ?>">
							<span class="mtasaFieldShown"><?= $valueHtml($key) ?></span>
							<?= isset($guessPair[$key]) ? $useGuessButton($guessPair[$key]) : '' ?>
						</div>
						<div class="mtasaFieldComments"></div>
					</div>
					<?php continue ?>
				<?php endif ?>
				<?php $key = $item ?>
				<?php
				$field = mtasaFields()[$key];
				$editable = $isAdmin && $field['editable'];
				$threads = $threadsByField[$key] ?? [];
				$open = $openByField[$key] ?? 0;
				$choices = mtasaFieldChoices($key);
				?>
				<?php $hidePair = $hiddenWhenSet[$key] ?? null ?>
				<div class="mtasaField<?= $hidePair !== null ? ' mtasaHideWhenSet' : '' ?>" id="field-<?= $key ?>" data-field="<?= $key ?>"<?= $hidePair !== null ? ' data-pair="' . $hidePair . '"' . ($pairs[$hidePair]['known'] ? ' hidden' : '') : '' ?><?= isset($pairs[$key]) ? ' data-guess="' . htmlspecialchars($pairs[$key]['guessValue']) . '"' : '' ?><?= $key === 'original_name' ? ' data-not-found="' . htmlspecialchars(MTASA_NAME_NOT_FOUND) . '"' : '' ?>>
					<div class="mtasaFieldLabel"><?= $shortLabels[$key] ?? t('field.' . $key) ?></div>
					<div class="mtasaFieldValue<?= in_array($field['type'], ['longtext'], true) ? ' mtasaLongValue' : '' ?>">
						<?php if ($key === 'finished'): ?>
							<?php /* A checkbox, saved on change; only an admin can tick it. */ ?>
							<input type="checkbox" class="mtasaCheckField"<?= $map[$key] !== null && (int)$map[$key] === 1 ? ' checked' : '' ?><?= $editable ? '' : ' disabled' ?>
								aria-label="<?= htmlspecialchars(t('field.' . $key)) ?>">
						<?php elseif ($key === 'status' || $key === 'gamemode'): ?>
							<?php /* Coloured as the sheet coloured them (the stylesheet keys it off
							         data-choice); an admin gets the dropdown itself, no ✎. */ ?>
							<?php $current = (string)($map[$key] ?? '') ?>
							<?php if ($editable): ?>
								<select class="mtasaChoiceSelect" data-choice="<?= htmlspecialchars($current) ?>" data-saved="<?= htmlspecialchars($current) ?>" aria-label="<?= htmlspecialchars(t('field.' . $key)) ?>">
									<option value=""><?= t('map.unset') ?></option>
									<?php foreach ($current !== '' && !in_array($current, $choices, true) ? array_merge($choices, [$current]) : $choices as $choice): ?>
										<option value="<?= htmlspecialchars($choice) ?>" data-choice="<?= htmlspecialchars($choice) ?>"<?= $choice === $current ? ' selected' : '' ?>><?= htmlspecialchars($choice) ?></option>
									<?php endforeach ?>
								</select>
							<?php elseif ($current !== ''): ?>
								<span class="mtasaChoicePill" data-choice="<?= htmlspecialchars($current) ?>"><?= htmlspecialchars($current) ?></span>
							<?php endif ?>
						<?php else: ?>
						<span class="mtasaFieldShown"><?= $valueHtml($key) ?></span>
						<?= isset($guessPair[$key]) ? $mismatchButton($guessPair[$key]) . $useGuessButton($guessPair[$key]) : '' ?>
						<?php if ($editable): ?>
							<button type="button" class="mtasaEdit"
								data-type="<?= $field['type'] ?>"
								data-value="<?= htmlspecialchars($map[$key] === null ? '' : (string)$map[$key]) ?>"
								<?php if ($choices !== null): ?>data-choices="<?= htmlspecialchars(json_encode($choices)) ?>"<?php endif ?>
								aria-label="<?= htmlspecialchars(t('map.editField', ['field' => t('field.' . $key)])) ?>">✎&#xFE0E;</button>
						<?php endif ?>
						<?php endif ?>
					</div>
					<div class="mtasaFieldComments">
						<?php if ($threads || $signedIn): ?>
							<button type="button" class="mtasaBadge<?= $threads ? '' : ' mtasaBadgeEmpty' ?><?= $open > 0 ? ' mtasaBadgeOpen' : '' ?>"
								aria-expanded="false" aria-controls="threads-<?= $key ?>"
								aria-label="<?= htmlspecialchars(t($threads ? 'comments.badgeLabel' : 'comments.addLabel', ['field' => t('field.' . $key), 'count' => $open])) ?>">
								<?= $threads ? '💬&#xFE0E; <span class="mtasaBadgeCount">' . $open . '</span>' : '+' ?>
							</button>
						<?php endif ?>
					</div>
					<div class="mtasaThreads" id="threads-<?= $key ?>" data-field="<?= $key ?>" hidden>
						<?php
						$threadField = $key;
						$threadMapId = $id;
						$threadAccountId = $globalData['accountId'];
						$threadIsAdmin = $isAdmin;
						$threadSignedIn = $signedIn;
						require __MODULES__ . '/mtasa/views/partials/threads.php';
						?>
					</div>
				</div>
			<?php endforeach ?>
			<?php /* The set this map belongs to, under Duplicates (the owner's wish). */ ?>
			<?php if ($section === 'duplicates' && $globalData['group']): ?>
				<div class="mtasaGroup" id="copies">
					<div class="mtasaListScroll">
					<?php
					/// Which copies are still the script's guess: unmarked, with a guess, and
					/// not the set's original. The guess's similarity and confidence columns
					/// show only while there is one (the owner's wish).
					$guessed = [];
					foreach ($globalData['group'] as $member) {
						$guessed[(int)$member['id']] = $duplicates['root'][(int)$member['id']] !== (int)$member['id']
							&& $member['duplicate_of'] === null && $member['duplicate_of_closest'] !== null;
					}
					$anyGuessed = in_array(true, $guessed, true);
					?>
					<table class="mtasaGroupTable">
						<thead>
							<tr>
								<th><?= t('group.column.map') ?></th>
								<th><?= t('group.column.name') ?></th>
								<th><?= t('field.duplicate_of') ?></th>
								<?php if ($anyGuessed): ?>
									<th><?= t('field.duplicate_similarity') ?></th>
									<th><?= t('field.duplicate_confidence') ?></th>
								<?php endif ?>
								<th><?= t('group.column.differences') ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($globalData['group'] as $member): ?>
								<?php $memberId = (int)$member['id'] ?>
								<?php $isOriginal = $duplicates['root'][$memberId] === $memberId ?>
								<?php /* A copy the owner has not marked is the script's guess, its whole row
								         faded and italic as a guess is everywhere (the owner's wish). */ ?>
								<?php $rowClasses = array_filter([$memberId === $id ? 'mtasaGroupSelf' : null, $guessed[$memberId] ? 'mtasaGroupGuess' : null]) ?>
								<tr<?= $rowClasses ? ' class="' . implode(' ', $rowClasses) . '"' : '' ?><?= $memberId === $id ? ' aria-current="page"' : '' ?>>
									<td><a href="/mtasa/map?id=<?= $memberId ?>"><?= $memberId ?></a></td>
									<td><?= htmlspecialchars(mtasaMapName($member)) ?></td>
									<?php $parent = $member['duplicate_of'] ?? $member['duplicate_of_closest'] ?>
									<?php /* Duplicate of: the owner's mark, else the script's guess (its row
									         faded); a dash for the set's original. */ ?>
									<td><?= $isOriginal ? '-' : ($parent !== null ? '<a href="/mtasa/map?id=' . (int)$parent . '">' . (int)$parent . '</a>' : '') ?></td>
									<?php if ($guessed[$memberId]): ?>
										<td><?= $member['duplicate_similarity'] !== null ? (int)$member['duplicate_similarity'] . '%' : '' ?></td>
										<td><?= htmlspecialchars($member['duplicate_confidence'] ?? '') ?></td>
										<?php /* The script's notes, verbatim, saying what they are on hover. */ ?>
										<td<?= ($member['duplicate_differences'] ?? '') !== '' ? ' data-tooltip="' . htmlspecialchars(t('group.guessNote')) . '"' : '' ?>><?= htmlspecialchars($member['duplicate_differences'] ?? '') ?></td>
									<?php else: ?>
										<?php if ($anyGuessed): ?>
											<td></td>
											<td></td>
										<?php endif ?>
										<?php if ($member['own_differences'] !== null): ?>
											<?php /* A confirmed mark: the script's guess is ignored (the owner's
											         wish), and the differences are the site's, against the map marked. */ ?>
											<?php if (empty($member['own_differences']['unknown']) && $globalData['preview'] !== null): ?>
												<?php /* The points are compared in the browser on Increase detail, from both
												         maps' files on GitHub (the owner's choice), so the cell waits for it,
												         carrying what the stored analysis already says. */ ?>
												<td class="mtasaOwnDifferences" data-compare="<?= htmlspecialchars(json_encode(['this' => $memberId, 'that' => (int)$member['duplicate_of'], 'words' => $member['own_differences']['words'], 'rows' => $member['own_differences']['rows']])) ?>"><span class="mtasaAfterDetail"><?= t('map.afterDetail') ?></span></td>
											<?php else: ?>
												<?php [$tooltip, $tooltipTable] = empty($member['own_differences']['unknown']) ? mtasaCompareTooltip($member['own_differences']['rows'], $memberId, (int)$member['duplicate_of']) : ['', null] ?>
												<td class="mtasaOwnDifferences"<?= $tooltip !== '' ? ' data-tooltip="' . htmlspecialchars($tooltip) . '"' : '' ?><?= $tooltipTable !== null ? ' data-tooltip-table="' . htmlspecialchars($tooltipTable) . '"' : '' ?>><?= htmlspecialchars($member['own_differences']['summary']) ?></td>
											<?php endif ?>
										<?php else: ?>
											<td></td>
										<?php endif ?>
									<?php endif ?>
								</tr>
							<?php endforeach ?>
						</tbody>
					</table>
					</div>
				</div>
			<?php endif ?>
		</section>
		<?php /* Downloads, a section of its own under Settings (the owner's wish),
		         its rows label and value as every other section's, drawn on every
		         map and left empty where there is nothing: the map's folder on
		         GitHub, and its files as a zip, which js/preview.js offers once
		         Increase detail has fetched them (the owner's choice). */ ?>
		<?php if ($section === 'settings'): ?>
			<?php $hasFolder = $globalData['hasFolder'] && (string)($map['folder'] ?? '') !== '' ?>
			<section class="mtasaSection">
				<h2><?= t('map.downloads') ?></h2>
				<div class="mtasaField">
					<div class="mtasaFieldLabel"><?= t('map.downloads.github') ?></div>
					<div class="mtasaFieldValue">
						<span class="mtasaFieldShown"><?php if ($hasFolder): ?><a href="<?= htmlspecialchars(MTASA_GITHUB_TREE . $map['folder']) ?>" target="_blank" rel="noopener"><?= MTASA_GITHUB_REPO ?></a><?php endif ?></span>
					</div>
					<div class="mtasaFieldComments"></div>
				</div>
				<div class="mtasaField" id="mtasaDownloadFiles">
					<div class="mtasaFieldLabel"><?= t('map.downloads.files') ?></div>
					<div class="mtasaFieldValue">
						<span class="mtasaFieldShown"><?php if ($hasFolder && $globalData['preview'] !== null): ?><span class="mtasaAfterDetail"><?= t('map.afterDetail') ?></span><?php endif ?></span>
					</div>
					<div class="mtasaFieldComments"></div>
				</div>
			</section>
		<?php endif ?>
	<?php endforeach ?>

	<section class="mtasaSection mtasaGeneral" id="comments">
		<h2><?= t('comments.generalHeading') ?></h2>
		<div class="mtasaThreads mtasaThreadsGeneral" data-field="">
			<?php
			$threads = $threadsByField[''] ?? [];
			$threadField = null;
			$threadMapId = $id;
			$threadAccountId = $globalData['accountId'];
			$threadIsAdmin = $isAdmin;
			$threadSignedIn = $signedIn;
			require __MODULES__ . '/mtasa/views/partials/threads.php';
			?>
		</div>
	</section>
</div>

<script src="<?= asset('/modules/mtasa/js/map.js') ?>"></script>
<script src="<?= asset('/modules/mtasa/js/analysis.js') ?>"></script>
<script src="<?= asset('/modules/mtasa/js/zip.js') ?>"></script>
<script src="<?= asset('/modules/mtasa/js/preview.js') ?>"></script>

<?php require(__MODULES__ . '/mtasa/views/partials/foot.php') ?>
