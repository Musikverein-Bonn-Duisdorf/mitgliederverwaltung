<?php
/**
 * Help guide sections (permission-filtered).
 * Keep in sync when user-facing workflows change (makeVersion reminder).
 *
 * Expected vars: $optionsDB
 */

$sections = array();
$showAdmin = !empty($_SESSION['admin']);
$canEditConfig = hasPermission('perm_editConfig');
$canShowLog = hasPermission('perm_showLog');
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';

$sections[] = array(
    'id' => 'einfuehrung',
    'title' => 'Einführung',
    'body' => '
<p>Die <b>Mitgliederverwaltung</b> pflegt Mitgliedschaften, SEPA-Mandate und Dokument-Metadaten (Nextcloud-Pfade). Login und Rechte kommen aus der Meldeliste (SSO bzw. dieselben Zugangsdaten). Zugang hat nur, wer in der Meldeliste die Berechtigung <b>Mitgliederverwaltung</b> (<code>perm_accessMitgliederverwaltung</code>) besitzt.</p>
<p>Über die Navigation erreichst du die Bereiche, die für dich freigeschaltet sind: auf breiten Bildschirmen links mit Text, auf Tablet und Smartphone unten als Leiste (weitere Einträge und Admin unter <b>Mehr</b>). Diese Hilfe zeigt Admin-Abschnitte nur, wenn du Admin bist.</p>
'
);

$sections[] = array(
    'id' => 'navigation',
    'title' => 'Navigation',
    'body' => '
<p>Auf dem Desktop steht die Navigation links (Icons mit Beschriftung). Auf schmalen Bildschirmen unten; unter <b>Mehr</b> findest du weitere Einträge, Admin und Ausloggen.</p>
<ul class="help-list">
<li><i class="fas fa-home"></i> <b>Übersicht</b> – Einstieg mit Zählern zu den Bereichen</li>
<li><i class="fas fa-users"></i> <b>Mitglieder</b> – Mitgliedschaften (Suche, Detail)</li>
<li><i class="fas fa-university"></i> <b>SEPA</b> – Lastschriftmandate (IBAN maskiert)</li>
<li><i class="fas fa-file-alt"></i> <b>Dokumente</b> – Metadaten zu Nextcloud-Pfaden</li>
'.($meldeUrl !== '' ? '<li><i class="fas fa-clipboard-list"></i> <b>Meldeliste</b> – Rückkehr zur Meldeliste (SSO)</li>' : '').'
<li>Logo oben rechts – öffnet die <b>Vereinshomepage</b> in einem neuen Tab</li>
<li><i class="fas fa-circle-question"></i> <b>Hilfe</b> – diese Seite inkl. Changelog</li>
'.($showAdmin ? '<li><i class="fas fa-wrench"></i> <b>Admin</b> – Verwaltung (Konfiguration, Backup, Updater, Log) unter Mehr</li>' : '').'
<li><i class="fas fa-sign-out-alt"></i> <b>Ausloggen</b> – Sitzung beenden</li>
</ul>
'
);

$sections[] = array(
    'id' => 'uebersicht',
    'title' => 'Übersicht',
    'body' => '
<p>Die <b>Übersicht</b> zeigt die Anzahl der Mitgliedschaften, SEPA-Mandate und Dokumente und verlinkt in die jeweiligen Listen.</p>
'
);

$sections[] = array(
    'id' => 'mitglieder',
    'title' => 'Mitglieder',
    'body' => '
<p>Unter <b>Mitglieder</b> siehst du alle Mitgliedschaften. Die Suchzeile filtert nach Name, Typ und Status (UND über mehrere Wörter).</p>
<p>Ein Eintrag öffnet die Detailseite: Typ und Status, Mitgliedschaftszeiträume (falls gepflegt) sowie verknüpfte Dokumente. Von dort kannst du ein Dokument für die Person anlegen.</p>
'
);

