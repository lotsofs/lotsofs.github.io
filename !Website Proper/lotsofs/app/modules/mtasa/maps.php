<?php

require_once __MODULES__ . '/mtasa/fields.php';

/// How many maps a page of the list may show (the pager's Per page), and the
/// default, which addresses leave out.
const MTASA_PAGE_SIZES = [10, 25, 50, 100, 250, 500, 1000];
const MTASA_PAGE_SIZE = 100;
/// The largest page size an address may ask for, more than the catalogue holds.
const MTASA_PAGE_SIZE_MAX = 10000;

/// A map's race mode, worked out rather than stored (the owner's rule): a race
/// with checkpoints is a sprint; without them the respawn mode decides, none
/// being a derby, a time limit or waves a freeroam, and no respawn mode either
/// of the two. A map the script never read (no checkpoint count) has none.
/// A map's gamemode, else the script's guess at it.
const MTASA_GAMEMODE_SQL = "COALESCE(NULLIF(m.gamemode, ''), a.category_guess)";

const MTASA_RACE_MODE_SQL = "(CASE
	WHEN a.checkpoints IS NULL THEN NULL
	WHEN a.checkpoints > 0 THEN 'Sprint'
	WHEN a.respawn_mode = 'none' THEN 'Destruction Derby'
	WHEN a.respawn_mode IN ('timelimit', 'waves') THEN 'Freeroam'
	ELSE 'Destruction Derby/Freeroam'
END)";

/// The list's columns, in order: `sql` sorts in the query, `derived` in PHP
/// (the duplicate's original comes from the whole catalogue's chains, which SQL
/// cannot follow). Blanks sort last either way.
function mtasaListColumns() {
	return [
		'id' => ['sql' => 'm.id'],
		'name' => ['sql' => "COALESCE(NULLIF(m.custom_name, ''), NULLIF(m.original_name, ''), NULLIF(a.title, ''))", 'text' => true],
		'author' => ['sql' => 'a.author', 'text' => true],
		'status' => ['sql' => 'm.status', 'text' => true],
		'gamemode' => ['sql' => MTASA_GAMEMODE_SQL, 'text' => true],
		'checkpoints' => ['sql' => 'a.checkpoints'],
		'finished' => ['sql' => 'm.finished'],
		'video' => ['sql' => 'm.video', 'text' => true],
		'errors' => ['sql' => 'a.errors', 'text' => true],
		'warnings' => ['sql' => 'a.warnings', 'text' => true],
		'original' => ['derived' => true],
		'comments' => ['sql' => "(SELECT NULLIF(COUNT(*), 0) FROM map_comment c WHERE c.map_id = m.id AND c.parent_id IS NULL AND c.resolved_at IS NULL)"],
		'custom_description' => ['sql' => 'm.custom_description', 'text' => true],
	];
}

/// What the sheet wrote where it looked for a map's original name and found none.
const MTASA_NAME_NOT_FOUND = 'Not found';

/// Whether a map's original name is known: set, and not the sheet's "Not found".
function mtasaOriginalNameKnown($value) {
	$value = trim((string)$value);

	return $value !== '' && $value !== MTASA_NAME_NOT_FOUND;
}

/// The owner's fields that a guess of the script's differs from, as the map
/// page's ! marks them: `owner field => its value`. Only where the owner's
/// value is known and the script guessed at all.
function mtasaGuessMismatches($row) {
	$mismatches = [];
	foreach (['original_name' => 'title', 'gamemode' => 'category_guess', 'duplicate_of' => 'duplicate_of_closest'] as $owner => $guess) {
		$value = trim((string)($row[$owner] ?? ''));
		$guessed = trim((string)($row[$guess] ?? ''));
		$known = $owner === 'original_name' ? mtasaOriginalNameKnown($value) : $value !== '';
		if ($known && $guessed !== '' && $value !== $guessed) {
			$mismatches[$owner] = $value;
		}
	}

	return $mismatches;
}

/// Which field a map's name comes from: the custom name, else the original,
/// else the title the script found, else null for the folder name every map has.
function mtasaNameSource($row) {
	foreach (['custom_name', 'original_name', 'title'] as $key) {
		if (($row[$key] ?? '') !== '' && $row[$key] !== null) {
			return $key;
		}
	}

	return null;
}

function mtasaMapName($row) {
	$source = mtasaNameSource($row);

	return $source !== null ? $row[$source] : 'z(' . (int)($row['id'] ?? $row['map_id']) . ')';
}

