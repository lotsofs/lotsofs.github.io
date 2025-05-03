<?php

$url = "https://fer.eltrick.uk/latest?base=EUR";
$exchangeRatesJson = file_get_contents($url);

// TODO: this can also be done client side using fetch


require "index.view.php";