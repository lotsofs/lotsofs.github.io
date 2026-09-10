const PATH_ISO4217 = "modules/main/json/iso4217.json";
const PATH_EXCHANGE_RATES = "modules/main/json/exchangeRates.json";

const ELEMENT_CURRENCY_TABLE_ID = "exchangeRates";
const ELEMENT_CURRENCY_INPUT_FIELD_ID = "currencyExchangeRate_calcBox";
const ELEMENT_DATE_ID = "exchangeRatesUpdateDate";

const RATE_DISPLAY_DECIMALS = 5;

const EMPTY_ISO_ENTRY = {
	"alphabeticCode": "", 
	"numericCode": "", 
	"minorUnit": -1, 
	"withdrawalDate": "",
	"currency": "<Unknown>",
	"entity": "",
}

let currencyWhiteList = false

let exchangeRates;
let iso4217;
let sortedExchangeRates;

// =========
// READ DATA
// =========

async function processExchangeRates() {
	exchangeRates = await readExchangeRates();
	iso4217 = await readIso4217();
	sortedExchangeRates = getSortedExchangeRates();
	
	updateInfo();

	displayExchangeRates();
}

// Read exchange rates data from json
async function readExchangeRates() {
	const response = await fetch(PATH_EXCHANGE_RATES);
	if (!response.ok) throw new Error("Failed to load exchange rates");
	return await response.json();
}

// Read iso 4217 data from json
async function readIso4217() {
	const response = await fetch(PATH_ISO4217);
	if (!response.ok) throw new Error("Failed to load iso 4217");
	return await response.json();
}

// takes the ISO4217 list, exchange rates list, mergess the & returns sorted
function getSortedExchangeRates() {
	iso4217.forEach(item => {
		if (!exchangeRates.rates.hasOwnProperty(item.alphabeticCode)) {
			exchangeRates.rates[item.alphabeticCode] = -1
		}
	})
	const keys = Object.keys(exchangeRates.rates).sort();
	const result = {};
	keys.forEach(key => {
		result[key] = exchangeRates.rates[key];
	});
	return result;
}

// =====================
// ASSEMBLING HTML TABLE
// =====================

// Writes the date to the appropriate span element
function updateInfo() {
	const dateElement = document.getElementById(ELEMENT_DATE_ID);
	if (dateElement) {
		dateElement.textContent = exchangeRates.date;
	}
}

// Loop through all the exchange rates (applies whitelist if present)
// and adds them to the html table
function displayExchangeRates() {
	const tableElement = document.getElementById(ELEMENT_CURRENCY_TABLE_ID)
	if (currencyWhiteList) {
		currencyWhiteList.forEach(alphaCode => {
			let rate = sortedExchangeRates[alphaCode];
			if (!rate) {
				return;
			}
			addCurrencyTableRow(tableElement, alphaCode, rate);
		});
	}
	else {
		for (const [alphaCode, rate] of Object.entries(sortedExchangeRates)) {
			addCurrencyTableRow(tableElement, alphaCode, rate);
		}
	}
}

// Add a currency table row to the html table
function addCurrencyTableRow(tableElement, alphaCode, rate) {
	const isoInfo = getIso4217Entry(alphaCode)
		
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

// Find ISO4217 entry for this alpha code
function getIso4217Entry(alphaCode) {
	// Look for relevant entry
	let entry = iso4217.find(item => item.alphabeticCode === alphaCode && item.withdrawalDate.trim() === "")
	if (entry) {
		return entry
	}
	// Look for obsolete entry
	entry = iso4217.find(item => item.alphabeticCode === alphaCode)
	if (entry) {
		return entry
	}
	// Return blank entry
	return EMPTY_ISO_ENTRY
}

// =============
// LIVE UPDATING
// =============

// If a calcbox is changed, also update the other ones
function calculateConversions(fromCalcBox) {
	const fromAlphaCode = fromCalcBox.id.slice(-3)
	const fromRateInverse = 1/exchangeRates.rates[fromAlphaCode]
	const fromValue = fromCalcBox.value

	const baseAlphaCode = exchangeRates.base;
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
		const toRate = exchangeRates.rates[toAlphaCode]
		const toCalcBox = document.getElementById(`calc-${toAlphaCode}`)
		const toIsoInfo = iso4217.find(item => item.alphabeticCode === toAlphaCode && item.minorUnit >= 0)
		const toMinorUnit = toIsoInfo ? toIsoInfo.minorUnit : 2
		const toValue = baseValue * toRate
		toCalcBox.value = toValue.toFixed(toMinorUnit)
	})
}