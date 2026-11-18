<?php require(__MODULES__ . '/games/views/partials/head.php') ?>

<?php
$loaded = $globalData['loaded'];
$sheetLink = '<a href="' . htmlspecialchars(GAMES_AOE2_SHEET) . '">' . htmlspecialchars(t('aoe2.sourceLink')) . '</a>';
?>

<h1><?= t('aoe2.heading') ?></h1>

<?php if ($loaded === null): ?>
	<p class="formError"><?= t('aoe2.unavailable', ['link' => $sheetLink]) ?></p>
<?php else: ?>
	<?php $fetchedAt = htmlspecialchars(date('Y-m-d H:i', $loaded['fetchedAt'])) ?>
	<p class="aoeSource"><?= t('aoe2.source', ['link' => $sheetLink, 'time' => $fetchedAt]) ?></p>
	<?php if ($loaded['stale']): ?>
		<p class="formError"><?= t('aoe2.stale', ['time' => $fetchedAt]) ?></p>
	<?php endif ?>

	<form method="post" action="/games/aoe2" id="aoeFilters" class="aoeFilters">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
		<?php
		$filterOption = function ($filterKey, $filter, $value, $class = 'aoeFilterOption') use ($globalData) {
			$title = $filter['titles'][$value] ?? null;
			?>
			<label class="<?= $class ?>"<?= $title !== null ? ' title="' . htmlspecialchars($title) . '"' : '' ?>>
				<input type="checkbox" name="<?= htmlspecialchars($filterKey) ?>[]" value="<?= htmlspecialchars($value) ?>"<?= in_array((string)$value, $globalData['selection'][$filterKey], true) ? ' checked' : '' ?>>
				<?= htmlspecialchars($filter['options'][$value]) ?>
			</label>
			<?php
		};

		/// A filter that groups another's options (the game, for the DLC) is drawn
		/// inside it, one box at the head of each of its rows, not on its own.
		$heads = array_filter(array_column($globalData['filters'], 'groupFilter'));
		?>
		<?php foreach ($globalData['filters'] as $filterKey => $filter): ?>
			<?php if (in_array($filterKey, $heads, true)) continue ?>
			<fieldset class="aoeFilter">
				<legend><?= t($filter['labelKey']) ?></legend>
				<?php /* Sent with every submission, so ticking nothing reads as none rather than as untouched. */ ?>
				<input type="hidden" name="<?= htmlspecialchars($filterKey) ?>[]" value="">
				<?php if (isset($filter['groupFilter'])): ?>
					<input type="hidden" name="<?= htmlspecialchars($filter['groupFilter']) ?>[]" value="">
				<?php endif ?>
				<?php if (isset($filter['groups'])): ?>
					<?php foreach ($filter['groups'] as $group => $values): ?>
						<?php $off = isset($filter['groupFilter']) && !in_array((string)$group, $globalData['selection'][$filter['groupFilter']], true) ?>
						<div class="aoeFilterGroup<?= $off ? ' aoeFilterGroupOff' : '' ?>">
							<?php if (isset($filter['groupFilter'])): ?>
								<?php $filterOption($filter['groupFilter'], $globalData['filters'][$filter['groupFilter']], $group, 'aoeFilterOption aoeFilterGroupName') ?>
							<?php else: ?>
								<span class="aoeFilterGroupName"><?= htmlspecialchars($group) ?></span>
							<?php endif ?>
							<span class="aoeFilterGroupOptions">
								<?php foreach ($values as $value): ?>
									<?php $filterOption($filterKey, $filter, $value) ?>
								<?php endforeach ?>
							</span>
						</div>
					<?php endforeach ?>
				<?php else: ?>
					<?php foreach (array_keys($filter['options']) as $value): ?>
						<?php $filterOption($filterKey, $filter, $value) ?>
					<?php endforeach ?>
				<?php endif ?>
			</fieldset>
		<?php endforeach ?>
		<label class="aoeSwitch">
			<input type="checkbox" name="scale" value="1"<?= $globalData['scale'] ? ' checked' : '' ?>>
			<span class="aoeSwitchTrack" aria-hidden="true"></span>
			<?= t('aoe2.toScale') ?>
		</label>
		<button type="submit" class="aoeApply"><?= t('aoe2.apply') ?></button>
	</form>

	<?php
	$total = count($globalData['rows']);
	$done = count(array_filter($globalData['rows'], fn($row) => isset($globalData['checked'][$row['key']])));
	?>
	<p class="aoeProgress"><span id="aoeProgress"><?= t('aoe2.progress', ['done' => $done, 'total' => $total]) ?></span> <span id="aoeStatus" class="formError" role="status"></span></p>
	<?php if ($globalData['scale'] && $globalData['rows']): ?>
		<?php /* Fold or unfold every year, or every stretch, at once (the owner's wish):
		         each button names the fold list it changes and which way. */ ?>
		<div class="aoeFoldAll">
			<button type="button" data-list="expanded" data-on="0"><?= t('aoe2.foldAllYears') ?></button>
			<button type="button" data-list="expanded" data-on="1"><?= t('aoe2.unfoldAllYears') ?></button>
			<button type="button" data-list="collapsed" data-on="1"><?= t('aoe2.foldAllStretches') ?></button>
			<button type="button" data-list="collapsed" data-on="0"><?= t('aoe2.unfoldAllStretches') ?></button>
		</div>
	<?php endif ?>

	<?php if (!$globalData['rows']): ?>
		<p><?= t('aoe2.empty') ?></p>
	<?php else: ?>
		<ol class="aoeTimeline"<?= $globalData['scale'] ? ' data-scale="1"' : '' ?> data-present-year="<?= (int)date('Y') ?>" style="--laneCount: <?= (int)$globalData['lanes']['width'] ?>; --rowAlpha: <?= round(GAMES_AOE2_ROW_ALPHA * 100) ?>%">
			<?php /* The sheet's column headings, over the columns they name. */ ?>
			<li class="aoeHead" aria-hidden="true">
				<span class="aoeYear"><?= t('aoe2.column.year') ?></span>
				<span class="aoeLanes"></span>
				<span class="aoeText">
					<span><?= t('aoe2.column.scenario') ?></span>
					<span><?= t('aoe2.column.civ') ?></span>
					<span><?= t('aoe2.column.campaign') ?></span>
					<span><?= t('aoe2.column.game') ?></span>
					<span><?= t('aoe2.column.pack') ?></span>
					<?php foreach (array_keys(GAMES_AOE2_ENTRY_FIELDS) as $field): ?>
						<span><?= t('aoe2.column.' . $field) ?></span>
					<?php endforeach ?>
				</span>
			</li>
			<?php
			$previousYear = null;
			$lastStep = count($globalData['steps']) - 1;
			$lineStyle = fn($line) => '--lane: ' . (int)$line['lane'] . '; --colour: ' . htmlspecialchars($line['colour']) . '; --edge: ' . gamesAoe2EdgeColour($line['colour']);
			?>
			<?php foreach ($globalData['steps'] as $step => $members): ?>
				<?php
				/// To scale, a year of several scenarios is one row, named by its first.
				$group = count($members) > 1 ? $globalData['rows'][$members[0]]['key'] : null;
				$expanded = $group !== null && isset($globalData['expanded'][$group]);
				?>
				<?php if ($group !== null): ?>
					<?php
					$folded = $globalData['lanes']['steps'][$step];
					$head = $globalData['rows'][$members[0]];
					/// The rows of a year share its start, so one flag holds for them all.
					$outOfPlace = $globalData['outOfPlace'][$members[0]] ?? false;
					$allDone = !array_diff_key(array_flip(array_map(fn($i) => $globalData['rows'][$i]['key'], $members)), $globalData['checked']);
					?>
					<?php /* Folded, a dot per scenario, zigzagging across the lanes in one lump;
					         unfolded, a header over its scenarios, with only the lines that
					         pass through. */ ?>
					<li class="aoeGroup aoeRowLight<?= $allDone ? ' aoeGroupDone' : '' ?>" data-group="<?= htmlspecialchars($group) ?>" data-year="<?= gamesAoe2YearNumber($head['year']) ?? '' ?>"<?= $expanded ? ' data-expanded' : '' ?>>
						<span class="aoeYear<?= $outOfPlace ? ' aoeYearOutOfPlace' : '' ?>"><?= htmlspecialchars($head['year']) ?></span>
						<span class="aoeLanes" aria-hidden="true">
							<?php foreach ($folded['lines'] as $line): ?>
								<i class="aoeLine aoeFoldedOnly<?= $line['start'] ? ' aoeLineStart' : '' ?><?= $line['end'] ? ' aoeLineEnd' : '' ?>" style="<?= $lineStyle($line) ?>"></i>
							<?php endforeach ?>
							<?php foreach ($folded['through'] as $line): ?>
								<i class="aoeLine aoeExpandedOnly" style="<?= $lineStyle($line) ?>"></i>
							<?php endforeach ?>
							<?php foreach ($folded['dots'] as $dot): ?>
								<i class="aoeDot aoeFoldedOnly<?= $dot['lane'] % 2 ? ' aoeDotUp' : '' ?><?= isset($globalData['checked'][$dot['key']]) ? ' aoeDotDone' : '' ?>" style="--lane: <?= (int)$dot['lane'] ?>; --colour: <?= htmlspecialchars($dot['colour']) ?>" data-key="<?= htmlspecialchars($dot['key']) ?>" title="<?= htmlspecialchars($dot['campaign']) ?>"></i>
							<?php endforeach ?>
						</span>
						<span class="aoeText">
							<button type="button" class="aoeGroupToggle" aria-expanded="<?= $expanded ? 'true' : 'false' ?>"><?= t('aoe2.yearScenarios', ['count' => count($members)]) ?></button>
						</span>
					</li>
					<?php $previousYear = $head['year'] ?>
				<?php endif ?>
				<?php foreach ($members as $i): ?>
					<?php
					$row = $globalData['rows'][$i];
					$layout = $globalData['lanes']['rows'][$i];
					$campaignText = $row['number'] === null
						? $row['campaign']
						: t('aoe2.campaignNumber', ['campaign' => $row['campaign'], 'number' => $row['number']]);
					/// Folded, its rows are hidden until found, so the browser's find still
					/// reaches them (aoe2.js unfolds the year when it does).
					$groupAttributes = $group === null ? '' : ' data-group="' . htmlspecialchars($group) . '"' . ($expanded ? '' : ' hidden="until-found"');
					?>
					<li class="aoeRow<?= $row['ink'] === 'light' ? ' aoeRowLight' : '' ?>" data-key="<?= htmlspecialchars($row['key']) ?>" data-game="<?= htmlspecialchars($row['game']) ?>" data-year="<?= gamesAoe2YearNumber($row['year']) ?? '' ?>" style="--rowColour: <?= htmlspecialchars($row['colour']) ?>"<?= $groupAttributes ?>>
						<?php $outOfPlace = $globalData['outOfPlace'][$i] ?? false ?>
						<?php /* Out of place: shown even when the row above has the same year. */ ?>
						<span class="aoeYear<?= $outOfPlace ? ' aoeYearOutOfPlace' : '' ?>"><?= $row['year'] !== $previousYear || $outOfPlace ? htmlspecialchars($row['year']) : '' ?></span>
						<span class="aoeLanes" aria-hidden="true">
							<?php foreach ($layout['lines'] as $line): ?>
								<i class="aoeLine<?= $line['start'] ? ' aoeLineStart' : '' ?><?= $line['end'] ? ' aoeLineEnd' : '' ?>" style="<?= $lineStyle($line) ?>"></i>
							<?php endforeach ?>
							<i class="aoeDot" style="--lane: <?= (int)$layout['dot'] ?>; --colour: <?= htmlspecialchars($layout['colour']) ?>" title="<?= htmlspecialchars($row['campaign']) ?>"></i>
						</span>
						<span class="aoeText">
							<label class="aoeLabel">
								<input type="checkbox" class="aoeCheck" value="<?= htmlspecialchars($row['key']) ?>"<?= isset($globalData['checked'][$row['key']]) ? ' checked' : '' ?>>
								<span class="aoeName"><?= htmlspecialchars($row['name']) ?></span>
							</label>
							<span class="aoeCiv"><?= htmlspecialchars($row['civ']) ?></span>
							<span class="aoeCampaign"><?= htmlspecialchars($campaignText) ?></span>
							<span class="aoeGame" title="<?= htmlspecialchars(GAMES_AOE2_GAME_LABELS[$row['game']] ?? $row['game']) ?>"><?= htmlspecialchars($row['game']) ?></span>
							<span class="aoePack"><?= htmlspecialchars($row['pack']) ?></span>
							<?php /* What the account wrote about it, each a button that aoe2.js swaps for a box to edit it in. */ ?>
							<?php foreach (array_keys(GAMES_AOE2_ENTRY_FIELDS) as $field): ?>
								<?php
								$text = gamesAoe2EntryText($field, $globalData['entries'][$row['key']][$field] ?? null);
								$label = t($text === '' ? 'aoe2.fieldLabel' : 'aoe2.fieldLabelValue', ['column' => t('aoe2.column.' . $field), 'scenario' => $row['name'], 'value' => $text]);
								?>
								<?php if ($field === 'youtube'): ?>
									<?php /* A link that opens the video, and a pencil that edits it, holding the link as its value. */ ?>
									<span class="aoeField" data-field="youtube"><?php if ($text !== ''): ?><a class="aoeFieldLink" href="<?= htmlspecialchars($text) ?>" target="_blank" rel="noopener" data-tooltip="<?= htmlspecialchars(t('aoe2.watchYoutube')) ?>" aria-label="<?= htmlspecialchars(t('aoe2.watchYoutubeFor', ['scenario' => $row['name']])) ?>">▶</a><?php endif ?><button type="button" class="aoeFieldText" data-scenario="<?= htmlspecialchars($row['name']) ?>" data-value="<?= htmlspecialchars($text) ?>" aria-label="<?= htmlspecialchars($label) ?>"><?= $text === '' ? '' : '✎' ?></button></span>
									<?php continue ?>
								<?php endif ?>
								<?php /* A descriptor or note is cut off when too long, so the whole of it is in the hover box. */ ?>
								<span class="aoeField" data-field="<?= $field ?>"><button type="button" class="aoeFieldText" data-scenario="<?= htmlspecialchars($row['name']) ?>"<?= $field === 'rating' ? '' : ' data-tooltip="' . htmlspecialchars($text) . '"' ?> aria-label="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($text) ?></button></span>
							<?php endforeach ?>
						</span>
					</li>
					<?php $previousYear = $row['year'] ?>
				<?php endforeach ?>
				<?php
				/// The stretch to the present comes before the undated scenarios, or after
				/// the last step when there are none and that step is dated.
				$trailing = $step === $lastStep && $globalData['presentStep'] === null && gamesAoe2YearNumber($globalData['rows'][$members[0]]['year']) !== null;
				?>
				<?php if ($step < $lastStep || $trailing): ?>
					<?php /* The room the to-scale layout opens before the next step: none
					         unless it sets --gap, with the lines that run on through it,
					         and the year axis over it, a button that folds it away. The
					         stretch is named by the scenario after it. */ ?>
					<?php
					$next = $trailing ? GAMES_AOE2_PRESENT_KEY : $globalData['rows'][$globalData['steps'][$step + 1][0]]['key'];
					$present = $trailing || $globalData['presentStep'] === $step + 1;
					?>
					<li class="aoeGap" role="none" data-next="<?= htmlspecialchars($next) ?>"<?= $present ? ' data-present' : '' ?><?= isset($globalData['collapsed'][$next]) ? ' data-collapsed' : '' ?>>
						<span class="aoeLanes" aria-hidden="true">
							<?php foreach ($layout['lines'] as $line): ?>
								<?php if (!$line['end']): ?>
									<i class="aoeLine" style="<?= $lineStyle($line) ?>"></i>
								<?php endif ?>
							<?php endforeach ?>
						</span>
						<button type="button" class="aoeAxis" disabled aria-hidden="true"><svg class="aoeKink" viewBox="0 0 12 16" aria-hidden="true"><polyline points="6,0 2,5 10,11 6,16"/></svg></button>
					</li>
				<?php endif ?>
			<?php endforeach ?>
		</ol>
	<?php endif ?>

	<script src="<?= asset('/modules/games/js/aoe2.js') ?>"></script>
<?php endif ?>

<?php require(__MODULES__ . '/games/views/partials/foot.php') ?>
