<?php

return [

	'nav.addSongs' => 'Add Songs',
	'nav.songs' => 'Songs',
	'nav.artists' => 'Artists',
	'nav.albums' => 'Albums',
	'nav.audit' => 'History',
	'nav.changes' => 'What\'s New',
	'nav.invites' => 'Invites',
	'nav.accounts' => 'Accounts',
	'nav.login' => 'Log In',
	'nav.register' => 'Register',
	'nav.logout' => 'Log Out',
	'nav.language' => 'Language',
	'nav.colour' => 'Colour',
	'nav.settings' => 'Settings',
	'settings.blindRating' => 'Hide other people\'s scores and notes on a song until I have rated it myself',
	'settings.toastDuration' => 'How long rating pop-ups stay',
	'settings.toastOff' => 'Off',
	'settings.toastSeconds' => '{count} seconds',
	'settings.toastMinute' => '1 minute',
	'settings.toastMinutes' => '{count} minutes',
	'settings.toastUntilClosed' => 'Until I close them',
	'settings.save' => 'Save',
	'colour.hue' => 'Site colour',
	'colour.save' => 'Use this colour',

	'alias.add' => 'Write in another name; it is saved when you leave the box or press Enter',
	'alias.addPlaceholder' => 'Another name',
	'alias.use' => 'Use this name',
	'alias.delete' => 'Delete this name',
	'alias.deleteConfirm' => 'Delete the name "{name}"?',
	'alias.deleteAndRelist' => 'Delete it, and list the song under its current title there',
	'alias.result.listed' => 'Listed under this name on: {albums}',
	'alias.result.current' => '❌ The current name can\'t be deleted; use another name first',
	'alias.result.notFound' => '❌ That name is already gone',
	'alias.result.required' => '❌ A name can\'t be empty',
	'alias.result.duplicate' => '✅ That is already one of its names',

	'language.en' => 'English',
	'language.de' => 'German',
	'language.fy' => 'West Frisian',

	'music.title' => 'Music',

	'addSongs.title' => 'Add Songs',
	'addSongs.heading' => 'Add Songs',
	'addSongs.pastePlaceholder' => "Paste rows separated by tabs, one song per line:\nDream Theater\tBridges in the Sky\tBlack Clouds & Silver Linings\t3\nParamore\tThe Only Exception",

	'addSongs.column.ignore' => 'Ignore',
	'addSongs.column.track' => 'Track #',

	'addSongs.columnMapping.heading' => 'What does each column mean?',
	'addSongs.columnMapping.columnLabel' => 'Column {n}',
	'addSongs.columnMapping.continue' => 'Continue',
	'addSongs.columnMapping.needsArtistAndTitle' => 'Pick one column for Artist and one for Title before continuing.',
	'addSongs.separateAll' => 'Add every song whose title its artist already has as a separate song',
	'addSongs.separateRelation' => 'Each separate song is the {relation} the song it shares its title with.',

	'ajax.notLoggedIn' => 'You need to be logged in.',
	'ajax.badCsrf' => 'That page expired, please reload it and try again.',
	'ajax.notAdmin' => 'That needs an admin account.',
	'ajax.badMethod' => 'That endpoint only accepts POST.',
	'ajax.badBody' => 'That request body was not valid JSON.',

	'status.submitting' => 'Submitting...',
	'status.submitFailed' => 'Submit failed: {error}',
	'status.nextStepFailed' => 'That step succeeded, but the next one could not be built: {error}',
	'status.noResult' => '❓ No result came back for this row',

];
