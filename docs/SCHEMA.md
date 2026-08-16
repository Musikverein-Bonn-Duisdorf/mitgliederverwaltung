# mit_ Schema

| Tabelle | Zweck |
|---------|--------|
| MemberProfile | 1:1 Melde-User: Geburtstag, Telefon, Anschrift, optional Kontoinhaber |
| Membership | Shell 1:1 Melde-User + individueller Jahresbeitrag (`AnnualFeeCents`) |
| MembershipPeriod | Tenure: Eintritt (`DateFrom`) / Austritt (`DateTo`, `ExitReason`) |
| MembershipTypePeriod | Typ-Segmente `aktiv`/`foerdernd` mit Stichtagen (Ehrungs-Historie) |
| MembershipApplication | Beitrittsantrag (Formular-Snapshot inkl. Beitrag, Scan, Apply) |
| SepaMandate | Mandat / IBAN (geschützt) |
| DebitBatch / DebitItem | Masseneinzug |
| Document | Dokument-Metadaten (Nextcloud-Pfad) |
| Permissions | MIT-Rechte pro Melde-User |
| config / Log | App-Config + Audit |

**Mitglied am Datum D:** EXISTS Tenure mit `DateFrom ≤ D` und (`DateTo` null oder `≥ D`).

**Typ am Datum D:** EXISTS TypePeriod mit gleichem Fenster.

Prefix: `mit_`. Identity: `meldeliste_User` (Login/Name/Email). Vollständige Stammdatenpflege in MIT (inkl. Name/Email auf dem Melde-User); Geburtstag/Telefon/Anschrift nur in `mit_MemberProfile`. Melde behält Orchester-Subset. Melde-`Active` bleibt Betriebsflag (≠ MIT-Typ `aktiv`).

Schema-Version: siehe `config/schema_version_number.php` (aktuell **7**: Perioden + individueller Beitrag).

Mindestbeiträge: Config `BeitragMindestAktiv` / `BeitragMindestFoerdernd` / `BeitragMindestErmaessigt` (Euro; Ermäßigung Studierende/Minderjährige, Standard `10,00`). Flag `FeeReduced` an Membership/Antrag.
Migration:

1. Schema create/repair (neue Tabellen)
2. `php scripts/migrateFlagsToPeriods.php` (Flags/TypeChange → Perioden)
3. Schema repair (drop `Membership.Type`/`Status`, `MembershipTypeChange`)
4. Optional: `php scripts/migrateMeldeFlagsToMit.php` vor Melde-Drop von Birthday/Mitglied
