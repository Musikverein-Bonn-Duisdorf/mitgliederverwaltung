# Identity-Vertrag (Notenarchiv)

- Canonical User-Tabelle: `{identityPrefix}User` (Prod/Standard: `meldeliste_User`).
- Permissions: `{identityPrefix}Permissions` (optional für Rechte-Checks).
- Config: `$identityPrefix` (default `meldeliste_`), getrennt von `$dbprefix` (`archiv_`).
- Legacy `{prefix}Users` wird nicht mehr beschrieben; Migration entfernt Abhängigkeit.
- SSO: optional Einmal-Ticket von Meldeliste (`sso.php`) oder Shared-Cookie (Parent-Domain).
