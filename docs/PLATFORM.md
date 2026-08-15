# Vereinsplattform — Modulgrenzen

Verkaufbare / installierbare Module:

| Modul | Repo | DB-Prefix (Ziel) | Identity |
|-------|------|------------------|----------|
| Meldeliste | meldeliste | `meldeliste_` (später `melde_`) | Owner von `User` + `Permissions` |
| Notenarchiv | notenarchiv | `archiv_` | liest Melde-`User` |
| Mitgliederverwaltung | mitgliederverwaltung | `mit_` | liest Melde-`User` |

## Betriebsmodelle

- **Self-Host** und **gehostet**: gleiche Artefakte; Unterschied nur Betrieb/Config.
- Single-Tenant zuerst (eine Installation = ein Verein).

## Constraints

- Keine Cross-App-PHP-Includes; Integration über gemeinsame MySQL-DB + SSO.
- White-Label: Vereinsname/URLs/Branding in Config, nicht hardcoded.
- Feature-Flags / Lizenz-Hook später andockbar (`modules.enabled` o. Ä.).
- Melde-Eingriffe in der Parallelphase minimal (SSO-Hook, später UserVoice).

## Ops (eigenes Modul)

- `updater.php` / `update.php`: Git-Check/Pull + Schema prüfen/reparieren nur für `mit_*`.
- `config-menu.php` / `savePara.php`: globale Einstellungen + Farbschemata (`mit_config`).
- `backup.php` / `libs/backup.php`: ZIP-Download + Restore nur für `mit_*` (nie Melde-Identity).
- `help.php` / `views/help/guide.php`: Hilfe + Changelog (Melde-UI-Parität).
- `log.php` / `getLog.php` / `getList.php?type=log`: eigenes `mit_Log` (Chunk, Server-Suche, Live-Poll) — Melde-UI-SHELL §2.
- `install.php`: Erstinstallation (fresh create); Admin-Reparatur bevorzugt über Updater.

## UI-Shell (Melde-Parität)

Siehe Pointer [`docs/UI-SHELL.md`](UI-SHELL.md) → Meldeliste **master** [`docs/UI-SHELL.md`](https://github.com/Musikverein-Bonn-Duisdorf/meldeliste/blob/master/docs/UI-SHELL.md). Keine lokale Kopie des Vertrags.
