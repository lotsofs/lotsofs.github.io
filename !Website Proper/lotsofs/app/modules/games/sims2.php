<?php

/// The Sims 2 page's checklists, in page order: each a key, a title and its
/// items in groups, a group headed by its name or by nothing when that is ''
/// (mostly the pack the items came with). Seeded from memory on 2026-11-14,
/// so they are the owner's to correct. An item's tick is keyed by its list's
/// key and its name (gamesSims2Key()), so renaming an item here loses its tick
/// on the page, and the row stays under the old key.
///
/// A list with `columns` is a grid instead (the owner's wish, 2026-11-15): its
/// items are rows, and each column set (`key`, `label`, `count`) gives every
/// row that many boxes, numbered from 1. `limits`, by group, cuts a set down
/// for that group's rows (0 for none), leaving the rest of the row blank.
function gamesSims2Lists() {
	return [
		[
			'key' => 'npcEncounters',
			'title' => 'NPC Encounters',
			'groups' => [
				'Base game' => ['Townie','Maid','Gardener','Nanny','Bartender','Exterminator','Repairman','Police','Firefighter','Social worker','Repo-man','Mail carrier','Newspaper deliverer','Carpool driver','Pizza deliverer','Delivery person','Cashier','The Grim Reaper','Hula Zombie','Social Bunny (pink)','Social Bunny (blue)','Social Bunny (yellow)','Headmaster','Burglar','Therapist','Big Boss','Pollination Technician','NPC - Remote Control Car'],
				'University' => ['Chinese food deliverer','Cafeteria chef','Professor','Coach','University mascot','Cheerleader','Streaker'],
				'University/Nightlife' => ['Barista'],
				'Nightlife' => ['Host','Server','Cook','Mrs. Crumplebottom','Dateable slob','Mr. Big','The Diva','Grand vampire'],
				'Nightlife/FreeTime' => ['Gypsy matchmaker','DJ'],
				'Open for Business' => ['Stylist','Reporter','NPC - Robot'],
				'Pets' => ['Obedience trainer','Animal control officer','Wolf','Pack leader','Stinky skunk','NPC - Bird'],
				'Pets & Apartment Life' => ['Spectral cat'],
				'Seasons' => ['Penguin','Ideal plantsim','Weather','NPC - Penguin'],
				'Bon Voyage' => ['Bellhop','Housekeeper','Masseuse','Local chef (Three Lakes)','Local chef (Takemizu Village)','Local chef (Twikkii Island)','Fire dancer','Tour guide','Unsavory Charlatan','Ninja','Bigfoot','Wise Old Man','Witch Doctor','Pirate Captain Edward Dregg','Bigfoot Bigfoot'],
				'FreeTime' => ['Hobby instructor','Rod Humble','Genie Midlock'],
				'Apartment Life' => ['Butler','Landlord','Local chef (One Complete Diner)','Human statue','Break dancer','High witch (good)','High witch (evil)','Spectral assistant'],
				'Happy Holiday Stuff' => ['Santa Klaus','Father Time','Toddler New Year'],
			],
		],
		[
			'key' => 'lifetimeWants',
			'title' => 'Lifetime Wants',
			'groups' => [
				'Family' => ['Reach Golden Anniversary','Graduate 3 Children from College','Marry Off 6 Children','Have 6 Grandchildren','Raise 20 Puppies or Kittens'],
				'Fortune' => ['Own 5 Top-Level Businesses','Own 10 Top-Level Businesses', 'Have 6 Pets Reach the Top Career Level',],
				'Knowledge' => ['Max Out 7 Skills', ],
				'Popularity' => ['Have 20 Simultaneous Best Friends', 'Have 20 Simultaneous Pet Best Friends',],
				'Romance' => ['Have 20 Simultaneous Lovers', 'WooHoo with 20 Different Sims'],
				'Pleasure' => ['Have 50 1st Dates','Have 50 Dream Dates'],
				'Grilled Cheese' => ['Eat 200 Grilled Cheese Sandwiches'],
				'Careers' => [
					'Become Hall of Famer (Fo/Po/Ro)',
					'Become Business Tycoon (Fo)',
					'Become Criminal Mastermind (Fo/Kn)',
					'Become Celebrity Chef (Pl/Po/Ro)',
					'Become Captain Hero (Fa/Po)',
					'Become Chief of Staff (Fo/Kn)',
					'Become General (Po)',
					'Become Mad Scientist (Kn)',
					'Become Mayor (Po)',
					'Become Professional Party Guest (Pl/Ro)',
					'Become Mogul (Fo/Po)', 
					'Become Game President (Fo/Kn)',
					'Become Space Pirate (Fo/Kn)',
					'Become Education Minister (Fa/Kn)',
					'Become Game Designer (Kn/Pl)',
					'Become Media Mogul (Kn/Po)',
					'Become The Law (Fo/Po)',
					'Become Rock God (Po/Ro)',
					'Become City Planner (Fo/Kn)',
					'Become World Class Ballet Dancer (Fo/Kn)',
					'Become Prestidigitator (Fo/Kn)',
					'Become Head of SCIA (Fo/Kn)',
					'Become Hand of Poseidon (Fo/Kn)',
				],
			],
		],
		[
			'key' => 'careers',
			'title' => 'Careers',
			'groups' => [
				'Base game' => ['Athletic', 'Business', 'Criminal', 'Culinary', 'Law Enforcement', 'Medicine', 'Military', 'Politics', 'Science', 'Slacker'],
				'University' => ['Artist', 'Natural Science', 'Paranormal', 'Show Business'],
				'Pets, for pets' => ['Pet Security', 'Pet Service', 'Pet Show Business'],
				'Seasons' => ['Adventurer', 'Education', 'Gamer', 'Journalism', 'Law', 'Music'],
				'FreeTime' => ['Architecture', 'Dance', 'Entertainment', 'Intelligence', 'Oceanography'],
			],
			'columns' => [
				['key' => 'level', 'label' => 'Levels reached', 'count' => 10],
				['key' => 'chance', 'label' => 'Chance cards seen', 'count' => 10],
				['key' => 'te', 'label' => 'T/E levels reached', 'count' => 3],
			],
			'limits' => [
				'Pets, for pets' => ['level' => 5, 'chance' => 0, 'te' => 0],
			],
		],
		[
			'key' => 'hobbies',
			'title' => 'Hobbies',
			'groups' => [
				'FreeTime' => ['Arts & Crafts', 'Cuisine', 'Film & Literature', 'Fitness', 'Games', 'Music & Dance', 'Nature', 'Science', 'Sports', 'Tinkering'],
			],
			'columns' => [
				['key' => 'level', 'label' => 'Levels reached', 'count' => 10],
				['key' => 'chance', 'label' => 'Chance cards seen', 'count' => 6],
				['key' => 'secret', 'label' => 'Secret lot visited', 'count' => 1],
			],
		],
		[
			'key' => 'packs',
			'title' => 'Expansion and stuff packs',
			'groups' => [
				'Base game' => ['Base game'],
				'Expansion packs' => ['University', 'Nightlife', 'Open for Business', 'Pets', 'Seasons', 'Bon Voyage', 'FreeTime', 'Apartment Life'],
				'Stuff packs' => ['Family Fun Stuff', 'Glamour Life Stuff', 'Happy Holiday Stuff', 'Celebration! Stuff', 'H&M Fashion Stuff', 'Teen Style Stuff', 'Kitchen & Bath Interior Design Stuff', 'IKEA Home Stuff', 'Mansion & Garden Stuff'],
			],
		],
		[
			'key' => 'neighborhoods',
			'title' => 'Neighborhoods',
			'groups' => [
				'Base game' => ['Pleasantview', 'Strangetown', 'Veronaville'],
				'University' => ['Sim State University', 'La Fiesta Tech', 'Académie Le Tour'],
				'Nightlife' => ['Downtown'],
				'Open for Business' => ['Bluewater Village'],
				'Seasons' => ['Riverblossom Hills'],
				'Bon Voyage' => ['Twikkii Island', 'Three Lakes', 'Takemizu Village'],
				'FreeTime' => ['Desiderata Valley'],
				'Apartment Life' => ['Belladonna Cove'],
				'Secret' => ['Pets', 'Weather', 'Hobbies', 'Magic'],
			],
		],
		[
			'key' => 'aspirations',
			'title' => 'Aspirations',
			'groups' => [
				'Base game' => ['Grow Up', 'Family', 'Fortune', 'Knowledge', 'Popularity', 'Romance'],
				'Nightlife' => ['Pleasure', 'Grilled Cheese'],
			],
		],
		[
			'key' => 'lifeStates',
			'title' => 'Life states',
			'groups' => [
				'Base game' => ['Sim', 'Alien', 'Ghost (encounter)'],
				'University / FreeTime / Apartment Life' => ['Zombie'],
				'Nightlife' => ['Vampire'],
				'Open for Business' => ['Servo'],
				'Pets' => ['Small dog', 'Large dog', 'Cat', 'Werewolf'],
				'Seasons' => ['PlantSim'],
				'Bon Voyage' => ['Bigfoot'],
				'FreeTime' => ['Genie (encounter)'],
				'Apartment Life' => ['Witch'],
				'Pets & Apartment Life' => ['Spectral Cat'],
			],
		],
		[
			'key' => GAMES_SIMS2_VEHICLE_LIST,
			'title' => 'Vehicle Encounters',
			/// Its items are the sims2_vehicle rows, filled in by gamesSims2WithVehicles().
			'vehicles' => true,
			'groups' => [],
		],
		[
			'key' => 'deaths',
			'title' => 'Deaths',
			'groups' => [
				'Base game' => ['Starvation','Electrocution','Drowning','Fire','Old age','Disease','Flies','Fright','Satellite'],
				'University' => ['Cowplant'],
				'Nightlife' => ['Coffin scare','Sunlight'],
				'Open for Business' => ['Rally forth'],
				'Seasons' => ['Wishing well','Hail','Lightning'],
				'Apartment Life' => ['Murphy bed'],
			],
		]
	];
}

