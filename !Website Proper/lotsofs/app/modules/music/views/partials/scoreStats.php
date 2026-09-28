<?php
	/* Per-rater statistics table, shared by the album and artist cards. Takes
	   $statsRows, $statsTotals, $statsColours, $statsDenominator. */
	require_once __MODULES__ . '/music/views/partials/scorePresentation.php';
?>
<dt class="albumStatsHeading"><?= htmlspecialchars(t('album.card.averages')) ?></dt>
<dd class="albumStatsCell">
	<table class="albumStatsTable">
		<thead>
			<tr>
				<th class="albumStatsNameCell"><?= htmlspecialchars(t('album.card.statRater')) ?></th>
				<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statAverage')) ?></th>
				<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statDeviation')) ?></th>
				<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMedian')) ?></th>
				<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMode')) ?></th>
				<th class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.statRated')) ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($statsRows as $statsRow): ?>
				<tr class="albumStatsRow" data-account-id="<?= (int)$statsRow['id'] ?>">
					<td class="albumStatsNameCell"><?php if ((int)$statsRow['rated'] > 0): ?><span class="albumStatsSwatch" style="background-color: <?= $statsColours[(int)$statsRow['id']] ?>"></span><?php endif ?><?= htmlspecialchars($statsRow['account_name']) ?></td>
					<?= scoreStatCells($statsRow) ?>
					<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)$statsRow['rated'], 'total' => $statsDenominator])) ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
		<?php if ($statsTotals !== null): ?>
			<tfoot>
				<tr class="albumStatsRow albumStatsTotalRow">
					<td class="albumStatsNameCell"><?= htmlspecialchars(t('album.card.statEveryone')) ?></td>
					<?= scoreStatCells($statsTotals) ?>
					<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)$statsTotals['rated'], 'total' => (int)$statsTotals['possible']])) ?></td>
				</tr>
			</tfoot>
		<?php endif ?>
	</table>
</dd>
