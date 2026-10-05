<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('poiImport.heading') ?></h1>

<?php $loaded = $globalData['loadedGameMap'] ?>

<?php if (!$loaded): ?>
	<p><?= t('poiImport.noMap') ?> <a href="/hideandseek/game-maps"><?= t('poiImport.pickMap') ?></a></p>
<?php else: ?>
	<p><?= htmlspecialchars(t('gameMap.loaded', ['name' => $loaded['name']])) ?></p>

	<?php if ($globalData['formError'] !== ''): ?>
		<p class="formError"><?= htmlspecialchars($globalData['formError']) ?></p>
	<?php endif ?>

	<?php if ($globalData['importResult']): ?>
		<?php $result = $globalData['importResult'] ?>
		<p class="hnsImportResult">
			<?php if ($result['category'] !== null): ?>
				<?= htmlspecialchars(t('poiImport.result', ['count' => $result['imported'], 'category' => $result['category']])) ?>
			<?php else: ?>
				<?= htmlspecialchars(t('poiImport.resultMany', ['count' => $result['imported'], 'categories' => $result['categories']])) ?>
			<?php endif ?>
			<?php if ($result['skipped'] > 0): ?>
				<?= htmlspecialchars(t('poiImport.skipped', ['count' => $result['skipped']])) ?>
			<?php endif ?>
		</p>
	<?php endif ?>

	<p class="hnsImportHint"><?= t('poiImport.hint') ?></p>

	<p id="poiImportStatus" hidden></p>

	<form id="poiImportForm" method="post" action="/hideandseek/import-pois" class="hnsImportForm" data-map-id="<?= (int)$loaded['id'] ?>">
		<div>
			<label for="poiCategory"><?= t('poiImport.category') ?></label>
			<input type="text" id="poiCategory" name="category" list="poiCategoryNames" autocomplete="off">
			<datalist id="poiCategoryNames">
				<?php foreach ($globalData['categories'] as $category): ?>
					<option value="<?= htmlspecialchars($category['name']) ?>" data-colour="<?= htmlspecialchars($category['colour'] ?? '') ?>" data-icon="<?= htmlspecialchars($category['icon'] ?? '') ?>"></option>
				<?php endforeach ?>
			</datalist>
		</div>

		<div class="hnsImportStyle">
			<div>
				<label for="poiColour"><?= t('poiImport.colour') ?></label>
				<input type="color" id="poiColour" name="colour" class="hnsColourInput">
			</div>
			<div>
				<label for="poiIcon"><?= t('poiImport.icon') ?></label>
				<input type="text" id="poiIcon" name="icon" class="hnsIconInput" autocomplete="off" size="3">
			</div>
			<p class="hnsImportHint"><?= t('poiImport.iconHint') ?></p>
		</div>

		<div>
			<label for="poiData"><?= t('poiImport.data') ?></label>
			<textarea id="poiData" name="data" rows="16" spellcheck="false"></textarea>
		</div>

		<button type="submit"><?= t('poiImport.submit') ?></button>
	</form>

	<h2><?= t('poiImport.listHeading') ?></h2>

	<?php if (!$globalData['categories']): ?>
		<p><?= t('poiImport.empty') ?></p>
	<?php else: ?>
		<div class="hnsExport">
			<button type="button" id="poiExportButton" data-map-id="<?= (int)$loaded['id'] ?>"><?= t('poiImport.export') ?></button>
			<span id="poiExportStatus" class="hnsImportHint" hidden></span>
		</div>
		<textarea id="poiExportText" rows="12" spellcheck="false" readonly hidden></textarea>

		<form id="poiCategoriesForm" method="post" action="/hideandseek/import-pois" class="hnsSaveAll">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
			<input type="hidden" name="action" value="styles">
			<input type="hidden" name="map_id" value="<?= (int)$loaded['id'] ?>">
			<button type="submit"><?= t('poiImport.saveAll') ?></button>
		</form>

		<table id="poiCategoryTable">
			<thead>
				<tr>
					<th><?= t('poiImport.column.category') ?></th>
					<th><?= t('poiImport.column.colour') ?></th>
					<th><?= t('poiImport.column.icon') ?></th>
					<th><?= t('poiImport.column.count') ?></th>
					<th><?= t('poiImport.column.action') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($globalData['categories'] as $category): ?>
					<?php $id = (int)$category['id'] ?>
					<?php $edit = is_array($globalData['categoryEdits'][$id] ?? null) ? $globalData['categoryEdits'][$id] : [] ?>
					<?php $field = 'categories[' . $id . ']' ?>
					<tr data-category-id="<?= $id ?>">
						<td>
							<input type="text" name="<?= $field ?>[name]" class="hnsCategoryNameInput" form="poiCategoriesForm" value="<?= htmlspecialchars((string)($edit['name'] ?? $category['name'])) ?>" aria-label="<?= htmlspecialchars(t('poiImport.column.category')) ?>" autocomplete="off">
						</td>
						<td>
							<input type="color" name="<?= $field ?>[colour]" class="hnsColourInput" form="poiCategoriesForm" value="<?= htmlspecialchars((string)($edit['colour'] ?? $category['colour'] ?? '')) ?>">
						</td>
						<td>
							<input type="text" name="<?= $field ?>[icon]" class="hnsIconInput" form="poiCategoriesForm" value="<?= htmlspecialchars((string)($edit['icon'] ?? $category['icon'] ?? '')) ?>" autocomplete="off" size="3">
						</td>
						<td><?= (int)$category['poi_count'] ?></td>
						<td class="hnsCategoryActions">
							<button type="submit" form="poiCategoriesForm" name="only" value="<?= $id ?>"><?= t('poiImport.save') ?></button>
							<form method="post" action="/hideandseek/import-pois">
								<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="map_id" value="<?= (int)$loaded['id'] ?>">
								<input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
								<button type="submit"><?= t('poiImport.delete') ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	<?php endif ?>

	<script src="<?= asset('/modules/hideandseek/js/poiImport.js') ?>"></script>
<?php endif ?>

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
