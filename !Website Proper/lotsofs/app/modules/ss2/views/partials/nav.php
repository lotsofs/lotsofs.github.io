<div class="header">
	<div class="imgbox">
		<img src="/modules/ss2/img/Bomb_logo.png" />
	</div>
	<div class="bignavbox">
		<div class="titlebox">
			<h1>Serious Compendium 2</h1>
		</div>
		<div class="navbox">
			<nav>
				<a href="/ss2/" class="<?= urlIs("/ss2/") ? "navCurrent" : "" ?>">General</a>
				<a href="/ss2/1" class="<?= urlStartsWith("/ss2/1") ? "navCurrent" : "" ?>">M'Digbo</a>
				<a href="/ss2/2" class="<?= urlStartsWith("/ss2/2") ? "navCurrent" : "" ?>">Magnor</a>
				<a href="/ss2/3" class="<?= urlStartsWith("/ss2/3") ? "navCurrent" : "" ?>">Chi-Fang</a>
				<a href="/ss2/4" class="<?= urlStartsWith("/ss2/4") ? "navCurrent" : "" ?>">Kleer</a>
				<a href="/ss2/5" class="<?= urlStartsWith("/ss2/5") ? "navCurrent" : "" ?>">Ellenier</a>
				<a href="/ss2/6" class="<?= urlStartsWith("/ss2/6") ? "navCurrent" : "" ?>">Kronor</a>
				<a href="/ss2/7" class="<?= urlStartsWith("/ss2/7") ? "navCurrent" : "" ?>">Sirius</a>
			</nav>
		</div>
		<div class="subnavbox">
			<?php if (urlStartsWith("/ss2/1")): ?>
				<nav>
					<a href="/ss2/11" class="<?= urlIs("/ss2/11") ? "navCurrent" : "" ?>">Jungle</a>
					<a href="/ss2/12" class="<?= urlIs("/ss2/12") ? "navCurrent" : "" ?>">Riverdance</a>
					<a href="/ss2/13" class="<?= urlIs("/ss2/13") ? "navCurrent" : "" ?>">M'keke Village</a>
					<a href="/ss2/14" class="<?= urlIs("/ss2/14") ? "navCurrent" : "" ?>">Road to Ursul</a>
					<a href="/ss2/15" class="<?= urlIs("/ss2/15") ? "navCurrent" : "" ?>">Ursul Suburbs</a>
					<a href="/ss2/16" class="<?= urlIs("/ss2/16") ? "navCurrent" : "" ?>">Kukulele Prison</a>
					<a href="/ss2/17" class="<?= urlIs("/ss2/17") ? "navCurrent" : "" ?>">Ursul Gardens</a>
					<a href="/ss2/18" class="<?= urlIs("/ss2/18") ? "navCurrent" : "" ?>">Kwongo</a>
				</nav>
			<?php elseif (urlStartsWith("/ss2/2")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php elseif (urlStartsWith("/ss2/3")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php elseif (urlStartsWith("/ss2/4")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php elseif (urlStartsWith("/ss2/5")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php elseif (urlStartsWith("/ss2/6")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php elseif (urlStartsWith("/ss2/7")): ?>
				<nav>
					<p>NO SUBNAV BUILT YET</p>
				</nav>
			<?php endif; ?>
		</div>
	</div>
</div>