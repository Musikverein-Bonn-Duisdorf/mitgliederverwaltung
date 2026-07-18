<?php
/**
 * Default configuration parameters for new installations / updates.
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
            'Value' => 'styles/favicon.ico',
            'Type' => 'string',
            'Description' => 'Icon Vereinshomepage',
        ),
        array(
            'Parameter' => 'favicon',
            'Value' => 'styles/favicon.ico',
            'Type' => 'string',
            'Description' => 'Favicon',
        ),
        array(
            'Parameter' => 'colorBackground',
            'Value' => 'w3-light-grey',
            'Type' => 'string',
            'Description' => 'Hintergrundfarbe',
        ),
        array(
            'Parameter' => 'colorTitle',
            'Value' => 'w3-teal',
            'Type' => 'color',
            'Description' => 'Titelleiste',
        ),
        array(
            'Parameter' => 'colorTitleBar',
            'Value' => 'w3-pale-blue',
            'Type' => 'color',
            'Description' => 'Titelleiste Inhalt',
        ),
        array(
            'Parameter' => 'colorNav',
            'Value' => 'w3-dark-grey',
            'Type' => 'color',
            'Description' => 'Navigation',
        ),
        array(
            'Parameter' => 'colorNavAdmin',
            'Value' => 'w3-grey',
            'Type' => 'color',
            'Description' => 'Admin-Navigation',
        ),
        array(
            'Parameter' => 'colorInputBackground',
            'Value' => 'w3-white',
            'Type' => 'color',
            'Description' => 'Eingabefelder',
        ),
        array(
            'Parameter' => 'colorBtnSubmit',
            'Value' => 'w3-blue',
            'Type' => 'color',
            'Description' => 'Submit-Buttons',
        ),
        array(
            'Parameter' => 'colorSuccess',
            'Value' => 'w3-pale-green',
            'Type' => 'color',
            'Description' => 'Erfolg',
        ),
        array(
            'Parameter' => 'colorLogError',
            'Value' => 'w3-pale-red',
            'Type' => 'color',
            'Description' => 'Fehler',
        ),
        array(
            'Parameter' => 'colorLogWarning',
            'Value' => 'w3-pale-yellow',
            'Type' => 'color',
            'Description' => 'Warnung',
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