/// Where the name comes from, for its hover box on the list.
function mtasaNameSourceText($row) {
	$source = mtasaNameSource($row);
	if ($source === 'title') {
		$from = trim((string)($row['title_source'] ?? ''));

		return $from !== '' ? t('list.nameFrom.titleSource', ['source' => $from]) : t('list.nameFrom.title');
	}

	return t('list.nameFrom.' . ($source ?? 'folder'));
}

/// What a copy is called by how many of the set there are with it: the 2nd is
/// a duplicate, the 10th a decuplicate, the 11th an undecuplicate (the owner's
/// word), on in the same Latin to the 99th, and "100th copy" past that.
function mtasaDuplicateWord($instance) {
	$units = ['', 'un', 'duo', 'tre', 'quattuor', 'quin', 'sex', 'septen', 'octo', 'novem'];
	$tens = ['', 'dec', 'vigint', 'trigint', 'quadragint', 'quinquagint', 'sexagint', 'septuagint', 'octogint', 'nonagint'];
	$small = [2 => 'duplicate', 3 => 'triplicate', 4 => 'quadruplicate', 5 => 'quintuplicate', 6 => 'sextuplicate', 7 => 'septuplicate', 8 => 'octuplicate', 9 => 'nonuplicate'];

	if (isset($small[$instance])) {
		return $small[$instance];
	}
	if ($instance >= 10 && $instance <= 99) {
		return $units[$instance % 10] . $tens[intdiv($instance, 10)] . 'uplicate';
	}

	$suffix = ($instance % 100 >= 11 && $instance % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$instance % 10] ?? 'th');

	return $instance . $suffix . ' copy';
}

