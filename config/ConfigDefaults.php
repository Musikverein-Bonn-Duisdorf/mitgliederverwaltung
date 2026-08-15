<?php
/**
 * Default configuration parameters for new installations / updates.
 * Brand colors: Melde Hex kit (UI-SHELL / PLATFORM white-label).
 */
function getConfigDefaults() {
    return array(
        array(
            'Parameter' => 'WebSiteName',
            'Value' => 'Mitgliederverwaltung',
            'Type' => 'string',
            'Description' => 'Name der Webseite',
        ),
        array(
            'Parameter' => 'WebSiteNameShort',
            'Value' => 'Mitglieder',
            'Type' => 'string',
            'Description' => 'Kurzname der Webseite',
        ),
        array(
            'Parameter' => 'WebSiteURL',
            'Value' => 'index.php',
            'Type' => 'string',
            'Description' => 'Startseite',
        ),
        array(
            'Parameter' => 'MasterPage',
            'Value' => 'https://example.org',
            'Type' => 'string',
            'Description' => 'Vereinshomepage',
        ),
        array(
            'Parameter' => 'MasterPageIcon',
            'Value' => 'imgs/MVDLogo32x32.png',
            'Type' => 'string',
            'Description' => 'Icon Vereinshomepage',
        ),
        array(
            'Parameter' => 'favicon',
            'Value' => 'imgs/MVDLogo32x32.png',
            'Type' => 'string',
            'Description' => 'Favicon',
        ),
        array(
            'Parameter' => 'urlMeldeliste',
            'Value' => '',
            'Type' => 'string',
            'Description' => 'URL der Meldeliste (Nav-Link)',
        ),
        array(
            'Parameter' => 'MessageOfTheDay',
            'Value' => '',
            'Type' => 'text',
            'Description' => 'Nachricht des Tages (Modal)',
        ),
        array(
            'Parameter' => 'MessageOfTheDayShort',
            'Value' => '',
            'Type' => 'string',
            'Description' => 'Kurzer Hinweis in der App',
        ),
        array(
            'Parameter' => 'showBranchBannerAlways',
            'Value' => '0',
            'Type' => 'bool',
            'Description' => 'Branch-Banner auch auf master anzeigen',
        ),
        array(
            'Parameter' => 'colorBackground',
            'Value' => '#FDFFFC',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe der Seite',
        ),
        array(
            'Parameter' => 'colorTitle',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe der Titelzeile',
        ),
        array(
            'Parameter' => 'colorTitleBar',
            'Value' => '#345A95',
            'Type' => 'color',
            'Description' => 'Farbe von Seiten-Titelleisten / aktivem Nav',
        ),
        array(
            'Parameter' => 'colorWarning',
            'Value' => '#FDF9E7',
            'Type' => 'color',
            'Description' => 'Farbe von Warnhinweisen',
        ),
        array(
            'Parameter' => 'colorNav',
            'Value' => '#969696',
            'Type' => 'color',
            'Description' => 'Farbe der Navigationsleiste',
        ),
        array(
            'Parameter' => 'colorNavAdmin',
            'Value' => '#607D8B',
            'Type' => 'color',
            'Description' => 'Farbe der Admin-Navigationsleiste',
        ),
        array(
            'Parameter' => 'colorInputBackground',
            'Value' => '#F1F1F1',
            'Type' => 'color',
            'Description' => 'Hintergrundfarbe von Eingabefeldern',
        ),
        array(
            'Parameter' => 'colorBtnSubmit',
            'Value' => '#9C27B0',
            'Type' => 'color',
            'Description' => 'Farbe von Submit-Buttons',
        ),
        array(
            'Parameter' => 'colorBtnDelete',
            'Value' => '#F44336',
            'Type' => 'color',
            'Description' => 'Farbe von Löschen-Buttons',
        ),
        array(
            'Parameter' => 'colorBtnEdit',
            'Value' => '#009688',
            'Type' => 'color',
            'Description' => 'Farbe von Bearbeiten-Buttons',
        ),
        array(
            'Parameter' => 'colorSuccess',
            'Value' => '#4CAF50',
            'Type' => 'color',
            'Description' => 'Farbe von Erfolgsmeldungen',
        ),
        array(
            'Parameter' => 'colorLogError',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von Fehlerhinweisen',
        ),
        array(
            'Parameter' => 'colorLogWarning',
            'Value' => '#FFC300',
            'Type' => 'color',
            'Description' => 'Farbe von Warnungen',
        ),
        array(
            'Parameter' => 'colorSchemeActive',
            'Value' => 'classic',
            'Type' => 'string',
            'Description' => 'Aktives Farbschema (classic, light, dark, gold, soft)',
        ),
        array(
            'Parameter' => 'colorSchemes',
            'Value' => '',
            'Type' => 'internal',
            'Description' => 'Gespeicherte Farbschemata (JSON, intern)',
        ),
        array(
            'Parameter' => 'HoverEffect',
            'Value' => 'w3-hover-gray',
            'Type' => 'string',
            'Description' => 'Stil des Hover-Effekts in Listen',
        ),
        array(
            'Parameter' => 'logListChunkSize',
            'Value' => '100',
            'Type' => 'uint',
            'Description' => 'Log: Einträge pro Scroll-/Poll-Seite (1–500)',
        ),
        array(
            'Parameter' => 'VereinName',
            'Value' => 'Musikverein Bonn-Duisdorf gegr. 1949 e.V.',
            'Type' => 'string',
            'Description' => 'Vollständiger Vereinsname (Beitrittsformular / SEPA)',
        ),
        array(
            'Parameter' => 'SepaCreditorBank',
            'Value' => 'VR-Bank Bonn Rhein-Sieg eG',
            'Type' => 'string',
            'Description' => 'Kreditinstitut des Vereins (SEPA-Gläubiger)',
        ),
        array(
            'Parameter' => 'SepaCreditorIban',
            'Value' => 'DE12 3706 9520 8008 6120 14',
            'Type' => 'string',
            'Description' => 'IBAN des Vereinskontos (SEPA-Gläubiger)',
        ),
        array(
            'Parameter' => 'SepaCreditorBic',
            'Value' => 'GENODED1RST',
            'Type' => 'string',
            'Description' => 'BIC des Vereinskontos (SEPA-Gläubiger)',
        ),
        array(
            'Parameter' => 'SepaCreditorId',
            'Value' => '',
            'Type' => 'string',
            'Description' => 'SEPA-Gläubiger-Identifikationsnummer (CI)',
        ),
        array(
            'Parameter' => 'BeitragMindestAktivCents',
            'Value' => '2000',
            'Type' => 'int',
            'Description' => 'Mindest-Jahresbeitrag aktives Mitglied (Cent)',
        ),
        array(
            'Parameter' => 'BeitragMindestFoerderndCents',
            'Value' => '2000',
            'Type' => 'int',
            'Description' => 'Mindest-Jahresbeitrag förderndes Mitglied (Cent)',
        ),
        array(
            'Parameter' => 'SchemaVersion',
            'Value' => '0',
            'Type' => 'int',
            'Description' => 'Installierte DB-Schema-Version (Soll: config/SchemaVersion.php)',
        ),
    );
}
?>
