# Mitgliederverwaltung

Modul der Vereinsplattform für Mitgliedschaften, SEPA-Mandate und Dokumente.

- Plattform-Grenzen: [docs/PLATFORM.md](docs/PLATFORM.md)
- Identity-Vertrag: [docs/IDENTITY.md](docs/IDENTITY.md)
- Datenbankschema: [docs/SCHEMA.md](docs/SCHEMA.md)

## Erstinstallation

1. `common/config_sample.php` nach `common/config.php` kopieren und MySQL-Zugangsdaten eintragen.
2. `install.php` im Browser öffnen und Schema anlegen (oder `php scripts/dbmanage.php create`).
3. Mit einem Benutzer aus der Meldeliste-Identity (`meldeliste_User`) einloggen.

## Tests

Siehe separates Repo `mitgliederverwaltung-tests`.
