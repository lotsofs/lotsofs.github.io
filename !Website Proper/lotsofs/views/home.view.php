<?php require('views/partials/head.php') ?>

<?php require('views/partials/nav.php') ?>

<h1>
	Main Page
</h1>
<div id="frontPage_exchangeRates">
	<h2>
		Exchange Rates
	</h2>
	<p>
		Exchange rates for commonly used currencies. (Based on EUR)
	</p>

	<table>
		<thead>
			<tr>
				<th>Alpha</th>
				<th>Numeric</th>
				<th>Currency Name</th>
				<th>Example Location</th>
				<th>Rate</th>
				<th>Rate (to EUR)</th>
				<th>Calculate</th>
			</tr>
		</thead>
		<tbody id="exchangeRates">
			
		</tbody>
	</table>
	<p id="exchangeRateReadError">
	</p>
	<p>
		<a href="exchangeRates">More</a>
	</p>
</div>

<p>
	There currently isn't much here yet. Did you mean to do one of the following?:
	<ul>
		<li>Access my Keep Talking and Nobody Explodes <a class="links-link" href="ktane">merged translated modules manuals w/ bonus languages</a>.</li>
	</ul>
</p>

<script src="js/exchangeRates.js"></script>
<script>
	currencyWhiteList = ["CHF", "DKK", "EUR", "GBP", "IDR", "MYR", "NPR", "SEK", "TRY", "USD"]
	processExchangeRates(<?= $exchangeRatesJson ?>)
</script>

<?php require('views/partials/foot.php') ?>
