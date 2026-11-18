<?php
/// One written-up vehicle on the Vehicle Encounters card: its tick, its name and
/// its occasions, plus the − and + an admin edits with (sims2.js). Rendered by
/// the page and by /games/ajax/sims2-vehicle, whose answer replaces the row.
/// Takes $vehicle, $vehicleChecked and $vehicleEditable.
$name = htmlspecialchars($vehicle['name']);
$editHint = $vehicleEditable ? ' title="' . htmlspecialchars(t('sims2.doubleClickToEdit')) . '"' : '';
?>
<li class="simsVehicle" data-id="<?= (int)$vehicle['id'] ?>">
	<div class="simsVehicleHead">
		<label class="simsItem">
			<input type="checkbox" class="simsCheck" value="<?= htmlspecialchars(gamesSims2VehicleKey($vehicle['name'])) ?>"<?= $vehicleChecked ? ' checked' : '' ?>>
			<span class="simsVehicleName"<?= $editHint ?>><?= $name ?></span>
		</label>
		<?php if ($vehicleEditable): ?>
			<button type="button" class="simsVehicleRemove simsMinus" title="<?= htmlspecialchars(t('sims2.vehicleDelete', ['name' => $vehicle['name']])) ?>">−</button>
		<?php endif ?>
	</div>
	<?php if ($vehicle['occasions'] || $vehicleEditable): ?>
		<ul class="simsOccasions">
			<?php foreach ($vehicle['occasions'] as $index => $occasion): ?>
				<li class="simsOccasion">
					<span class="simsOccasionText"<?= $editHint ?>><?= htmlspecialchars($occasion) ?></span>
					<?php if ($vehicleEditable): ?>
						<button type="button" class="simsOccasionRemove simsMinus" title="<?= htmlspecialchars(t('sims2.occasionDelete')) ?>">−</button>
					<?php endif ?>
				</li>
			<?php endforeach ?>
			<?php if ($vehicleEditable): ?>
				<li class="simsOccasionAddRow"><button type="button" class="simsOccasionAdd simsPlus" title="<?= htmlspecialchars(t('sims2.occasionAdd')) ?>">+</button></li>
			<?php endif ?>
		</ul>
	<?php endif ?>
</li>
