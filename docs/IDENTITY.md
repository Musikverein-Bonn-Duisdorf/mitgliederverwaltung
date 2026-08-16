# Identity-Vertrag (Mitgliederverwaltung / Plattform)

- Canonical User-Tabelle: `{identityPrefix}User` (Prod/Standard: `meldeliste_User`) — **eine** Personen-Identität, keine `mit_Person`.
- **MIT** pflegt die **vollständigen Stammdaten**: Name, E-Mail(/Email2) auf dem Melde-User sowie Geburtstag, Telefon, Anschrift, Kontoinhaber in `mit_MemberProfile`, plus Mitgliedschaft/SEPA. Neue Personen können in MIT angelegt werden (Melde-`User`-Zeile; `Active`/Instrument bleiben Melde-Defaults, Login optional).
- **Melde** behält ein **Subset** für den Orchesterbetrieb: Name/E-Mail (weiter editierbar), Login/Passhash, Instrument, Active (regelmäßig dabei), Gruppen, Rechte, Benachrichtigungen — **kein** Geburtstag/Adresse/Telefon/Mitgliedsnummer.
- Vereinsmitglied heute = offene Tenure; Typ `aktiv`/`foerdernd` = TypePeriod am Stichtag. Melde-`Active` ≠ MIT-Typ `aktiv`.
- Modul-Login: Melde `{identityPrefix}Permissions.perm_accessMitgliederverwaltung` (kein Admin-Bypass).
- In-App-Rechte: `mit_Permissions` (`perm_showUsers`, `perm_editUsers`, `perm_showJubilees`, `perm_showLog`, `perm_editPermissions`).
- Config: `$identityPrefix` (default `meldeliste_`), getrennt von `$dbprefix` (`mit_`).
- SSO: Einmal-Ticket von Meldeliste (`sso.php`).
- Sibling-Nav: Config `urlMeldeliste` (Rücklink), `urlNotenarchiv` (nur mit Melde-`perm_accessNotenarchiv`; SSO bevorzugt über Melde-`sso.php`).
- Fördernde: Melde-User-Zeile existiert; in Melde-Listen ausgeblendet außer bei expliziten Chips (Termine/Inventar) — Filter über aktuelle TypePeriod `foerdernd`.
- Migration: `migrateMeldeFlagsToMit.php` + `migrateFlagsToPeriods.php` vor/mit Schema-Drop.
