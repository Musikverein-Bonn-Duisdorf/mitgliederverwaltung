# mit_ Schema

| Tabelle | Zweck |
|---------|--------|
| Membership | user_id, Typ, Status |
| MembershipPeriod | Eintritt/Austritt |
| SepaMandate | Mandat / IBAN (geschützt) |
| DebitBatch / DebitItem | Masseneinzug |
| Document | Beitritts-Scan (nextcloud_path) |
| Permissions | MIT-Rechte pro Melde-User (`perm_showUsers`, `perm_editUsers`, `perm_editPermissions`) |
| config / Log | App-Config + Audit (`mit_Log`, Type int, Melde-UI-SHELL) |

Prefix: `mit_`. Identity: `meldeliste_User`. Modul-Login: Melde-`perm_accessMitgliederverwaltung`.

Schema-Version: siehe `config/schema_version_number.php` (aktuell 4: `mit_Permissions`).