/// What a tick on an item is stored under. The group is not part of it, so an
/// item can move between groups and keep its tick, and one list cannot hold two
/// items of the same name (the suite checks).
function gamesSims2Key($list, $item) {
	return $list['key'] . '|' . $item;
}

/// What a tick on one box of a grid is stored under: the row's key, the column
/// set and the box's number (`careers|Paranormal|level|3`).
function gamesSims2CellKey($list, $item, $column, $number) {
	return gamesSims2Key($list, $item) . '|' . $column['key'] . '|' . $number;
}

/// How many boxes a column set has in a group's rows of a grid.
function gamesSims2CellCount($list, $group, $column) {
	return min($column['count'], $list['limits'][$group][$column['key']] ?? $column['count']);
}

/// Every key a list can be ticked under: its items, or a grid's boxes.
function gamesSims2ListKeys($list) {
	$keys = [];
	foreach ($list['groups'] as $group => $items) {
		foreach ($items as $item) {
			if (!isset($list['columns'])) {
				$keys[] = gamesSims2Key($list, $item);
				continue;
			}
			foreach ($list['columns'] as $column) {
				for ($number = 1; $number <= gamesSims2CellCount($list, $group, $column); $number++) {
					$keys[] = gamesSims2CellKey($list, $item, $column, $number);
				}
			}
		}
	}
	return $keys;
}

