<?php

/// Shown at /music/changes, newest first; a null date is the release not dated yet.
/// Each entry is keyed by locale with `en` required, and **text** is bold (docs/music.md).

return [
	[
		'date' => null,
		'changes' => [
			'added' => [
				[
					'en' => 'A Columns button beside Card view on the song list lets you choose which columns the table shows, down to one person\'s ratings or a single statistic. Your choice is saved to your account.',
					'fy' => 'In knop Kolommen neist Kaartwerjefte op de side Lieten lit dy kieze hokker kolommen de tabel sjen lit, oant de wurdearrings fan ien persoan of ien inkelde statistyk ta. Dyn kar wurdt by dyn akkount bewarre.',
					'de' => 'Eine Schaltfläche „Spalten“ neben „Kartenansicht“ in der Songliste lässt dich wählen, welche Spalten die Tabelle zeigt, bis hin zu den Wertungen einer einzelnen Person oder einer einzelnen Statistik. Deine Auswahl wird in deinem Konto gespeichert.',
				],
				[
					'en' => 'A new Also Known As column on the song list shows a song\'s other titles, one per line. It is hidden until you tick it under Columns. A song\'s card always lists them.',
					'fy' => 'In nije kolom Ek bekend as op de side Lieten lit de oare titels fan in nûmer sjen, ien per rigel. Hy is ferstoppe oant do him ûnder Kolommen oanfinkest. De kaart fan in nûmer lit se altyd sjen.',
					'de' => 'Eine neue Spalte „Auch bekannt als“ in der Songliste zeigt die anderen Titel eines Lieds, einen pro Zeile. Sie ist ausgeblendet, bis du sie unter „Spalten“ ankreuzt. Die Karte eines Lieds zeigt sie immer.',
				],
				[
					'en' => 'When editing a song, album or artist card, you can now write in another name for it under Also Known As, delete one of its other names, or make one its name again. Deleting asks first. If an album lists the song under the name you are deleting, the card says which album and offers to list it there under the song\'s current title.',
					'fy' => 'By it bewurkjen fan de kaart fan in nûmer, album of artyst kinst no ûnder Ek bekend as in oare namme derby skriuwe, ien fan de oare nammen wiskje, of der wer de namme fan meitsje. Foar it wiskjen wurdt earst frege. As in album it nûmer ûnder de namme stiet dy\'tsto wiskest, seit de kaart hokker album en biedt oan it dêr ûnder de hjoeddeistige titel fan it nûmer te setten.',
					'de' => 'Beim Bearbeiten der Karte eines Lieds, Albums oder Interpreten kannst du jetzt unter „Auch bekannt als“ einen weiteren Namen eintragen, einen seiner anderen Namen löschen oder wieder zu seinem Namen machen. Vor dem Löschen wird nachgefragt. Führt ein Album das Lied unter dem Namen, den du löschst, nennt die Karte das Album und bietet an, das Lied dort unter seinem aktuellen Titel zu führen.',
				],
				[
					'en' => 'A song\'s card can now have a Related row naming its remastered or original version. Click the name to open that song\'s card. When editing a card, you can add, change or remove one, finding the other song by typing part of its name. The song list has a matching Related column, hidden until you tick it under Columns.',
					'fy' => 'De kaart fan in nûmer kin no in rige Besibbe hawwe mei de remastere of oarspronklike ferzje derfan. Klik op de namme om de kaart fan dat nûmer te iepenjen. By it bewurkjen fan in kaart kinst der ien tafoegje, feroarje of fuorthelje, en it oare nûmer fynst troch in diel fan de namme te typen. De side Lieten hat in passende kolom Besibbe, ferstoppe oant do him ûnder Kolommen oanfinkest.',
					'de' => 'Die Karte eines Lieds kann jetzt eine Zeile „Verwandt“ haben, die seine remasterte Fassung oder seine Originalfassung nennt. Klicke auf den Namen, um die Karte dieses Lieds zu öffnen. Beim Bearbeiten einer Karte kannst du eine hinzufügen, ändern oder entfernen und findest das andere Lied, indem du einen Teil seines Namens tippst. Die Songliste hat eine passende Spalte „Verwandt“, ausgeblendet, bis du sie unter „Spalten“ ankreuzt.',
				],
				[
					'en' => 'Add Songs can now add a song of its own when the artist already has one with that title, such as another recording of it: pick <New, separate> for that row, or tick the box above the list to do it for every such row. Below the box you can say how each one relates to the song it shares its title with, and that shows on both cards.',
					'fy' => 'Lieten tafoegje kin no in nûmer apart tafoegje as de artyst al ien mei dy titel hat, lykas in oare opname derfan: kies <Nij, apart> foar dy rige, of finkje it fakje boppe de list oan om dat foar al sokke rigen te dwaan. Under it fakje kinst oanjaan hoe\'t elk him ferhâldt ta it nûmer mei deselde titel, en dat stiet dan op beide kaarten.',
					'de' => '„Songs hinzufügen“ kann jetzt ein eigenes Lied anlegen, wenn der Interpret schon eins mit diesem Titel hat, etwa eine andere Aufnahme davon: wähle <Neu, getrennt> für diese Zeile, oder kreuze das Kästchen über der Liste an, um es für alle solchen Zeilen zu tun. Unter dem Kästchen kannst du angeben, wie jedes mit dem gleichnamigen Lied zusammenhängt, und das steht dann auf beiden Karten.',
				],
				[
					'en' => 'Where you haven\'t rated a song but have rated another version of it, such as its remaster, your rating of that version shows on it, faded, and counts in its averages, its ratings count and sorting, on the song list and on album and artist pages. A score shows the average of the versions you rated, a note the one you wrote last. Rate the song yourself and yours replaces it. Hovering a rating lists that person\'s ratings of the other versions.',
					'fy' => 'Wêrsto in nûmer net wurdearre hast mar in oare ferzje derfan al, lykas de remaster, stiet dyn wurdearring fan dy ferzje der flau op en telt dy mei yn de gemiddelden, it tal wurdearrings en it sortearjen, op de side Lieten en op de siden fan albums en artysten. In skoare lit it gemiddelde sjen fan de ferzjes dy\'tsto wurdearre hast, in notysje dyjinge dy\'tsto it lêst skreaun hast. Wurdearrest it nûmer sels, dan komt dyn wurdearring derfoar yn it plak. As do mei de mûs oer in wurdearring giest, sjochsto de wurdearrings fan dy persoan foar de oare ferzjes.',
					'de' => 'Wo du ein Lied nicht bewertet hast, aber eine andere Fassung davon, etwa das Remaster, erscheint deine Bewertung dieser Fassung blass darauf und zählt in seinen Durchschnitten, der Zahl der Bewertungen und der Sortierung, in der Songliste und auf den Seiten von Alben und Interpreten. Eine Wertung zeigt den Durchschnitt der Fassungen, die du bewertet hast, eine Notiz die, die du zuletzt geschrieben hast. Bewertest du das Lied selbst, tritt deine Bewertung an ihre Stelle. Fährst du mit der Maus über eine Bewertung, siehst du die Bewertungen dieser Person für die anderen Fassungen.',
				],
			],
		],
	],
	[
		'date' => '2026-11-02',
		'changes' => [
			'added' => [
				[
					'en' => 'A What\'s New page in the menu bar, listing what has changed on these pages, newest first.',
					'fy' => 'In side "Wat is nij" yn de menubalke, mei wat der op dizze siden feroare is, it nijste boppe-oan.',
					'de' => 'Eine Seite „Neuigkeiten“ in der Menüleiste, die zeigt, was sich auf diesen Seiten geändert hat, das Neueste zuerst.',
				],
				[
					'en' => 'In the settings menu (the cog) you can choose how long the rating pop-ups on the song list stay: from 10 seconds to an hour, until you close them, or not at all. It starts at 3 minutes, as before. The pop-up saying your own rating hasn\'t been saved yet still shows when they are off.',
					'fy' => 'Yn it ynstellingsmenu (⚙) kinst kieze hoe lang de meldingen oer wurdearrings op de side Lieten bliuwe: fan 10 sekonden oant in oere, oant do se slútst, of hielendal net. It stiet op 3 minuten, krekt as earder. De melding dat dyn eigen wurdearring noch net bewarre is, komt ek as se út steane.',
					'de' => 'Im Einstellungsmenü (⚙) kannst du wählen, wie lange die Bewertungshinweise in der Songliste bleiben: von 10 Sekunden bis zu einer Stunde, bis du sie schließt, oder gar nicht. Voreingestellt sind wie bisher 3 Minuten. Der Hinweis, dass deine eigene Wertung noch nicht gespeichert ist, erscheint auch dann, wenn sie ausgeschaltet sind.',
				],
			],
			'changed' => [
				[
					'en' => 'A score has to be a number from 0 to 10. The score box on the song list only takes what can become one: letters, a minus sign or a number over 10 simply don\'t go in, and on a phone it brings up a number keypad.',
					'fy' => 'In wurdearring moat in getal fan 0 oant 10 wêze. It skoarefak op de side Lieten nimt allinnich oan wat dêr ien wurde kin: letters, in minteken of in getal boppe de 10 komme der gewoan net yn, en op in telefoan komt der in nûmertoetseboerd.',
					'de' => 'Eine Wertung muss eine Zahl von 0 bis 10 sein. Das Wertungsfeld in der Songliste nimmt nur an, was eine werden kann: Buchstaben, ein Minuszeichen oder eine Zahl über 10 lassen sich gar nicht erst eingeben, und auf dem Handy erscheint ein Ziffernblock.',
				],
				[
					'en' => 'The pop-ups on the song list that say someone rated a song now show that person\'s name in their own site colour.',
					'fy' => 'De meldingen op de side Lieten dat ien in liet wurdearre hat, litte de namme fan dy persoan no yn harren eigen kleur sjen.',
					'de' => 'Die Hinweise in der Songliste, dass jemand einen Song bewertet hat, zeigen den Namen dieser Person jetzt in ihrer eigenen Farbe.',
				],
				[
					'en' => 'You now stay signed in, even after closing your browser, until you log out or stay away for more than a year.',
					'fy' => 'Do bliuwst no oanmeld, ek as do dyn browser slútst, oant do dy ôfmeldst of mear as in jier net langskomst.',
					'de' => 'Du bleibst jetzt angemeldet, auch wenn du den Browser schließt, bis du dich abmeldest oder länger als ein Jahr nicht vorbeischaust.',
				],
			],
		],
	],
];
