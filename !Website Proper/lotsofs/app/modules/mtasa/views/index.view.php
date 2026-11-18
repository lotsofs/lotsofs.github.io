<?php require(__MODULES__ . '/mtasa/views/partials/head.php') ?>
<?php
$list = $globalData['list'];
$chosen = $globalData['chosen'];
$duplicates = $globalData['duplicates'];

/// A header that sorts by its column, flipping the direction when it already does.
$header = function ($key) use ($list, $chosen) {
	$current = $list['sort'] === $key;
	$dir = $current && $list['dir'] === 'asc' ? 'desc' : 'asc';
	$mark = $current ? ($list['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
	$sorted = $current ? ($list['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';

	return '<th aria-sort="' . $sorted . '"><a href="' . htmlspecialchars(mtasaListUrl($chosen, $key, $dir, 1, $list['per'])) . '">' . t('list.column.' . $key) . $mark . '</a></th>';
};

$isAdmin = $globalData['isAdmin'];

/// An admin edits a cell by clicking it (js/list.js): which field it saves to,
/// how, and what it holds now.
$edit = function ($field, $type, $value) use ($isAdmin) {
	if (!$isAdmin) {
		return '';
	}

	return ' data-edit-field="' . $field . '" data-type="' . $type . '" data-value="' . htmlspecialchars($value) . '"';
};

$optionText = function ($name, $value) {
	if ($value === 'none') {
		return t('filter.none');
	}
	if ($name === 'finished') {
		return t($value === '1' ? 'value.yes' : 'value.no');
	}
	if ($name === 'duplicates' || $name === 'comments') {
		return t('filter.' . $name . '.' . $value);
	}

	return $value;
};
?>

<?php if ($globalData['mapCount'] > 0): ?>
	<?php /* Filters are remembered in this browser (the owner's wish), before the
	         table is parsed so the bare list never shows first: the bare list goes
	         to the last filtered one, any other visit records its filters (none
	         forgets them), and Clear forgets them. Search and the page size count;
	         sort and page do not, and Clear keeps the page size. */ ?>
	<script>
	(() => {
		const key = "mtasaListFilters";
		/// The page size is remembered with them, the pager's Per page.
		const names = <?= json_encode(array_merge(array_keys($globalData['filters']), ['q', 'per'])) ?>;
		try {
			if (location.search === "") {
				const saved = localStorage.getItem(key);
				if (saved) {
					location.replace("/mtasa?" + saved);
				}
			}
			else {
				const kept = new URLSearchParams();
				for (const [name, value] of new URLSearchParams(location.search)) {
					/// The pager's form sends the default size too; it is not worth remembering.
					const isDefault = name === "per" && value === "<?= MTASA_PAGE_SIZE ?>";
					/// An empty name[] is kept: it is a filter with a default, cleared to every map.
					if ((value !== "" || name.endsWith("[]")) && !isDefault && names.includes(name.replace(/\[\]$/, ""))) {
						kept.append(name, value);
					}
				}
				if (kept.toString() === "") {
					localStorage.removeItem(key);
				}
				else {
					localStorage.setItem(key, kept.toString());
				}
			}
		}
		catch (error) {
			/* No storage (a private window, blocked site data): nothing is remembered. */
		}
		document.addEventListener("click", event => {
			if (event.target.closest("[data-clear-filters]")) {
				try {
					localStorage.removeItem(key);
				}
				catch (error) {
					/* Nothing was stored, then. */
				}
			}
		});
	})();
	</script>
<?php endif ?>

<h1><?= t('maps.heading') ?></h1>

<?php if ($globalData['imported']): ?>
	<p class="mtasaNotice" id="mtasaImported"><?= htmlspecialchars(t('import.done', $globalData['imported'])) ?></p>
<?php endif ?>
<?php if ($globalData['reloaded']): ?>
	<p class="mtasaNotice" id="mtasaReloaded"><?= htmlspecialchars(t('reload.done', $globalData['reloaded'])) ?></p>
<?php endif ?>
<?php if ($globalData['importError']): ?>
	<p class="formError" id="mtasaImportError"><?= htmlspecialchars($globalData['importError']) ?></p>
<?php endif ?>

<?php if ($globalData['mapCount'] === 0): ?>
	<p><?= t('maps.empty') ?></p>
	<?php if ($globalData['isAdmin']): ?>
		<form method="post" action="/mtasa/import" id="mtasaImportForm">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
			<p><?= t('import.explain') ?></p>
			<button type="submit"><?= t('import.submit') ?></button>
		</form>
	<?php endif ?>
<?php else: ?>

	<form method="get" action="/mtasa" class="mtasaFilters" id="mtasaFilters">
		<label class="mtasaSearch">
			<span><?= t('filter.search') ?></span>
			<input type="text" name="q" value="<?= htmlspecialchars($chosen['q'] ?? '') ?>">
		</label>

		<?php foreach ($globalData['filters'] as $name => $filter): ?>
			<?php if ($filter['kind'] === 'values'): ?>
				<?php $default = mtasaFilterDefault($filter) ?>
				<?php $narrowed = isset($chosen[$name]) && $chosen[$name] !== [] ?>
				<?php $picked = $chosen[$name] ?? $default ?? [] ?>
				<details class="mtasaFilter"<?= isset($chosen[$name]) ? ' open' : '' ?>>
					<summary><?= t('filter.' . $name) ?><?= $narrowed ? ' (' . count($picked) . ')' : '' ?></summary>
					<?php if ($default !== null): ?>
						<?php /* Names the filter with nothing ticked, so unticking every box means every map, not the default. */ ?>
						<input type="hidden" name="<?= $name ?>[]" value="">
					<?php endif ?>
					<?php foreach (array_merge($filter['options'], empty($filter['binary']) ? ['none'] : []) as $value): ?>
						<label class="mtasaFilterOption">
							<input type="checkbox" name="<?= $name ?>[]" value="<?= htmlspecialchars($value) ?>"<?= in_array($value, $picked, true) ? ' checked' : '' ?>>
							<?= htmlspecialchars($optionText($name, $value)) ?>
						</label>
					<?php endforeach ?>
				</details>
			<?php else: ?>
				<label class="mtasaFilterSelect">
					<span><?= t('filter.' . $name) ?></span>
					<select name="<?= $name ?>">
						<option value=""><?= t('filter.all') ?></option>
						<?php foreach ($filter['options'] as $value): ?>
							<option value="<?= $value ?>"<?= ($chosen[$name] ?? null) === $value ? ' selected' : '' ?>><?= htmlspecialchars($optionText($name, $value)) ?></option>
						<?php endforeach ?>
					</select>
				</label>
			<?php endif ?>
		<?php endforeach ?>

		<?php if ($list['sort'] !== 'id' || $list['dir'] !== 'asc'): ?>
			<input type="hidden" name="sort" value="<?= htmlspecialchars($list['sort']) ?>">
			<input type="hidden" name="dir" value="<?= htmlspecialchars($list['dir']) ?>">
		<?php endif ?>
		<?php if ($list['per'] !== MTASA_PAGE_SIZE): ?>
			<input type="hidden" name="per" value="<?= $list['per'] ?>">
		<?php endif ?>

		<div class="mtasaFilterActions">
			<button type="submit"><?= t('filter.apply') ?></button>
			<a href="<?= htmlspecialchars(mtasaListUrl([], 'id', 'asc', 1, $list['per'])) ?>" data-clear-filters><?= t('filter.clear') ?></a>
		</div>
	</form>

	<?php
	/// The pager, above and below the table (the owner's layout): « First,
	/// ‹ Previous, a box to type a page into, Next ›, Last », and how many maps a
	/// page shows. A GET form, so the box works with Enter and no script; its
	/// hidden fields carry the filters, search and sort. A link with nowhere to
	/// go stays in place, greyed. Shown whenever there are maps, so the page
	/// size can be changed on a single page too.
	$pager = function () use ($list, $chosen) {
		if ($list['total'] === 0) {
			return '';
		}
		$link = function ($page, $text, $rel) use ($list, $chosen) {
			if ($page < 1 || $page > $list['pages'] || $page === $list['page']) {
				return '<span class="mtasaPagerOff" aria-disabled="true">' . $text . '</span>';
			}
			return '<a href="' . htmlspecialchars(mtasaListUrl($chosen, $list['sort'], $list['dir'], $page, $list['per'])) . '"' . ($rel ? ' rel="' . $rel . '"' : '') . '>' . $text . '</a>';
		};
		$hidden = '';
		foreach ($chosen as $name => $value) {
			foreach ($value === [] ? [''] : (array)$value as $one) {
				$hidden .= '<input type="hidden" name="' . htmlspecialchars($name) . (is_array($value) ? '[]' : '') . '" value="' . htmlspecialchars($one) . '">';
			}
		}
		if ($list['sort'] !== 'id' || $list['dir'] !== 'asc') {
			$hidden .= '<input type="hidden" name="sort" value="' . htmlspecialchars($list['sort']) . '"><input type="hidden" name="dir" value="' . htmlspecialchars($list['dir']) . '">';
		}
		$sizes = '';
		$offered = array_unique(array_merge(MTASA_PAGE_SIZES, [$list['per']]));
		sort($offered);
		foreach ($offered as $size) {
			$sizes .= '<option value="' . $size . '"' . ($size === $list['per'] ? ' selected' : '') . '>' . $size . '</option>';
		}

		return '<form method="get" action="/mtasa" class="mtasaPager" aria-label="' . t('list.pages') . '">' . $hidden
			. $link(1, t('list.first'), null)
			. $link($list['page'] - 1, t('list.previous'), 'prev')
			. '<label class="mtasaPagerBox">' . t('list.pageBox') . ' <input type="number" name="page" min="1" max="' . $list['pages'] . '" value="' . $list['page'] . '" aria-label="' . htmlspecialchars(t('list.pageOf', ['page' => $list['page'], 'pages' => $list['pages']])) . '"> ' . htmlspecialchars(t('list.of', ['pages' => $list['pages']])) . '</label>'
			. '<button type="submit" class="mtasaPagerGo">' . t('list.go') . '</button>'
			. $link($list['page'] + 1, t('list.next'), 'next')
			. $link($list['pages'], t('list.last'), null)
			. '<label class="mtasaPagerSize">' . t('list.perPage') . ' <select name="per" data-current="' . $list['per'] . '">' . $sizes . '</select></label>'
			. '</form>';
	};
	?>

	<p class="mtasaStatus" id="mtasaStatus" role="status"></p>
	<p class="mtasaCount" id="mtasaCount"><?= htmlspecialchars(t('list.count', ['count' => $list['total']])) ?></p>
	<?= $pager() ?>

	<?php if ($list['rows']): ?>
		<div class="mtasaListScroll">
			<table class="mtasaList" id="mtasaList"<?= $isAdmin ? ' data-choices="' . htmlspecialchars(json_encode(['status' => MTASA_STATUSES, 'gamemode' => MTASA_GAMEMODES])) . '"' : '' ?>>
				<thead>
					<tr>
						<?php foreach (array_keys(mtasaListColumns()) as $key): ?>
							<?= $header($key) ?>
						<?php endforeach ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($list['rows'] as $row): ?>
						<?php $id = (int)$row['id'] ?>
						<tr data-map-id="<?= $id ?>">
							<td class="mtasaNumber mtasaIdCell"><a href="/mtasa/map?id=<?= $id ?>"><?= $id ?></a></td>
							<?php /* The name opens the map; its hover box says where the name comes
							         from, and one only the script found is a guess, faded. */ ?>
							<?php $name = htmlspecialchars(mtasaMapName($row)) ?>
							<td class="mtasaName" data-tooltip="<?= htmlspecialchars(mtasaNameSourceText($row)) ?>"><a href="/mtasa/map?id=<?= $id ?>"><?= mtasaNameSource($row) === 'title' ? '<span class="mtasaGuess">' . $name . '</span>' : $name ?></a></td>
							<td class="mtasaAuthor" data-tooltip="<?= htmlspecialchars($row['author'] ?? '') ?>"><?= htmlspecialchars($row['author'] ?? '') ?></td>
							<td<?= $edit('status', 'choice', (string)($row['status'] ?? '')) ?>>
								<?php if (($row['status'] ?? '') !== ''): ?>
									<span class="mtasaChoicePill" data-choice="<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span>
								<?php endif ?>
							</td>
							<?php $gamemode = (string)($row['gamemode'] ?? '') ?>
							<td class="mtasaGamemode" data-guess="<?= htmlspecialchars((string)($row['category_guess'] ?? '')) ?>"<?= $edit('gamemode', 'choice', $gamemode) ?>>
								<?php if ($gamemode !== ''): ?>
									<span class="mtasaChoicePill" data-choice="<?= htmlspecialchars($gamemode) ?>"><?= htmlspecialchars($gamemode) ?></span>
								<?php elseif (($row['category_guess'] ?? '') !== ''): ?>
									<span class="mtasaGuess" data-tooltip="<?= htmlspecialchars(t('list.guess')) ?>"><?= htmlspecialchars($row['category_guess']) ?></span>
								<?php endif ?>
							</td>
							<td class="mtasaNumber"><?= $row['checkpoints'] ?? '' ?></td>
							<?php $finished = $row['finished'] !== null && (int)$row['finished'] === 1 ?>
							<td class="mtasaFinished">
								<?php if ($isAdmin): ?>
									<input type="checkbox" class="mtasaListCheck"<?= $finished ? ' checked' : '' ?> aria-label="<?= htmlspecialchars(t('list.finished')) ?>">
								<?php elseif ($finished): ?>
									<span role="img" aria-label="<?= htmlspecialchars(t('list.finished')) ?>">✓&#xFE0E;</span>
								<?php endif ?>
							</td>
							<?php $video = (string)($row['video'] ?? '') ?>
							<td class="mtasaVideo">
								<?php if ($video !== ''): ?>
									<a href="<?= htmlspecialchars($video) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= htmlspecialchars(t('list.video')) ?>" data-tooltip="<?= htmlspecialchars($video) ?>">▶&#xFE0E;</a>
								<?php endif ?>
							</td>
							<td><?= htmlspecialchars($row['errors'] ?? '') ?></td>
							<?php $mismatches = mtasaGuessMismatches($row) ?>
							<?php $warnings = (string)($row['warnings'] ?? '') ?>
							<td class="mtasaWarnings"<?= $warnings !== '' ? ' data-tooltip="' . htmlspecialchars($warnings) . '"' : '' ?>>
								<?php if ($mismatches): ?>
									<?php $said = htmlspecialchars(implode("\n", array_map(fn($owner, $value) => t('list.mismatch.' . $owner, ['value' => $value]), array_keys($mismatches), $mismatches))) ?>
									<span class="mtasaListMismatch" role="img" aria-label="<?= $said ?>" data-tooltip="<?= $said ?>">!</span>
								<?php endif ?>
								<?= htmlspecialchars(preg_replace('/\s+/', ' ', $warnings)) ?>
							</td>
							<td>
								<?php if ($row['original'] !== null): ?>
									<a href="/mtasa/map?id=<?= (int)$row['original'] ?>" data-tooltip="<?= htmlspecialchars(mtasaDuplicateLabel($duplicates, $id)) ?>"><?= (int)$row['original'] ?></a>
								<?php endif ?>
							</td>
							<td class="mtasaNumber">
								<?php if ($row['open_threads'] > 0): ?>
									<a href="/mtasa/map?id=<?= $id ?>#comments" class="mtasaListComments" data-tooltip="<?= htmlspecialchars(t('list.openThreads', ['count' => $row['open_threads']])) ?>">💬&#xFE0E; <?= $row['open_threads'] ?></a>
								<?php endif ?>
							</td>
							<td class="mtasaDescription" data-tooltip="<?= htmlspecialchars($row['custom_description'] ?? '') ?>"<?= $edit('custom_description', 'longtext', (string)($row['custom_description'] ?? '')) ?>><?= htmlspecialchars(preg_replace('/\s+/', ' ', (string)($row['custom_description'] ?? ''))) ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		</div>
		<?= $pager() ?>
	<?php endif ?>
<?php endif ?>

<?php if ($globalData['mapCount'] > 0): ?>
	<?php /* Per page reloads the list at once, on the page that holds the first map
	         now showing, so the reader stays where they were. */ ?>
	<script>
	document.addEventListener("change", event => {
		const select = event.target.closest(".mtasaPager select[name=per]");
		if (!select) {
			return;
		}
		const form = select.form;
		const page = Number(form.elements.page.value) || 1;
		form.elements.page.value = Math.floor((page - 1) * Number(select.dataset.current) / Number(select.value)) + 1;
		form.submit();
	});
	</script>
<?php endif ?>

<?php if ($isAdmin && $globalData['mapCount'] > 0): ?>
	<?php /* After a rerun of tools/mtasa: the script's columns again from classics.csv. */ ?>
	<form method="post" action="/mtasa/import" class="mtasaReload" id="mtasaReloadForm">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
		<input type="hidden" name="action" value="reload">
		<p><?= t('reload.explain') ?></p>
		<button type="submit"><?= t('reload.submit') ?></button>
	</form>
	<script src="<?= asset('/modules/mtasa/js/list.js') ?>"></script>
<?php endif ?>

<?php require(__MODULES__ . '/mtasa/views/partials/foot.php') ?>
