<?php

require_once __MODULES__ . '/music/hue.php';
require_once __MODULES__ . '/music/format.php';

/// How scores are coloured and laid out wherever a card shows them.

/// The 0-10 red-to-green ramp, as an inline style. Mirrored in songs.js.
function scoreColourAttr($value) {
	return ' style="color: hsl(' . round(max(0, min(10, (float)$value)) * 12, 2) . ', 75%, 55%)"';
}

/// Rater id => that rater's own hue, so a colour belongs to the person.
function scoreRaterColours($rows) {
	$colours = [];

	foreach ($rows as $row) {
		$colours[(int)$row['id']] = 'hsl(' . (int)($row['hue'] ?? MUSIC_HUE_DEFAULT) . ', 70%, 62%)';
	}

	return $colours;
}

/// The named statistic cells of any row carrying a musicScoreStats() result.
function scoreStatCells($row, $keys = ['average', 'deviation', 'median', 'mode']) {
	$dash = htmlspecialchars(t('album.card.noAverage'));
	$unrated = (int)($row['rated'] ?? 0) === 0;

	$cells = '';

	foreach ($keys as $key) {
		if ($unrated) {
			$cells .= '<td class="albumStatsScoreCell albumStatsEmpty">' . $dash . '</td>';
			continue;
		}

		if ($key === 'deviation') {
			$cells .= '<td class="albumStatsScoreCell albumStatsDeviationCell">' . htmlspecialchars(scoreText($row['deviation'])) . '</td>';
			continue;
		}

		if ($key === 'mode') {
			/// Each tied mode is coloured on its own.
			$modeHtml = '';
			foreach ($row['modes'] ?? [] as $mode) {
				$modeHtml .= ($modeHtml === '' ? '' : ', ')
					. '<span' . scoreColourAttr($mode) . '>' . htmlspecialchars(scoreText($mode)) . '</span>';
			}

			$cells .= '<td class="albumStatsScoreCell' . ($modeHtml === '' ? ' albumStatsEmpty' : '') . '">' . ($modeHtml === '' ? $dash : $modeHtml) . '</td>';
			continue;
		}

		$cells .= '<td class="albumStatsScoreCell"' . scoreColourAttr($row[$key]) . '>' . htmlspecialchars(scoreText($row[$key])) . '</td>';
	}

	return $cells;
}

/// The whose-scores control: everyone pooled, or one rater with something here.
function scoreWhoSelect($raters, $selected) {
	if (!$raters) {
		return '';
	}

	$html = '<div class="cardStatsWhoRow">'
		. '<label for="cardStatsWho">' . htmlspecialchars(t('album.card.statsWho')) . '</label>'
		. '<select id="cardStatsWho" class="cardStatsWho cardWheelSelect">'
		. '<option value=""' . ($selected === null ? ' selected' : '') . '>' . htmlspecialchars(t('album.card.statEveryone')) . '</option>';

	foreach ($raters as $rater) {
		$id = (int)$rater['id'];
		$html .= '<option value="' . $id . '"' . ($selected === $id ? ' selected' : '') . '>' . htmlspecialchars($rater['account_name']) . '</option>';
	}

	return $html . '</select></div>';
}

/// The name to head a column of one person's scores with.
function scoreWhoName($raters, $selected) {
	foreach ($raters as $rater) {
		if ((int)$rater['id'] === (int)$selected) {
			return $rater['account_name'];
		}
	}

	return '';
}

/// Only the raters who have scored something here.
function scoreActiveRaters($rows) {
	$active = [];

	foreach ($rows as $row) {
		if ((int)$row['rated'] > 0) {
			$active[] = $row;
		}
	}

	return $active;
}
