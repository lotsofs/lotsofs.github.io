<?php

$url = "https://fer.eltrick.uk/latest?base=EUR";
$cacert_pem = "secure/cacert.pem"; // https://curl.se/ca

// OPENSSL:
// $exchangeRatesJson = file_get_contents($url);

// CURL:
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_CAINFO, $cacert_pem);
$exchangeRatesJson = curl_exec($ch);

if (!isset($exchangeRatesJson) || empty($exchangeRatesJson)) {
	$exchangeRatesJson = "{ \"date\":\"2025-05-03\",\"base\":\"EUR\",\"rates\":{}}";
}
