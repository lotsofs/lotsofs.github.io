<?php require(__MODULES__ . '/games/views/partials/head.php') ?>

<?php
/// A list's title and its "N of M", over its items or its grid's boxes.
$listHeading = function ($list) use ($globalData) {
	$keys = gamesSims2ListKeys($list);
	$done = count(array_intersect_key(array_flip($keys), $globalData['checked']));
	?>
	<h2><?= htmlspecialchars($list['title']) ?> <span class="simsProgress"><?= t('sims2.progress', ['done' => $done, 'total' => count($keys)]) ?></span></h2>
	<?php
};

$box = function ($key, $label) use ($globalData) {
	?><input type="checkbox" class="simsCheck" value="<?= htmlspecialchars($key) ?>" aria-label="<?= htmlspecialchars($label) ?>"<?= isset($globalData['checked'][$key]) ? ' checked' : '' ?>><?php
};
?>

<h1><?= t('sims2.heading') ?></h1>

<p class="simsStatus"><span id="simsStatus" class="formError" role="status"></span></p>

<?php /* Every list in page order, checklists and grids alike; sims2.js packs them
         (see games.md). */ ?>
<div class="simsLists">
	<?php foreach ($globalData['lists'] as $list): ?>
		<?php if (!isset($list['columns'])): ?>
		<section class="simsList" data-list="<?= htmlspecialchars($list['key']) ?>">
			<?php $listHeading($list) ?>
			<?php if (!empty($list['vehicles'])): ?>
				<?php /* The written-up vehicles, one partial per row so the endpoint
				         that edits them draws a row the same way (sims2.js). */ ?>
				<ul class="simsVehicles"<?= $globalData['isAdmin'] ? ' data-editable="1"' : '' ?>>
					<?php foreach ($list['vehicleRows'] as $vehicle): ?>
						<?php
						$vehicleChecked = isset($globalData['checked'][gamesSims2VehicleKey($vehicle['name'])]);
						$vehicleEditable = $globalData['isAdmin'];
						require __MODULES__ . '/games/views/partials/sims2Vehicle.php';
						?>
					<?php endforeach ?>
				</ul>
				<p class="simsEmpty"><?= t('sims2.noVehicles') ?></p>
				<?php if ($globalData['isAdmin']): ?>
					<button type="button" class="simsVehicleAdd simsPlus" title="<?= htmlspecialchars(t('sims2.vehicleAdd')) ?>">+</button>
				<?php endif ?>
			<?php else: ?>
			<?php foreach ($list['groups'] as $group => $items): ?>
				<?php if ($group !== ''): ?>
					<h3><?= htmlspecialchars($group) ?></h3>
				<?php endif ?>
				<ul>
					<?php foreach ($items as $item): ?>
						<?php $key = gamesSims2Key($list, $item) ?>
						<li>
							<label class="simsItem">
								<input type="checkbox" class="simsCheck" value="<?= htmlspecialchars($key) ?>"<?= isset($globalData['checked'][$key]) ? ' checked' : '' ?>>
								<span><?= htmlspecialchars($item) ?></span>
							</label>
						</li>
					<?php endforeach ?>
				</ul>
			<?php endforeach ?>
			<?php endif ?>
		</section>
		<?php else: ?>
	<?php /* A grid: as wide as its boxes, spanning as many columns as that takes,
	         and scrolling sideways inside its card on a narrower screen. */ ?>
	<section class="simsList simsGridList" data-list="<?= htmlspecialchars($list['key']) ?>">
		<?php $listHeading($list) ?>
		<div class="simsGridScroll">
			<table class="simsGrid">
				<thead>
					<tr>
						<th rowspan="2" class="simsGridCorner"></th>
						<?php foreach ($list['columns'] as $column): ?>
							<th colspan="<?= (int)$column['count'] ?>" class="simsGridSet"><?= htmlspecialchars($column['label']) ?></th>
						<?php endforeach ?>
					</tr>
					<tr>
						<?php foreach ($list['columns'] as $column): ?>
							<?php for ($number = 1; $number <= $column['count']; $number++): ?>
								<th class="simsGridNumber<?= $number === 1 ? ' simsGridSetStart' : '' ?>"><?= $column['count'] > 1 ? $number : '' ?></th>
							<?php endfor ?>
						<?php endforeach ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($list['groups'] as $group => $items): ?>
						<?php if ($group !== ''): ?>
							<tr class="simsGridGroup">
								<th scope="rowgroup"><?= htmlspecialchars($group) ?></th>
								<td colspan="<?= array_sum(array_column($list['columns'], 'count')) ?>"></td>
							</tr>
						<?php endif ?>
						<?php foreach ($items as $item): ?>
							<tr class="simsGridRow">
								<th scope="row"><?= htmlspecialchars($item) ?></th>
								<?php foreach ($list['columns'] as $column): ?>
									<?php $boxes = gamesSims2CellCount($list, $group, $column) ?>
									<?php for ($number = 1; $number <= $column['count']; $number++): ?>
										<?php $start = $number === 1 ? ' simsGridSetStart' : '' ?>
										<?php if ($number <= $boxes): ?>
											<?php $label = $column['count'] > 1 ? "$item: {$column['label']} $number" : "$item: {$column['label']}" ?>
											<td class="simsGridCell<?= $start ?>"><?php $box(gamesSims2CellKey($list, $item, $column, $number), $label) ?></td>
										<?php else: ?>
											<?php /* A box this row cannot have (a pet career's level 6). */ ?>
											<td class="simsGridNone<?= $start ?>"></td>
										<?php endif ?>
									<?php endfor ?>
								<?php endforeach ?>
							</tr>
						<?php endforeach ?>
					<?php endforeach ?>
				</tbody>
			</table>
		</div>
	</section>
		<?php endif ?>
	<?php endforeach ?>
</div>

<script src="<?= asset('/modules/games/js/sims2.js') ?>"></script>

<?php require(__MODULES__ . '/games/views/partials/foot.php') ?>
