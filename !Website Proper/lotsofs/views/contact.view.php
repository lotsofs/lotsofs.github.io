<?php require('views/partials/head.php') ?>

<?php require('views/partials/nav.php') ?>

<h1>
	Contact / Link Dump
</h1>

<ul>
	<ul>Twitch:</b> <a href="http://www.twitch.tv/lotsofs"">LotsOfS</a></ul>
	<ul>YouTube (Main):</b> <a href="http://www.youtube.com/lotsofs"">LotsOfS</a></ul>
	<ul>YouTube (Vods):</b> <a href="http://www.youtube.com/channel/UCtxG61DoSAcbgA6q3Vm3JDA"">VodsOfS</a></ul>
	<ul>Discord:</b> <a href="http://www.discord.gg/lotsofs"">discord.gg/lotsofs</a></ul>
	<ul>Instagram:</b> <a href="http://www.instagram.com/lotsofs"">LotsOfS</a></ul>
	<ul>TikTok:</b> <a href="https://www.tiktok.com/@lotsofsierra"">@lotsofsierra</a></ul>
	<ul>Twitter:</b> <a href="http://www.twitter.com/lotsofs"">LotsOfS</a></ul>
	<ul>BlueSky:</b> <a href="https://bsky.app/profile/lotsofs.bsky.social"">lotsofs@bsky.social</a></ul>
	<ul>Website:</b> <a href="https://www.lotsofs.com"">https://www.lotsofs.com</a></ul>
	<ul>SpeedRunRecords:</b> <a href="https://www.speedrun.com/user/S."">S.</a></ul>
	<ul>Stream Elements:</b> <a href="https://streamelements.com/lotsofs/tip"">lotsofs</a></ul>
	<ul>E-Mail:</b> <span style=unicode-bidi:bidi-override;direction:rtl;user-select:none>moc.sfostol@seiriuqni</span>
</ul>

<script>
	currencyWhiteList = ["CHF", "DKK", "EUR", "GBP", "IDR", "NOK", "SEK", "TRY", "USD", "VND"]
	processExchangeRates(<?= $exchangeRatesJson ?>)
</script>

<?php require('views/partials/foot.php') ?>
