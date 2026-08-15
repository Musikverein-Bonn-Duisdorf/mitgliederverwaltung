# Identity-Vertrag (Notenarchiv)

- Canonical User-Tabelle: `{identityPrefix}User` (Prod/Standard: `meldeliste_User`).
- Permissions: Melde `{identityPrefix}Permissions` für Modul-Login (`perm_accessMitgliederverwaltung`, kein Admin-Bypass).
- In-App-Rechte: `mit_Permissions` (`perm_showUsers`, `perm_editUsers`, `perm_editPermissions`) — siehe `permissions.php`.
- Config: `$identityPrefix` (default `meldeliste_`), getrennt von `$dbprefix` (`archiv_`).
- Legacy `{prefix}Users` wird nicht mehr beschrieben; Migration entfernt Abhängigkeit.
- SSO: optional Einmal-Ticket von Meldeliste (`sso.php`) oder Shared-Cookie (Parent-Domain).
