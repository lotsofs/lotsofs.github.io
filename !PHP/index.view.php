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
    <table>
        <thead>
            <tr>
                <th>Alpha</th>
                <th>Numeric</th>
                <th>Currency Name</th>
                <th>Rate (from EUR)</th>
                <th>Rate (to EUR)</th>
            </tr>
        </thead>
        <tbody id="foreignExchangeRates">

        </tbody>
    </table>

    <script>
        const exchangeRates = <?= $exchangeRatesJson ?>;
        
        let iso4217 = [];
        
        fetch('iso4217.json')
            .then(response => response.json())
            .then(data => {
                iso4217 = data;
                processExchangeRates();
            })
            .catch(error => console.error("Error loading currency data (ISO 4217)", error));

        function processExchangeRates() {
            const table = document.getElementById("foreignExchangeRates");
            for (const [currency, rate] of Object.entries(exchangeRates.rates)) {
                const row = document.createElement('tr');
                let isoInfo = iso4217.find(item => item.alphabeticCode === currency);
                if (!isoInfo) {
                    isoInfo = {
                        "alphabeticCode": "", 
                        "numericCode": "", 
                        "minorUnit":-1, 
                        "entity":"<Unknown>",
                    }
                }

                const alphabeticCell = document.createElement('td');
                alphabeticCell.textContent = currency;
                row.appendChild(alphabeticCell);
                
                const numericCell = document.createElement('td');
                numericCell.textContent = isoInfo.numericCode;
                row.appendChild(numericCell);
                
                const entityCell = document.createElement('td');
                entityCell.textContent = isoInfo.entity;
                row.appendChild(entityCell);
                
                const rateCell = document.createElement('td');
                rateCell.textContent = rate.toFixed(10);
                row.appendChild(rateCell);
                
                const inverseRateCell = document.createElement('td');
                inverseRateCell.textContent = (1/rate).toFixed(10);
                row.appendChild(inverseRateCell);
                
                table.appendChild(row);
            }
        }
        

    </script>

</body>
</html>