<?php /* One home link's fields, for its row in the editor or, with $fieldLink null, the add form. */ ?>
<?php
	$fieldFilter = $fieldLink['filter'] ?? '';
	$fieldSort = $fieldLink['sort'] ?? 'id';
	$fieldDir = $fieldLink['dir'] ?? 'asc';
?>
<label class="homeLinkField">
	<span><?= t('home.field.label') ?></span>
	<input type="text" name="label" value="<?= htmlspecialchars($fieldLink['label'] ?? '') ?>" placeholder="<?= htmlspecialchars($fieldLink['fallback'] ?? '') ?>">
</label>
<label class="homeLinkField">
	<span><?= t('home.field.note') ?></span>
	<input type="text" name="note" value="<?= htmlspecialchars($fieldLink['note'] ?? '') ?>">
</label>
<label class="homeLinkField">
	<span><?= t('song.list.filterHeading') ?></span>
	<select name="filter">
		<option value=""><?= t('song.list.heading') ?></option>
		<?php foreach (['artists' => t('nav.artists'), 'albums' => t('nav.albums')] as $group => $groupLabel): ?>
			<?php if ($globalData['filterChoices'][$group]): ?>
				<optgroup label="<?= htmlspecialchars($groupLabel) ?>">
					<?php foreach ($globalData['filterChoices'][$group] as $value => $choice): ?>
						<option value="<?= $value ?>"<?= $value === $fieldFilter ? ' selected' : '' ?>><?= htmlspecialchars($choice) ?></option>
					<?php endforeach ?>
				</optgroup>
			<?php endif ?>
		<?php endforeach ?>
	</select>
</label>
<label class="homeLinkField">
	<span><?= t('song.list.sortBy') ?></span>
	<select name="sort">
		<?php foreach ($globalData['sortOptions'] as $key => $sortName): ?>
			<option value="<?= htmlspecialchars($key) ?>"<?= $key === $fieldSort ? ' selected' : '' ?>><?= htmlspecialchars($sortName) ?></option>
		<?php endforeach ?>
	</select>
</label>
<label class="homeLinkField">
	<span><?= t('home.field.dir') ?></span>
	<select name="dir">
		<option value="asc"<?= $fieldDir === 'asc' ? ' selected' : '' ?>><?= t('song.list.sortAscending') ?></option>
		<option value="desc"<?= $fieldDir === 'desc' ? ' selected' : '' ?>><?= t('song.list.sortDescending') ?></option>
	</select>
</label>
