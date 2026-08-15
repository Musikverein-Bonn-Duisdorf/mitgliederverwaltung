# mit_ Schema

| Tabelle | Zweck |
|---------|--------|
| Membership | user_id, Typ, Status |
| MembershipPeriod | Eintritt/Austritt |
| SepaMandate | Mandat / IBAN (geschützt) |
| DebitBatch / DebitItem | Masseneinzug |
| Document | Beitritts-Scan (nextcloud_path) |
| config / Log | App-Config + Audit (`mit_Log`, Type int, Melde-UI-SHELL) |

Prefix: `mit_`. Identity: `meldeliste_User`.

Schema-Version: siehe `config/schema_version_number.php` (aktuell 3: HoverEffect).
