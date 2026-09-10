<?php require(__MAIN__ . '/views/partials/head.php') ?>

<?php require(__MAIN__ . '/views/partials/nav.php') ?>

<h1>
	Currency Exchange Rates
</h1>
<p>
	Data last updated on <span id=exchangeRatesUpdateDate>1970-01-01</span>
</p>
<table>
	<thead>
		<tr>
			<th>Alpha</th>
			<th>Numeric</th>
			<th>Currency Name</th>
			<th>Example Location</th>
			<th>Rate (from EUR)</th>
			<th>Rate (to EUR)</th>
			<th>Calculate</th>
		</tr>
	</thead>
	<tbody id="exchangeRates">

	</tbody>
</table>
<p id="exchangeRateReadError">
</p>

<script src="modules/main/js/exchangeRates.js"></script>
<script>
	processExchangeRates()
</script>

<?php require(__MAIN__ . '/views/partials/foot.php') ?>