/// Every key on the page, key => true, from the lists as the page shows them
/// (with gamesSims2WithVehicles()).
function gamesSims2Keys($lists) {
	$keys = [];
	foreach ($lists as $list) {
		$keys += array_fill_keys(gamesSims2ListKeys($list), true);
	}
	return $keys;
}

/// The key of the list whose items are the written-up vehicles.
const GAMES_SIMS2_VEHICLE_LIST = 'vehicleEncounters';

/// The written-up vehicles' limits, in bytes (no mbstring here).
const GAMES_SIMS2_VEHICLE_NAME_MAX = 100;
const GAMES_SIMS2_OCCASION_MAX = 200;
const GAMES_SIMS2_OCCASIONS_MAX = 50;

/// The vehicles the owner has written up, shared by every account, in the
/// order they were added: each an id, a name and its occasions. With $id, that
/// one vehicle or null.
function gamesSims2Vehicles($db, $id = null) {
	$rows = $id === null
		? $db->query("SELECT id, name, occasions FROM sims2_vehicle ORDER BY id")->fetchAll()
		: $db->query("SELECT id, name, occasions FROM sims2_vehicle WHERE id = ?", [(int)$id])->fetchAll();
	$vehicles = [];
	foreach ($rows as $row) {
		$occasions = json_decode($row['occasions'], true);
		$vehicles[] = [
			'id' => (int)$row['id'],
			'name' => $row['name'],
			'occasions' => is_array($occasions) ? $occasions : [],
		];
	}
	return $id === null ? $vehicles : ($vehicles[0] ?? null);
}

