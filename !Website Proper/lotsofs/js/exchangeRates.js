const ISO4217_PATH = "json/iso4217.json"

const RATE_DISPLAY_DECIMALS = 5
const ELEMENT_CURRENCY_TABLE_ID = "exchangeRates"
const ELEMENT_CURRENCY_INPUT_FIELD_ID = "currencyExchangeRate_calcBox"

const EMPTY_ISO_ENTRY = {
	"alphabeticCode": "", 
	"numericCode": "", 
	"minorUnit": -1, 
	"withdrawalDate": "",
	"currency": "<Unknown>",
	"entity": "",
}

let currencyWhiteList = false

// const exchangeRates = <?= $exchangeRatesJson ?>
// let iso4217 = [] // {"alphabeticCode": "AAA", "numericCode": "000", "minorUnit":0, "withdrawalDate":"","currency":"Name","entity":"COUNTRY"},

let exchangeRatesUpdateDate = "1970-01-01"
let baseAlphaCode = ""
let exchangeRates = {}
let iso4217 = []

function processExchangeRates(data) {
	// exchangeRates = rates
	if (!data) {
		const errorElement = document.getElementById(exchangeRateReadError)
		exchangeRateReadError.textContent = "Failed to get exchange rates."
		return
	}
	
	exchangeRatesUpdateDate = data.date
	baseAlphaCode = data.base
	exchangeRates = data.rates
	readIso4217()
	updateLabels()
}

function updateLabels() {
	const dateElement = document.getElementById(exchangeRatesUpdateDate);
	if (dateElement) {
		dateElement.textContent = exchangeRatesUpdateDate;
	}
}

function readIso4217() {
	fetch(ISO4217_PATH)
	.then(response => response.json())
	.then(data => {
		iso4217 = data
		displayExchangeRates()
	})
	.catch(error => console.error("Error loading currency data (ISO 4217)", error))
}

function getSortedExchangeRates() {
	// This takes the ISO4217 list, exchange rates list, merges them & returns sorted
	iso4217.forEach(item => {
		if (!exchangeRates.hasOwnProperty(item.alphabeticCode)) {
			exchangeRates[item.alphabeticCode] = -1
		}
	})
	return Object.entries(exchangeRates).sort(([a],[b]) => a.localeCompare(b))
}

function getIsoEntry(alphaCode) {
	let entry = iso4217.find(item => item.alphabeticCode === alphaCode && item.withdrawalDate.trim() === "")
	if (entry) {
		return entry
	}
	entry = iso4217.find(item => item.alphabeticCode === alphaCode)
	if (entry) {
		return entry
	}
	return EMPTY_ISO_ENTRY
}

function appendChildToElement(parent, childTag, childTextContent) {
	const element = document.createElement(childTag)
	element.textContent = childTextContent
	parent.appendChild(element)
	return element
}

function addCurrencyTableRow(tableElement, alphaCode, rate) {
	const isoInfo = getIsoEntry(alphaCode)
		
	const rowElement = appendChildToElement(tableElement, "tr", "")

	appendChildToElement(rowElement, "td", alphaCode)
	appendChildToElement(rowElement, "td", isoInfo.numericCode)
	appendChildToElement(rowElement, "td", isoInfo.currency)
	appendChildToElement(rowElement, "td", isoInfo.entity)
	appendChildToElement(rowElement, "td", rate == -1 ? "" : rate.toFixed(RATE_DISPLAY_DECIMALS))
	appendChildToElement(rowElement, "td", rate == -1 ? "" : (1/rate).toFixed(RATE_DISPLAY_DECIMALS))
	
	const inputCellElement = appendChildToElement(rowElement, "td", "")
	if (rate != -1) {
		const inputFieldElement = appendChildToElement(inputCellElement, "input", "")
	
		inputFieldElement.id = `calc-${alphaCode}`
		inputFieldElement.classList.add(ELEMENT_CURRENCY_INPUT_FIELD_ID)
		inputFieldElement.type = "number"
		inputFieldElement.value = 0
		inputFieldElement.addEventListener('input', () => {
			calculateConversions(inputFieldElement)
		})
	}	
}

function displayExchangeRates() {
	const sortedRates = getSortedExchangeRates()

	const tableElement = document.getElementById(ELEMENT_CURRENCY_TABLE_ID)
	if (currencyWhiteList) {
		currencyWhiteList.forEach(alphaCode => {
			let rate = sortedRates.find(item => item[0] == alphaCode)
			if (!rate) {
				return
			}
			rate = rate[1]
			addCurrencyTableRow(tableElement, alphaCode, rate)
		});
	}
	else {
		for (const [alphaCode, rate] of sortedRates) {
			addCurrencyTableRow(tableElement, alphaCode, rate)
		}
	}
}

function calculateConversions(fromCalcBox) {
	const fromAlphaCode = fromCalcBox.id.slice(-3)
	const fromRateInverse = 1/exchangeRates[fromAlphaCode]
	const fromValue = fromCalcBox.value

	const baseCalcBox = document.getElementById(`calc-${baseAlphaCode}`)
	const baseIsoInfo = iso4217.find(item => item.alphabeticCode === baseAlphaCode && item.minorUnit >= 0)
	const baseMinorUnit = baseIsoInfo ? baseIsoInfo.minorUnit : 2
	const baseValue = fromValue * fromRateInverse
	baseCalcBox.value = baseValue.toFixed(baseMinorUnit)
	
	document.querySelectorAll(".currencyExchangeRate_calcBox").forEach(input => {
		if (input == fromCalcBox || input == baseCalcBox) {
			return
		}
		const toAlphaCode = input.id.slice(-3)
		const toRate = exchangeRates[toAlphaCode]
		const toCalcBox = document.getElementById(`calc-${toAlphaCode}`)
		const toIsoInfo = iso4217.find(item => item.alphabeticCode === toAlphaCode && item.minorUnit >= 0)
		const toMinorUnit = toIsoInfo ? toIsoInfo.minorUnit : 2
		const toValue = baseValue * toRate
		toCalcBox.value = toValue.toFixed(toMinorUnit)
	})
}