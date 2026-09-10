<?php

return [

	'register.title' => 'Registrieren',
	'register.heading' => 'Registrieren',
	'register.firstAccount' => 'Es existieren noch keine Konten, daher braucht dieses erste keinen Einladungscode.',
	'register.field.inviteCode' => 'Einladungscode',
	'register.field.accountName' => 'Kontoname',
	'register.field.password' => 'Passwort',
	'register.field.passwordConfirm' => 'Passwort wiederholen',
	'register.submit' => 'Registrieren',
	'register.error.expired' => 'Dieses Formular ist abgelaufen, bitte versuche es erneut.',
	'register.error.missing' => 'Gib einen Kontonamen und ein Passwort ein.',
	'register.error.passwordShort' => 'Passwörter brauchen mindestens 8 Zeichen.',
	'register.error.passwordMismatch' => 'Die beiden Passwörter stimmen nicht überein.',
	'register.error.nameTaken' => 'Dieser Kontoname ist bereits vergeben.',
	'register.error.badInvite' => 'Dieser Einladungscode ist ungültig oder wurde bereits verwendet.',

	'login.title' => 'Anmelden',
	'login.heading' => 'Anmelden',
	'login.field.accountName' => 'Kontoname',
	'login.field.password' => 'Passwort',
	'login.submit' => 'Anmelden',
	'login.error.expired' => 'Dieses Formular ist abgelaufen, bitte versuche es erneut.',
	'login.error.rejected' => 'Kontoname und Passwort stimmen nicht überein.',
	'login.error.tooMany' => 'Zu viele Fehlversuche. Versuche es in ein paar Minuten erneut.',

	'invites.title' => 'Einladungen',
	'invites.heading' => 'Einladungen',
	'invites.create' => 'Einladungscode erstellen',
	'invites.empty' => 'Noch keine Einladungscodes.',
	'invites.column.code' => 'Code',
	'invites.column.created' => 'Erstellt',
	'invites.column.used' => 'Verwendet',
	'invites.column.action' => 'Aktion',
	'invites.unused' => 'Unbenutzt',
	'invites.usedBy' => 'Verwendet von {name}',
	'invites.revoked' => 'Widerrufen', // ? "Ungültig gemacht"
	'invites.revoke' => 'Widerrufen', // ? "Ungültig machen"

	'accounts.title' => 'Konten',
	'accounts.heading' => 'Konten',
	'accounts.column.name' => 'Konto',
	'accounts.column.admin' => 'Admin',
	'accounts.column.action' => 'Aktion',
	'accounts.isAdmin' => 'Ja',
	'accounts.notAdmin' => 'Nein',
	'accounts.promote' => 'Zum Admin machen',
	'accounts.demote' => 'Admin entfernen',
	'accounts.self' => 'Du',

];
