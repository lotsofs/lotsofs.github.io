<?php require('views/partials/head.php') ?>

<?php require('views/partials/nav.php') ?>

<h1>
	Currency Exchange Rates
</h1>
<p>
	Data last updated on <span id=exchangeRatesUpdateDate>2025-05-02</span>
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

<script src="js/exchangeRates.js"></script>
<script>
	processExchangeRates(<?= $exchangeRatesJson ?>)
</script>

<?php require('views/partials/foot.php') ?>