$sections[] = array(
    'id' => 'sepa',
    'title' => 'SEPA',
    'body' => '
<p>Unter <b>SEPA</b> erscheinen Lastschriftmandate: Referenz, Person, maskierte IBAN, Gültigkeit und Aktiv-Status. Die Suche filtert über diese Felder.</p>
<p>IBANs werden nur maskiert angezeigt; die App speichert Bankdaten getrennt von der Melde-Identity.</p>
'
);

$sections[] = array(
    'id' => 'dokumente',
    'title' => 'Dokumente',
    'body' => '
<p>Unter <b>Dokumente</b> legst du Metadaten an: Melde-User-ID, Dokumenttyp, Nextcloud-Pfad und optionale Notiz. Die Datei selbst liegt in Nextcloud; die Mitgliederverwaltung speichert nur den Verweis.</p>
<p>Die Liste darunter zeigt vorhandene Einträge und lässt sich durchsuchen.</p>
'
);

$sections[] = array(
    'id' => 'login-sso',
    'title' => 'Login &amp; Meldeliste',
    'body' => '
<p>Die Mitgliederverwaltung teilt die Benutzerkonten mit der Meldeliste. Zugang nur mit Melde-Recht <b>Mitgliederverwaltung</b>. Typischer Einstieg: SSO-Link aus der Meldeliste oder Login mit denselben Zugangsdaten.</p>
'.($meldeUrl !== '' ? '<p>Über den Nav-Eintrag <b>Meldeliste</b> kehrst du zurück: <a href="'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'">'.htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'.($masterPage !== '' ? '<p>Die Vereinshomepage erreichst du über das Logo oder: <a href="'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener noreferrer">'.htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8').'</a></p>' : '').'
'
);

$sections[] = array(
    'id' => 'admin-verwaltung',
    'title' => 'Admin: Verwaltung',
    'visible' => $showAdmin,
    'body' => '
<ul class="help-list">
'.($canEditConfig ? '
<li><b>Konfiguration</b> – Site-Name, URLs, Farben und Farbschema; Änderungen erscheinen im Log. Schema-Version und Schema-Metadaten werden hier nicht bearbeitet</li>
<li><b>Backup</b> – ZIP mit Versionsinfo und SQL nur für Mitgliedschafts-Tabellen (<code>mit_*</code>), nicht Melde-Identity. Download im Browser; Restore mit CSRF und Bestätigung <code>RESTORE</code>; CLI <code>php scripts/restoreBackup.php … --yes</code>. Erfolgreiche Downloads erscheinen im Log</li>
<li><b>Updater</b> – Software-Update vom Remote und Datenbank prüfen/reparieren; der Bericht listet nur Änderungen und Probleme</li>
' : '').'
'.($canShowLog ? '
<li><b>Log</b> – Anwendungsprotokoll (Server-Suche, Live-Aktualisierung, Nachladen beim Scrollen)</li>
' : '').'
</ul>
'
);

$sections[] = array(
    'id' => 'kontakt',
    'title' => 'Kontakt',
    'body' => '
<p>Bei Fragen zu Rechten oder Zugang wende dich an die Meldeliste-Administration.</p>
<p>Die installierte Version ist im Changelog markiert (rechts bzw. darunter).</p>
'
);

$visible = array();
foreach($sections as $section) {
    if(isset($section['visible']) && !$section['visible']) {
        continue;
    }
    $visible[] = $section;
}
?>
<nav class="help-toc w3-card w3-padding w3-margin-bottom" aria-label="Inhalt">
  <h3 class="w3-margin-top">Inhalt</h3>
  <ol class="help-toc-list">
<?php foreach($visible as $section) { ?>
    <li><a href="#help-<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $section['title']; ?></a></li>
<?php } ?>
    <li class="w3-hide-large"><a href="#help-changelog">Changelog</a></li>
  </ol>
</nav>

<?php foreach($visible as $section) { ?>
<section class="help-section w3-margin-bottom" id="help-<?php echo htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8'); ?>">
  <h3><?php echo $section['title']; ?></h3>
  <div class="help-section-body">
    <?php echo $section['body']; ?>
  </div>
</section>
<?php } ?>
