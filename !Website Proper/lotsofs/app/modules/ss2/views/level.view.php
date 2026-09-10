<?php require(__MODULES__ . '/ss2/views/partials/head.php') ?>

<?php require(__MODULES__ . '/ss2/views/partials/nav.php') ?>

<div class="mainBody">
	<h2 id="mapName">MAP NAME</h2>
	<div id="psl">
		<h3>Plot, Story, Lore</h3>
		<p id="psl_p">Level .dsc file blurb</p>
		<div id="psl_div">
			<h4>MISSION OBJECTIVES</h4>
				<!-- Populated by script -->
		</div>
	</div>
	<div id="ccr">
		<h3>Chapter Completion Requirements</h3>
		<table id="ccr_table">
			<thead>
				<tr>
					<th>Ch.</th>
					<th>Official Description</th>
					<th>Completion Condition</th>
					<th>Security Timer</th>
					<th>Requires previous chapter completion?</th>
				</tr>
			</thead>
			<tbody id="ccr_tbody">
				<!-- Populated by script -->
			</tbody>
		</table>
	</div>
	<div id="waa">
		<h3>Weapons and Ammo</h3>
		<table id="waa_table">
			<thead>
				<tr id="waa_tr_head">
					<th>Weapon \ Chapter</th>
					<!-- Populated by script -->
				</tr>
			</thead>
			<tbody id="waa_tbody">
				<tr id="waa_tr_ratio">
					<th>Ammo Ratio</th>
					<!-- Populated by script -->
				</tr>
				<!-- Populated by script -->
			</tbody>
		</table>
		<p>Bold cells: Ammo is set by a fixed value for this chapter.</p>
		<p>Italic cells: Ammo is determined by the generic ammo ratio value of this chapter.</p>
		<p>Dashes: Impossible to get this ammo type at this point in the game.</p>
	</div>
	<div id="sat">
		<h3>Spawners & Timings</h3>
		<p>
			V2.090: 
			<input type="checkbox" id="sat_checkbox"></input>
			(Enemy spawners behave differently on this version due to a bug.)
		</p>
		<div id="satdiv">
			<!-- Populated by script -->
		</div>
		<table id="satTemplateTable">
			<thead>
				<tr>
					<th colspan="2" data-field="unitType"></th>
				</tr>
			</thead>
			<tbody>
				<tr class="fatterTopBorder"><th>Spawn type</th><td data-field="spawnType"></td></tr>
				<tr><th>Spawn effect configuration</th><td data-field="spawnEffectConfiguration"></td></tr>
				<tr><th>Launcher</th><td data-field="launcher"></td></tr>
				<tr><th>Spawn Formation</th><td data-field="spawnFormation"></td></tr>
				<tr class="fatterTopBorder"><th>Total number</th><td data-field="totalNumber"></td></tr>
				<tr><th>Number in group</th><td data-field="numberInGroup"></td></tr>
				<tr><th>Initial delay</th><td data-field="initialDelay"></td></tr>
				<tr><th>Initial delay from script</th><td data-field="initialDelayByScript"></td></tr>
				<tr><th>Other initial delays</th><td data-field="otherInitialDelay"></td></tr>
				<tr><th>Single delay</th><td data-field="singleDelay"></td></tr>
				<tr><th>Group delay</th><td data-field="groupDelay"></td></tr>
				<tr><th>Spawnee death delay</th><td data-field="spawneeDeathDelay"></td></tr>
				<tr class="fatterTopBorder"><th>Total spawntime</th><td data-field="totalSpawntime"></td></tr>
			</tbody>
		 </table>
	</div>
	<div id="ess">
		<h3>Editor Screenshots</h3>
		<div id="essdiv">
			<!-- Populated by script -->
		</div>
	</div>
	<div id="sbd">
		<h3>Score Breakdown</h3>
		<p>
			<span class="sbd_multiplierText">
				Enemy Multiplier: 
				<button class="sbd_button" id="sbd_button_l">◀</button>
				<span id="sbd_multiplierSpan">NONE</span>
				<button class="sbd_button" id="sbd_button_r">▶</button>
			</span>
			(Some spawners are not affected by multiplier, or can only be multiplied to a certain amount.)
		</p>
		<table id="sbd_table">
			<thead>
				<tr>
					<th colspan=4>Source</th>
					<th colspan=1>Kills</th>
					<th colspan=3>Score</th>
				</tr>
				<tr>
					<th>Ch.</th>
					<th>Area</th>
					<th>Count</th>
					<th>Type</th>
					<th>Cum. Kills</th>
					<th>Worth</th>
					<th>Total Worth</th>
					<th>Cum. Score</th>
				</tr>
			</thead>
			<tbody id="sbd_tbody">
				<!-- Populated by script -->
			</tbody>
		</table>
		<table id="sbd_endScreenTable">
			<tr>
				<th>Score:</th><td data-field="baseScore">#</td><th>Bonuses:</th>
			</tr>
			<tr>
				<th>Difficulty:</th><td data-field="difficultyName">Serious</td><td data-field="difficultyScore">10000</td>
			</tr>
			<tr>
				<th>Kills:</th><td data-field="killCount">#</td><td data-field="killScore"># * 10</td>
			</tr>
			<tr>
				<th>Secrets:</th><td data-field="secretCount">X/Y</td><td data-field="secretScore">1000/Y*X</td>
			</tr>
			<tr>
				<th>Lives:</th><td data-field="livesCount">L=floor(Total score / 10000)+3</td><td data-field="livesScore">L*1000</td>
			</tr>
			<tr>
				<th>Playtime:</th><td data-field="playtime">00:00:00</td><th>Time Bonus:</th>
			</tr>
			<tr>
				<th>Est. Time:</th><td data-field="estimatedTime">99:99:99</td><td data-field="timeScore">EstTime-Playtime*10 (in sec)</td>
			</tr>
			<tr>
				<th colspan="2">Total Bonus:</th><td data-field="totalBonus">#####</td>
			</tr>
			<tr>
				<th colspan="2">Total Score:</th><th data-field="totalScore">#####</th>
			</tr>
		</table>
	</div>
	<div id="map">
		<h3>Map</h3>
		<div id="mapSvgContainer">
			<!-- Populated by script -->
		</div>
	</div>
	<div id="mps">
		<h3>Macro Program Snippets</h3>
		<div id="mpsdiv">
			<!-- Populated by script -->
		</div>
	</div>
	<div id="popupToolTip">
		Sample Text
	</div>
</div>

<div id="levelInfo"
	data-id="<?= $levelId ?>"
></div>
<script type="module" src="/modules/ss2/js/level.js"></script>

<?php require(__MODULES__ . '/ss2/views/partials/foot.php') ?>
