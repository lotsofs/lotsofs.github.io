<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
</head>
<body>
	<h1>
		Currency Exchange Rates
	</h1>
	<p>
		Data last updated on 2025-05-02
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
		<tbody id="foreignExchangeRates">

		</tbody>
	</table>

	<script>
		const exchangeRates = <?= $exchangeRatesJson ?>
		
		let iso4217 = []
		
		fetch('iso4217.json')
			.then(response => response.json())
			.then(data => {
				iso4217 = data
				processExchangeRates()
			})
			.catch(error => console.error("Error loading currency data (ISO 4217)", error))

		function calculateConversions(fromCalcBox) {
			const fromAlphaCode = fromCalcBox.id.slice(-3)
			const eurCalcBox = document.getElementById("calc-EUR")
			const fromRateI = document.getElementById(`rateI-${fromAlphaCode}`).textContent
			const fromIsoInfo = iso4217.find(item => item.alphabeticCode === fromAlphaCode && item.minorUnit >= 0)
			const eurExch = fromCalcBox.value * fromRateI
			eurCalcBox.value = eurExch.toFixed(fromIsoInfo ? fromIsoInfo.minorUnit : "2")
			document.querySelectorAll(".currencyExchangeRate_calcBox").forEach(input => {
				if (input == fromCalcBox || input == eurCalcBox) {
					return
				}
				const toAlphaCode = input.id.slice(-3)
				const toRate = document.getElementById(`rate-${toAlphaCode}`).textContent
				const toCalcBox = document.getElementById(`calc-${toAlphaCode}`)
				const toIsoInfo = iso4217.find(item => item.alphabeticCode === toAlphaCode && item.minorUnit >= 0)
				const toExch = eurExch * toRate
				toCalcBox.value = toExch.toFixed(toIsoInfo ? toIsoInfo.minorUnit : "2")
			})
		}

		function processExchangeRates() {
			const table = document.getElementById("foreignExchangeRates")
			iso4217.forEach(item => {
				if (!exchangeRates.rates.hasOwnProperty(item.alphabeticCode)) {
					exchangeRates.rates[item.alphabeticCode] = -1
				}
			})
			const sortedRates = Object.entries(exchangeRates.rates).sort(([a],[b]) => a.localeCompare(b))


			for (const [alphaCode, rate] of sortedRates) {
				const row = document.createElement('tr')
				let isoInfo = iso4217.find(item => item.alphabeticCode === alphaCode && item.withdrawalDate.trim() === "")
				if (!isoInfo) {
					isoInfo = iso4217.find(item => item.alphabeticCode === alphaCode)
					if (!isoInfo) {
						isoInfo = {
							"alphabeticCode": "", 
							"numericCode": "", 
							"minorUnit":-1, 
							"withdrawalDate":"",
							"currency":"<Unknown>",
							"entity":"",
						}
					}
				}

				const alphabeticCell = document.createElement('td')
				alphabeticCell.textContent = alphaCode
				row.appendChild(alphabeticCell)
				
				const numericCell = document.createElement('td')
				numericCell.textContent = isoInfo.numericCode
				row.appendChild(numericCell)
				
				const currencyCell = document.createElement('td')
				currencyCell.textContent = isoInfo.currency
				row.appendChild(currencyCell)
				
				const entityCell = document.createElement('td')
				entityCell.textContent = isoInfo.entity
				row.appendChild(entityCell)
				
				const rateCell = document.createElement('td')
				rateCell.textContent = rate == -1 ? "" : rate.toFixed(10)
				rateCell.id = `rate-${alphaCode}`
				row.appendChild(rateCell)
				
				const inverseRateCell = document.createElement('td')
				inverseRateCell.textContent = rate == -1 ? "" : (1/rate).toFixed(10) 
				inverseRateCell.id = `rateI-${alphaCode}`
				row.appendChild(inverseRateCell)
				
				const calculateCell = document.createElement('td')
				if (rate != -1) {
					const calcInput = document.createElement('input')
					calcInput.id = `calc-${alphaCode}`
					calcInput.classList.add('currencyExchangeRate_calcBox')
					calcInput.type = "number"
					calcInput.addEventListener('input', () => {
						calculateConversions(calcInput)
					})
					
					calculateCell.appendChild(calcInput)
				}	
				row.appendChild(calculateCell)

				table.appendChild(row)
			}
		}
	</script>

</body>
</html>