/// The lists with the vehicles list's items filled in, as one unheaded group of
/// their names (which is what its ticks and its count read), and the vehicles
/// themselves for the view.
function gamesSims2WithVehicles($lists, $vehicles) {
	foreach ($lists as &$list) {
		if (empty($list['vehicles'])) {
			continue;
		}
		$list['groups'] = $vehicles ? ['' => array_column($vehicles, 'name')] : [];
		$list['vehicleRows'] = $vehicles;
	}
	unset($list);
	return $lists;
}

/// What a tick on a vehicle is stored under.
function gamesSims2VehicleKey($name) {
	return gamesSims2Key(['key' => GAMES_SIMS2_VEHICLE_LIST], $name);
}

/// A vehicle's name, trimmed and checked: [name, error or null]. A name may not
/// be another vehicle's in any case, as the index has it; $id is the vehicle
/// being renamed, which may change the case of its own.
function gamesSims2VehicleName($db, $name, $id = 0) {
	$name = trim((string)$name);
	$error = null;
	if ($name === '') {
		$error = t('sims2.vehicleNameRequired');
	}
	else if (strlen($name) > GAMES_SIMS2_VEHICLE_NAME_MAX) {
		$error = t('sims2.vehicleNameTooLong', ['max' => GAMES_SIMS2_VEHICLE_NAME_MAX]);
	}
	else if ($db->query("SELECT 1 FROM sims2_vehicle WHERE name = ? COLLATE NOCASE AND id <> ?", [$name, (int)$id])->fetch()) {
		$error = t('sims2.vehicleNameTaken', ['name' => $name]);
	}
	return [$name, $error];
}

/// A vehicle's occasions, each trimmed and the empty ones dropped (emptying
/// one is how it goes): [occasions, error or null]. Null for anything but a
/// list of strings.
function gamesSims2Occasions($raw) {
	if (!is_array($raw) || array_values($raw) !== $raw) {
		return null;
	}
	$occasions = [];
	foreach ($raw as $occasion) {
		if (!is_string($occasion)) {
			return null;
		}
		if (trim($occasion) !== '') {
			$occasions[] = trim($occasion);
		}
	}
	$error = null;
	if (count($occasions) > GAMES_SIMS2_OCCASIONS_MAX) {
		$error = t('sims2.tooManyOccasions', ['max' => GAMES_SIMS2_OCCASIONS_MAX]);
	}
	else if (max(array_map('strlen', $occasions ?: [''])) > GAMES_SIMS2_OCCASION_MAX) {
		$error = t('sims2.occasionTooLong', ['max' => GAMES_SIMS2_OCCASION_MAX]);
	}
	return [$occasions, $error];
}

/// Renames a vehicle's ticks along with it, for every account, since a tick is
/// keyed by name. Inside the caller's transaction. An account that somehow
/// already has the new key keeps that one, and the old row is left behind.
function gamesSims2MoveVehicleTicks($db, $oldName, $newName) {
	if ($oldName === $newName) {
		return;
	}
	$db->query(
		"UPDATE sims2_check SET item_key = ? WHERE item_key = ?
			AND account_id NOT IN (SELECT account_id FROM sims2_check WHERE item_key = ?)",
		[gamesSims2VehicleKey($newName), gamesSims2VehicleKey($oldName), gamesSims2VehicleKey($newName)]
	);
}
