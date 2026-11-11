<?php

/// Shown at /music/changes, newest first; a null date is the release not dated yet.
/// Each entry is keyed by locale with `en` required, and **text** is bold (docs/music.md).
/// One short line per entry, saying what changed and not how to use it.
/// One list per release, in page order: an entry users must act on goes first and opens in bold.
/// 'admin' => true marks what only admins can use, listed apart under For admins.

return [
	[
		'date' => null,
		'changes' => [],
	],
	[
		'date' => '2026-11-11',
		'changes' => [
			[
				'en' => 'Other names & versions is split into Other names, which also lists a version\'s names, and Remastered by and Remaster of after Links.',
				'fy' => 'Oare nammen & ferzjes is splitst yn Oare nammen, dy\'t ek de nammen fan in oare ferzje lit sjen, en Remastere as en Remaster fan nei Keppelings.',
				'de' => '„Andere Namen & Fassungen“ ist aufgeteilt in „Andere Namen“, das auch die Namen einer anderen Fassung zeigt, sowie „Remastert als“ und „Remaster von“ nach „Links“.',
			],
			[
				'en' => 'A song\'s YouTube player and link begin at the link\'s timestamp, if it has one.',
				'fy' => 'De YouTube-spiler en -keppeling fan in liet begjinne by de tiidstimpel fan de keppeling, as dy der is.',
				'de' => 'YouTube-Player und -Link eines Songs beginnen beim Zeitstempel des Links, falls er einen hat.',
			],
			[
				'en' => 'A pasted YouTube link keeps its timestamp; links pasted before lost theirs.',
				'fy' => 'In plakte YouTube-keppeling hâldt syn tiidstimpel; earder plakte keppelings binne harres kwyt.',
				'de' => 'Ein eingefügter YouTube-Link behält seinen Zeitstempel; früher eingefügte haben ihn verloren.',
				'admin' => true,
			],
			[
				'en' => 'Songs can be linked as a game version and its soundtrack release, or an alternate take and its main take, each with columns in the song list.',
				'fy' => 'Lieten kinne keppele wurde as spulferzje en soundtrackútjefte, of as alternative opname en haadopname, elk mei kolommen yn de side Lieten.',
				'de' => 'Songs lassen sich als Spielfassung und Soundtrack-Veröffentlichung oder als alternative Aufnahme und Hauptaufnahme verknüpfen, jeweils mit Spalten in der Songliste.',
			],
			[
				'en' => 'The song list can show when each rater last changed their rating, when a song was last changed and when it was added, and sort by each.',
				'fy' => 'De side Lieten kin sjen litte wannear\'t elke beoardieler syn wurdearring foar it lêst feroare hat, wannear\'t in liet foar it lêst feroare is en wannear\'t it tafoege is, en dêrop sortearje.',
				'de' => 'Die Songliste kann zeigen, wann jeder Bewertende seine Wertung zuletzt geändert hat, wann ein Song zuletzt geändert und wann er hinzugefügt wurde, und danach sortieren.',
			],
			[
				'en' => 'Columns can hide every rater\'s scores, or every rater\'s notes, at once.',
				'fy' => 'Kolommen kin de wurdearrings, of de notysjes, fan alle beoardielers tagelyk ferstopje.',
				'de' => 'Unter „Spalten“ lassen sich die Wertungen oder die Notizen aller Bewertenden auf einmal ausblenden.',
			],
			[
				'en' => 'The heading, Filter and buttons above the song list stay in view when the table scrolls sideways.',
				'fy' => 'De kop, it filter en de knoppen boppe de side Lieten bliuwe yn byld as de tabel nei de kant skowt.',
				'de' => 'Überschrift, Filter und Schaltflächen über der Songliste bleiben sichtbar, wenn die Tabelle seitwärts scrollt.',
			],
			[
				'en' => 'ID, Artist and Title stay in view when the song list scrolls sideways, where the window has room for them.',
				'fy' => 'ID, Artyst en Titel bliuwe yn byld as de side Lieten nei de kant skowt, wêr\'t it finster der romte foar hat.',
				'de' => 'ID, Interpret und Titel bleiben sichtbar, wenn die Songliste seitwärts scrollt, sofern das Fenster Platz dafür hat.',
			],
			[
				'en' => 'A card\'s Related row can link a game version or an alternate take.',
				'fy' => 'De rige Besibbe op in kaart kin in spulferzje of in alternative opname keppelje.',
				'de' => 'Die Zeile „Verwandt“ auf einer Karte kann eine Spielfassung oder eine alternative Aufnahme verknüpfen.',
				'admin' => true,
			],
			[
				'en' => 'A card\'s Related box shows each song\'s id, and an id typed into it picks that song.',
				'fy' => 'It fakje Besibbe op in kaart lit de id fan elk liet sjen, en in id dy\'t dêr yntypt wurdt kiest dat liet.',
				'de' => 'Das Feld „Verwandt“ auf einer Karte zeigt die ID jedes Songs, und eine dort eingetippte ID wählt diesen Song.',
				'admin' => true,
			],
		],
	],
	[
		'date' => '2026-11-05',
		'changes' => [
			[
				'en' => 'A home page with featured links into the song list, each saying how much of it is rated; logging in opens it.',
				'fy' => 'In thússide mei útljochte keppelings nei de side Lieten, elk mei hoefolle dêrfan wurdearre is; by it oanmelden giest dêr hinne.',
				'de' => 'Eine Startseite mit hervorgehobenen Links in die Songliste, jeweils mit dem Anteil bewerteter Songs; die Anmeldung führt dorthin.',
			],
			[
				'en' => 'The home page\'s links are picked on the home page.',
				'fy' => 'De keppelings fan de thússide wurde op de thússide útkeazen.',
				'de' => 'Die Links der Startseite werden auf der Startseite ausgewählt.',
				'admin' => true,
			],
			[
				'en' => 'A Columns button picks which columns the song list shows.',
				'fy' => 'In knop Kolommen kiest hokker kolommen de side Lieten sjen lit.',
				'de' => 'Eine Schaltfläche „Spalten“ wählt, welche Spalten die Songliste zeigt.',
			],
			[
				'en' => 'The song list\'s text columns can be made wider or narrower by dragging, and Album starts wider.',
				'fy' => 'De tekstkolommen fan de side Lieten kinne breder of smeller sleept wurde, en Album begjint breder.',
				'de' => 'Die Textspalten der Songliste lassen sich durch Ziehen breiter oder schmaler machen, und Album ist von Anfang an breiter.',
			],
			[
				'en' => 'New Other names & versions column on the song list, off by default.',
				'fy' => 'Nije kolom Oare nammen & ferzjes op de side Lieten, standert út.',
				'de' => 'Neue Spalte „Andere Namen & Fassungen“ in der Songliste, standardmäßig aus.',
			],
			[
				'en' => 'Song cards show a song\'s remastered or original version.',
				'fy' => 'De kaart fan in nûmer lit de remastere of oarspronklike ferzje sjen.',
				'de' => 'Die Karte eines Lieds nennt seine remasterte oder Originalfassung.',
			],
			[
				'en' => 'A song you haven\'t rated shows your rating of its other versions, faded.',
				'fy' => 'In nûmer datsto net wurdearre hast, lit flau dyn wurdearring fan in oare ferzje sjen.',
				'de' => 'Ein Lied, das du nicht bewertet hast, zeigt blass deine Bewertung seiner anderen Fassungen.',
			],
			[
				'en' => 'History also lists admins\' changes to songs, albums and artists.',
				'fy' => 'Skiednis lit ek de feroarings fan behearders oan nûmers, albums en artysten sjen.',
				'de' => 'Der Verlauf zeigt auch Änderungen von Admins an Liedern, Alben und Interpreten.',
			],
			[
				'en' => 'The settings and language icons are now the same size.',
				'fy' => 'De ikoanen foar ynstellings en taal binne no like grut.',
				'de' => 'Die Symbole für Einstellungen und Sprache sind jetzt gleich groß.',
			],
			[
				'admin' => true,
				'en' => 'Card edit mode can add and remove other names and related songs.',
				'fy' => 'Yn de bewurkmodus fan in kaart kinst oare nammen en besibbe nûmers tafoegje en fuorthelje.',
				'de' => 'Im Bearbeitungsmodus einer Karte lassen sich andere Namen und verwandte Lieder hinzufügen und entfernen.',
			],
			[
				'admin' => true,
				'en' => 'Add Songs can add another recording of a title the artist already has.',
				'fy' => 'Lieten tafoegje kin in oare opname tafoegje fan in titel dy\'t de artyst al hat.',
				'de' => '„Songs hinzufügen“ kann eine weitere Aufnahme eines Titels anlegen, den der Interpret schon hat.',
			],
			[
				'admin' => true,
				'en' => 'Add Songs shows each found song\'s ID and album, and links in full.',
				'fy' => 'Lieten tafoegje lit by elk fûn liet de ID en it album sjen, en keppelings hielendal.',
				'de' => '„Songs hinzufügen“ zeigt bei jedem gefundenen Song ID und Album, und Links vollständig.',
			],
		],
	],
	[
		'date' => '2026-11-02',
		'changes' => [
			[
				'en' => 'New What\'s New page.',
				'fy' => 'Nije side Wat is nij.',
				'de' => 'Neue Seite „Neuigkeiten“.',
			],
			[
				'en' => 'Choose how long rating pop-ups stay, in the settings menu (⚙).',
				'fy' => 'Kies yn it ynstellingsmenu (⚙) hoe lang meldingen oer wurdearrings bliuwe.',
				'de' => 'Im Einstellungsmenü (⚙) wählst du, wie lange Bewertungshinweise bleiben.',
			],
			[
				'en' => 'The score box only takes numbers from 0 to 10.',
				'fy' => 'It skoarefak nimt allinnich getallen fan 0 oant 10 oan.',
				'de' => 'Das Wertungsfeld nimmt nur Zahlen von 0 bis 10 an.',
			],
			[
				'en' => 'Rating pop-ups show the rater\'s name in their own colour.',
				'fy' => 'Meldingen oer wurdearrings litte de namme fan de wurdearder yn harren eigen kleur sjen.',
				'de' => 'Bewertungshinweise zeigen den Namen der Person in ihrer eigenen Farbe.',
			],
			[
				'en' => 'You stay signed in for up to a year, even after closing the browser.',
				'fy' => 'Do bliuwst oant in jier oanmeld, ek nei it sluten fan de browser.',
				'de' => 'Du bleibst bis zu einem Jahr angemeldet, auch nach dem Schließen des Browsers.',
			],
		],
	],
];
