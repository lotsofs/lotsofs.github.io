<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('gameMap.heading') ?></h1>

<?php if ($globalData['formError'] !== ''): ?>
	<p class="formError"><?= htmlspecialchars($globalData['formError']) ?></p>
<?php endif ?>

<?php $loaded = $globalData['loadedGameMap'] ?>

<?php /* A div, not a p: a p may hold only phrasing content, so a form inside one
         is closed by the browser before it and the layout comes apart. */ ?>
<div class="hnsLoadedMap">
	<?php if ($loaded): ?>
		<span><?= htmlspecialchars(t('gameMap.loaded', ['name' => $loaded['name']])) ?></span>
		<form method="post" action="/hideandseek/game-maps">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
			<input type="hidden" name="action" value="close">
			<button type="submit"><?= t('gameMap.close') ?></button>
		</form>
	<?php else: ?>
		<span><?= t('gameMap.noneLoaded') ?></span>
	<?php endif ?>
</div>

<h2><?= t('gameMap.createHeading') ?></h2>

<form method="post" action="/hideandseek/game-maps">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
	<input type="hidden" name="action" value="create">
	<label for="gameMapName"><?= t('gameMap.name') ?></label>
	<input type="text" id="gameMapName" name="name" autocomplete="off">
	<button type="submit"><?= t('gameMap.create') ?></button>
</form>

<h2><?= t('gameMap.listHeading') ?></h2>

<?php if (!$globalData['gameMaps']): ?>
	<p><?= t('gameMap.empty') ?></p>
<?php else: ?>
	<table id="gameMapTable">
		<thead>
			<tr>
				<th><?= t('gameMap.column.id') ?></th>
				<th><?= t('gameMap.column.name') ?></th>
				<th><?= t('gameMap.column.action') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['gameMaps'] as $map): ?>
				<?php $isLoaded = $loaded && (int)$loaded['id'] === (int)$map['id'] ?>
				<tr<?= $isLoaded ? ' class="hnsGameMapCurrent"' : '' ?>>
					<td><?= (int)$map['id'] ?></td>
					<td>
						<?php /* The rename field is the name, so there is nothing to open
						         first and nothing to cancel. */ ?>
						<form method="post" action="/hideandseek/game-maps">
							<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
							<input type="hidden" name="action" value="rename">
							<input type="hidden" name="map_id" value="<?= (int)$map['id'] ?>">
							<input type="text" name="name" value="<?= htmlspecialchars($map['name']) ?>" autocomplete="off">
							<button type="submit"><?= t('gameMap.rename') ?></button>
						</form>
					</td>
					<td>
						<?php if (!$isLoaded): ?>
							<form method="post" action="/hideandseek/game-maps">
								<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
								<input type="hidden" name="action" value="load">
								<input type="hidden" name="map_id" value="<?= (int)$map['id'] ?>">
								<button type="submit"><?= t('gameMap.loadButton') ?></button>
							</form>
						<?php endif ?>

						<form method="post" action="/hideandseek/game-maps">
							<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
							<input type="hidden" name="action" value="delete">
							<input type="hidden" name="map_id" value="<?= (int)$map['id'] ?>">
							<button type="submit"><?= t('gameMap.delete') ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