/// Every map's place among its copies, worked out once per request from the
/// whole catalogue. A map's parent is the owner's Duplicate of when set (its
/// own id meaning "an original", whatever the script thinks), else the
/// script's closest copy; the original is where the chain ends.
///
/// Returns `parent` and `root` keyed by map id, and `copies`, the copies of
/// each original in id order, keyed by the original.
function mtasaDuplicates($db) {
	static $cache = [];
	$key = spl_object_id($db);
	if (isset($cache[$key])) {
		return $cache[$key];
	}

	$rows = $db->query("
		SELECT m.id, m.duplicate_of, a.duplicate_of_closest
		FROM map m
		LEFT JOIN map_analysis a ON a.map_id = m.id
		ORDER BY m.id
	")->fetchAll();

	$parent = [];
	foreach ($rows as $row) {
		$id = (int)$row['id'];
		$up = $row['duplicate_of'] !== null ? (int)$row['duplicate_of'] : ($row['duplicate_of_closest'] !== null ? (int)$row['duplicate_of_closest'] : null);
		$parent[$id] = $up === $id ? null : $up;
	}

	$root = [];
	foreach ($parent as $id => $_) {
		$seen = [$id => true];
		$at = $id;
		/// A parent that is not a map, or a loop, ends the chain where it is.
		while ($parent[$at] !== null && array_key_exists($parent[$at], $parent) && !isset($seen[$parent[$at]])) {
			$at = $parent[$at];
			$seen[$at] = true;
		}
		$root[$id] = $at;
	}

	$copies = [];
	foreach ($root as $id => $original) {
		if ($original !== $id) {
			$copies[$original][] = $id;
		}
	}

	return $cache[$key] = ['parent' => $parent, 'root' => $root, 'copies' => $copies];
}

/// "triplicate of 81" for a copy, null for an original.
function mtasaDuplicateLabel($duplicates, $id) {
	$original = $duplicates['root'][$id] ?? $id;
	if ($original === $id) {
		return null;
	}

	$instance = array_search($id, $duplicates['copies'][$original], true) + 2;

	return t('duplicate.of', ['word' => mtasaDuplicateWord($instance), 'id' => $original]);
}

/// The filters, defined once: what each reads, its options (taken from the
/// catalogue, so a value the data brings is offered at once) and how a choice
/// narrows the query. Adding one is an entry here and, for a kind not seen
/// yet, its case in mtasaFilterSql().
function mtasaFilters($db) {
	$distinct = fn($column) => array_column($db->query("SELECT DISTINCT {$column} v FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} COLLATE NOCASE")->fetchAll(), 'v');

	return [
		'status' => ['kind' => 'values', 'column' => 'm.status', 'options' => $distinct('m.status')],
		/// The gamemode, else the script's guess, as the Gamemode column shows and sorts it.
		'gamemode' => ['kind' => 'values', 'column' => MTASA_GAMEMODE_SQL, 'options' => $distinct(MTASA_GAMEMODE_SQL)],
		'race_mode' => ['kind' => 'values', 'column' => MTASA_RACE_MODE_SQL, 'options' => $distinct(MTASA_RACE_MODE_SQL)],
		/// A filter's `hidden` values are left out while the address names none of it.
		'errors' => ['kind' => 'values', 'column' => 'a.errors', 'options' => $distinct('a.errors'), 'hidden' => ['folder missing']],
		/// Yes or no, with no "(none)": an unmarked map is not finished.
		'finished' => ['kind' => 'values', 'column' => 'm.finished', 'options' => ['1', '0'], 'binary' => true],
		'duplicates' => ['kind' => 'one', 'options' => ['originals', 'copies']],
		'comments' => ['kind' => 'one', 'options' => ['open']],
	];
}

/// The values a filter starts on: none for most (no narrowing), every option
/// but its `hidden` ones for a filter that has them.
function mtasaFilterDefault($filter) {
	if (empty($filter['hidden'])) {
		return null;
	}

	return array_values(array_merge(array_diff($filter['options'], $filter['hidden']), ['none']));
}

/// The filters an address asks for, kept to the options there are. A values
/// filter's `none` stands for an empty value, so "no status yet" is a choice.
/// A filter with a default is left out while it holds its default, and an
/// empty list (the address naming it with nothing ticked) means every map.
function mtasaReadFilters($filters, $query) {
	$chosen = [];
	foreach ($filters as $name => $filter) {
		$raw = $query[$name] ?? null;
		if ($filter['kind'] === 'values') {
			$picked = array_values(array_unique(array_filter((array)$raw, fn($v) => is_string($v) && (($v === 'none' && empty($filter['binary'])) || in_array($v, $filter['options'], true)))));
			$default = mtasaFilterDefault($filter);
			if ($default !== null) {
				$same = count($picked) === count($default) && !array_diff($picked, $default);
				if ($raw !== null && !$same) {
					$chosen[$name] = $picked;
				}
			}
			else if ($picked) {
				$chosen[$name] = $picked;
			}
		}
		else if (is_string($raw) && in_array($raw, $filter['options'], true)) {
			$chosen[$name] = $raw;
		}
	}

	$search = trim(is_string($query['q'] ?? null) ? $query['q'] : '');
	if ($search !== '') {
		$chosen['q'] = substr($search, 0, 200);
	}

	return $chosen;
}

/// One page of the list: the rows, the number of maps the filters leave and
/// the page actually shown. The ids are filtered and ordered first (in SQL
/// where it can, in PHP for what only the chains know), then the page's rows
/// are read by id, so the derived sorts never need a column of their own.
function mtasaListQuery($db, $filters, $chosen, $sort, $dir, $page, $per = MTASA_PAGE_SIZE) {
	$columns = mtasaListColumns();
	if (!isset($columns[$sort])) {
		$sort = 'id';
	}
	if ($dir !== 'asc' && $dir !== 'desc') {
		$dir = 'asc';
	}

	$where = [];
	$params = [];
	foreach ($filters as $name => $filter) {
		if (!empty($filter['hidden']) && !isset($chosen[$name])) {
			$where[] = "({$filter['column']} IS NULL OR {$filter['column']} = '' OR {$filter['column']} NOT IN (" . implode(', ', array_fill(0, count($filter['hidden']), '?')) . "))";
			$params = array_merge($params, $filter['hidden']);
		}
	}
	foreach ($chosen as $name => $choice) {
		if ($name === 'q' || ($filters[$name]['kind'] ?? null) !== 'values' || !$choice) {
			continue;
		}
		$column = $filters[$name]['column'];
		if (!empty($filters[$name]['binary']) && in_array('0', $choice, true)) {
			$choice[] = 'none';
		}
		$values = array_values(array_filter($choice, fn($v) => $v !== 'none'));
		$parts = [];
		if ($values) {
			$parts[] = "{$column} IN (" . implode(', ', array_fill(0, count($values), '?')) . ")";
			$params = array_merge($params, $values);
		}
		if (in_array('none', $choice, true)) {
			$parts[] = "({$column} IS NULL OR {$column} = '')";
		}
		$where[] = '(' . implode(' OR ', $parts) . ')';
	}

	if (isset($chosen['q'])) {
		$like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $chosen['q']) . '%';
		$searched = ['m.original_name', 'm.custom_name', 'm.hypothesized_name', 'm.custom_description', 'a.folder', 'a.title', 'a.author', 'a.description'];
		$parts = array_map(fn($column) => "{$column} LIKE ? ESCAPE '\\'", $searched);
		$params = array_merge($params, array_fill(0, count($searched), $like));
		if (ctype_digit($chosen['q'])) {
			$parts[] = 'm.id = ?';
			$params[] = (int)$chosen['q'];
		}
		$where[] = '(' . implode(' OR ', $parts) . ')';
	}

	if (($chosen['comments'] ?? null) === 'open') {
		$where[] = "EXISTS (SELECT 1 FROM map_comment c WHERE c.map_id = m.id AND c.parent_id IS NULL AND c.resolved_at IS NULL)";
	}

	$order = 'm.id';
	if (isset($columns[$sort]['sql'])) {
		$expr = $columns[$sort]['sql'];
		$collate = !empty($columns[$sort]['text']) ? ' COLLATE NOCASE' : '';
		$order = "({$expr} IS NULL OR {$expr} = '') ASC, {$expr}{$collate} " . strtoupper($dir) . ", m.id ASC";
	}

	$ids = array_map('intval', array_column($db->query(
		"SELECT m.id FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id"
			. ($where ? ' WHERE ' . implode(' AND ', $where) : '')
			. " ORDER BY {$order}",
		$params
	)->fetchAll(), 'id'));

	$duplicates = mtasaDuplicates($db);

	if (isset($chosen['duplicates'])) {
		$wantCopies = $chosen['duplicates'] === 'copies';
		$ids = array_values(array_filter($ids, fn($id) => ($duplicates['root'][$id] !== $id) === $wantCopies));
	}

	if (!empty($columns[$sort]['derived'])) {
		$value = fn($id) => $duplicates['root'][$id] !== $id ? $duplicates['root'][$id] : null;
		usort($ids, function ($x, $y) use ($value, $dir) {
			$a = $value($x);
			$b = $value($y);
			if (($a === null) !== ($b === null)) {
				return $a === null ? 1 : -1;
			}
			$compare = $dir === 'asc' ? $a <=> $b : $b <=> $a;

			return $compare !== 0 ? $compare : $x <=> $y;
		});
	}

	$total = count($ids);
	$pages = max(1, (int)ceil($total / $per));
	$page = min(max(1, $page), $pages);
	$pageIds = array_slice($ids, ($page - 1) * $per, $per);

	$rows = [];
	if ($pageIds) {
		$byId = [];
		foreach ($db->query(
			"SELECT m.*, a.*, m.id AS id, " . MTASA_RACE_MODE_SQL . " AS race_mode FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE m.id IN (" . implode(', ', $pageIds) . ")"
		)->fetchAll() as $row) {
			$byId[(int)$row['id']] = $row;
		}
		$counts = mtasaOpenThreadCounts($db, $pageIds);
		foreach ($pageIds as $id) {
			$row = $byId[$id];
			$row['original'] = $duplicates['root'][$id] !== $id ? $duplicates['root'][$id] : null;
			$row['open_threads'] = $counts[$id] ?? 0;
			$rows[] = $row;
		}
	}

	return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per, 'sort' => $sort, 'dir' => $dir];
}

/// One map with both halves, or null.
function mtasaMap($db, $id) {
	return $db->query(
		"SELECT m.*, a.*, m.id AS id, " . MTASA_RACE_MODE_SQL . " AS race_mode FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id WHERE m.id = ?",
		[$id]
	)->fetch() ?: null;
}

/// Open threads per map, for the given ids.
function mtasaOpenThreadCounts($db, $ids) {
	if (!$ids) {
		return [];
	}

	$counts = [];
	foreach ($db->query(
		"SELECT map_id, COUNT(*) n FROM map_comment WHERE parent_id IS NULL AND resolved_at IS NULL AND map_id IN (" . implode(', ', array_map('intval', $ids)) . ") GROUP BY map_id"
	)->fetchAll() as $row) {
		$counts[(int)$row['map_id']] = (int)$row['n'];
	}

	return $counts;
}

/// The address of the list with these filters, sort, page and page size,
/// leaving out whatever is at its default so a plain link stays plain.
function mtasaListUrl($chosen, $sort, $dir, $page = 1, $per = MTASA_PAGE_SIZE) {
	/// An empty choice means "every map" against a filter's default, so it is written as name[]=.
	$query = array_map(fn($value) => $value === [] ? [''] : $value, $chosen);
	if ($sort !== 'id' || $dir !== 'asc') {
		$query['sort'] = $sort;
		$query['dir'] = $dir;
	}
	if ($page > 1) {
		$query['page'] = $page;
	}
	if ($per !== MTASA_PAGE_SIZE) {
		$query['per'] = $per;
	}
	$text = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

	/// PHP numbers array keys (status[0]=…); the brackets alone read back the same.
	return '/mtasa' . ($text === '' ? '' : '?' . preg_replace('/%5B\d+%5D/', '%5B%5D', $text));
}
