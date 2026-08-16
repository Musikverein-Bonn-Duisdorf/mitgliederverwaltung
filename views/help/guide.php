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
$canEditPermissions = hasPermission('perm_editPermissions');
$canShowUsers = hasPermission('perm_showUsers');
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$archivUrlCfg = isset($optionsDB['urlNotenarchiv']) ? trim((string)$optionsDB['urlNotenarchiv']) : '';
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';
$showArchivNav = $archivUrlCfg !== '' && !empty($_SESSION['userid'])
    && function_exists('userMayAccessNotenarchiv')
    && userMayAccessNotenarchiv((int)$_SESSION['userid']);

$sections[] = array(
    'id' => 'einfuehrung',
    'title' => 'Einführung',
    'body' => '
<p>Die <b>Mitgliederverwaltung</b> pflegt Mitgliedschaften, SEPA-Mandate und personenbezogene Dokumente (lokal). Login über Meldeliste (SSO bzw. dieselben Zugangsdaten) erfordert das Melde-Recht <b>Mitgliederverwaltung</b>. Was du danach siehst und bearbeiten darfst, steuern die <b>eigenen MIT-Berechtigungen</b> (Benutzer anzeigen/bearbeiten, Jubiläen, Log, Rechte).</p>
<p>Über die Navigation erreichst du die Bereiche, die für dich freigeschaltet sind: auf breiten Bildschirmen links mit Text, auf Tablet und Smartphone unten als Leiste (weitere Einträge und Admin unter <b>Mehr</b>). Diese Hilfe zeigt Admin-Abschnitte nur, wenn du Admin bist.</p>
'
);

$sections[] = array(
    'id' => 'navigation',
    'title' => 'Navigation',
    'body' => '
<p>Auf dem Desktop steht die Navigation links (Icons mit Beschriftung). Auf schmalen Bildschirmen unten; unter <b>Mehr</b> findest du weitere Einträge, Admin und Ausloggen. Einträge sind nach Berechtigungsgruppen eingefärbt (wie in der Meldeliste).</p>
<ul class="help-list">
<li><i class="fas fa-home"></i> <b>Übersicht</b> – Einstieg mit Zählern zu den Bereichen</li>
'.($canShowUsers ? '
<li><i class="fas fa-users"></i> <b>Personen</b> – alle Melde-User (Suche, Filter, Detail)</li>
<li><i class="fas fa-university"></i> <b>SEPA</b> – Lastschriftmandate (IBAN in Listen maskiert)</li>
' : '').'
'.(hasPermission('perm_showJubilees') ? '
<li><i class="fas fa-award"></i> <b>Jubiläen</b> – <b>Kalender</b> (Monat) und <b>Liste</b> (Jahr); Recht <code>perm_showJubilees</code></li>
' : '').'
'.($meldeUrl !== '' ? '<li><i class="fas fa-clipboard-list"></i> <b>Meldeliste</b> – Wechsel zur Meldeliste (Config <code>urlMeldeliste</code>)</li>' : '').'
'.($showArchivNav ? '<li><i class="fas fa-book"></i> <b>Notenarchiv</b> – Wechsel zum Archiv (Melde-Recht Notenarchiv; SSO über Melde wenn möglich)</li>' : '').'
<li>Logo oben rechts – öffnet die <b>Vereinshomepage</b> in einem neuen Tab</li>
<li><i class="fas fa-circle-question"></i> <b>Hilfe</b> – diese Seite inkl. Changelog</li>
'.($showAdmin ? '<li><i class="fas fa-wrench"></i> <b>Admin</b> – Verwaltung (Konfiguration, Backup, Updater'.($canShowLog ? ', Log' : '').($canEditPermissions ? ', Berechtigungen' : '').') unter Mehr</li>' : '').'
<li><i class="fas fa-sign-out-alt"></i> <b>Ausloggen</b> – Sitzung beenden</li>
</ul>
'
);

$sections[] = array(
    'id' => 'uebersicht',
    'title' => 'Übersicht',
    'body' => '
<p>Die <b>Übersicht</b> zeigt die Anzahl der Personen und SEPA-Mandate und verlinkt in die jeweiligen Listen.</p>
'
);

$sections[] = array(
    'id' => 'personen',
    'title' => 'Personen',
    'body' => '
<p>Unter <b>Personen</b> siehst du alle nicht gelöschten Melde-User (nicht nur bestehende Mitgliedschaften). Filter: alle / Mitglied heute / <b>Aktiv</b> (Musiker) / <b>Fördernd</b> / kein Mitglied / <b>Löschung fällig</b>. Die Suchzeile filtert nach Name, Email und Typ. Mit Recht <code>perm_editUsers</code> legst du über <b>Neu</b> Personen an (Identity-Zeile in der Meldeliste; Orchesterbetrieb bleibt inaktiv, bis dort gepflegt). Auf <b>Person anlegen</b> kannst du entweder „Anlegen“ (Vor-/Nachname Pflicht → Personenseite) oder direkt <b>Beitrittsformular</b> wählen (auch ohne Angaben); Name, E-Mail und Adresse trägst du dann im Formular ein.</p>
<p>Im Detail pflegst du die <b>vollständigen Stammdaten</b> sowie Mitgliedschaft (Beitritt/Typwechsel/Austritt), SEPA und das <b>Dokumentenverzeichnis</b> der Person. Speichern erfordert <code>perm_editUsers</code>.</p>
<p>Das <b>Beitrittsformular</b> erreichst du von der Personenseite oder direkt beim Anlegen. Neue Erklärungen kannst du ausfüllen und drucken; alte PDFs oder Scans legst du unter Dokumente mit Typ <b>Beitritt</b> ab (bei Bestandsmitgliedern ohne Änderung der Mitgliedschaft).</p>
<p><b>Beitritt:</b> Formular öffnen → Name/Kontakt/Adresse/Bank und Beitrag speichern (schreibt Personendaten und Antrag) → Drucken → unterschreiben → Scan hochladen. Der Upload setzt Mitgliedschaft (Datum und Typ vom Formular) und bei SEPA das Mandat. Typwechsel und Austritt erscheinen erst nach Eintritt.</p>
<p><b>Korrektur:</b> Mitgliedszeiten und Typzeiten im Verlauf nachträglich editieren oder löschen. Angewendete Anträge bleiben editierbar (Scan ersetzen/löschen); Antrag löschen ändert die Mitgliedschaft nicht. SEPA-Mandate auf der Personenseite anlegen, ändern oder löschen.</p>
<p><b>Austritt/Tod:</b> beendet die Mitgliedschaft und löscht sofort alle SEPA-Mandate sowie den Kontoinhaber. Stammdaten bleiben; nach konfigurierbaren Jahren (Standard 5) erscheint der Hinweis <b>Löschung fällig</b>. Mit <b>Person löschen</b> (nur ohne aktive Mitgliedschaft) entfernst du MIT-Daten und soft-deletest die Melde-Identity.</p>
<p><b>Jubiläen:</b> nächste Termine auf der Personenseite (z. B. „40. Geburtstag“, „25 Jahre Mitgliedschaft“). Meilensteine in der Konfiguration: feste Alter bzw. Mitgliedsjahre (Komma-Liste) und Schrittweite danach — Default Geburtstag 10…70, dann alle 5; Mitgliedschaft 20/25/40/45/50, dann alle 5.</p>
<p>Melde-<b>Active</b> (regelmäßig dabei / keine Karteileiche) bleibt in der Meldeliste und ist nicht der Mitgliedstyp.</p>
'
);

$sections[] = array(
    'id' => 'jubilaeen',
    'title' => 'Kalender / Jubiläen',
    'visible' => hasPermission('perm_showJubilees'),
    'body' => '
<p>Unter <b>Jubiläen</b> in der Navigation (Recht <code>perm_showJubilees</code>): <b>Kalender</b> (Monat) und <b>Liste</b> (Jahr). Berechnete Jubiläen: <b>Geburtstag</b> (aus Stammdaten) und <b>Mitgliedschaft</b> (Jahre seit Eintritt der offenen Mitgliedszeit, nur aktuelle Mitglieder). Klick führt zur Person.</p>
<p>Meilensteine pflegst du unter Admin → Konfiguration (<code>jubileeBirthdayAges</code>, <code>jubileeBirthdayStepAfter</code>, <code>jubileeMembershipYears</code>, <code>jubileeMembershipStepAfter</code>).</p>
'
);

$sections[] = array(
    'id' => 'sepa',
    'title' => 'SEPA',
    'body' => '
<p>Unter <b>SEPA</b> erscheinen Lastschriftmandate: interne Mandatsreferenz (automatisch vergeben), Person, <b>Mitgliedschaft</b> (Aktiv / Fördernd), maskierte IBAN, Gültigkeit und Mandatsstatus. Anlegen und Korrektur erfolgen auf der <b>Personenseite</b> (IBAN und Kreditinstitut dort unmaskiert editierbar).</p>
<p>Bei Austritt oder Tod werden Mandate und Kontoinhaber gelöscht.</p>
'
);

$sections[] = array(
    'id' => 'dokumente',
    'title' => 'Dokumente',
    'visible' => $canShowUsers,
    'body' => '
<p>Auf der <b>Personenseite</b> liegt das Dokumentenverzeichnis: Typen <b>Beitritt</b>, <b>Austritt</b>, <b>Kommunikation</b>, <b>Sonstiges</b>. Dateien (PDF/Bilder) werden lokal unter <code>uploads/persons/</code> gespeichert. Öffnen und Löschen erfolgen dort.</p>
<p>Das interaktive <b>Beitrittsformular</b> erreichst du von der Personenseite oder direkt über <b>Person anlegen → Beitrittsformular</b>; ein Scan vom Formular landet zusätzlich als Dokument Typ Beitritt.</p>
'
);

$sections[] = array(
    'id' => 'login-sso',
    'title' => 'Login &amp; Meldeliste',
    'body' => '
<p>Die Mitgliederverwaltung teilt die Benutzerkonten mit der Meldeliste. <b>Modulzugang</b> (Login) nur mit Melde-Recht Mitgliederverwaltung. <b>In-App-Rechte</b> setzt du unter Admin → Berechtigungen (<code>mit_Permissions</code>) nur für Nutzer mit diesem Melde-Zugang: Benutzer anzeigen/bearbeiten, Rechte bearbeiten, Jubiläen anzeigen, Log anzeigen. Nav und Rechte-Matrix nutzen Gruppenfarben in Melde-Reihenfolge (Nutzer → Jubiläen → System).</p>
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
'.($canEditPermissions ? '
<li><b>Berechtigungen</b> – Matrix für MIT-Rechte in Melde-Reihenfolge (Nutzer: anzeigen/bearbeiten/Rechte; Jubiläen; System: Log) nur für Nutzer mit Melde-Zugang Mitgliederverwaltung. Wenn noch niemand Rechte hat, erhält der erste erfolgreiche Login automatisch alle Rechte</li>
' : '').'
'.($canEditConfig ? '
<li><b>Konfiguration</b> – Site-Name, URLs, Farben, Farbschema und Beitrittsformular-Texte (Platzhalter <code>{org}</code>, <code>{name}</code>, <code>{fee}</code>, <code>{privacyUrl}</code>; Absätze mit Leerzeile; <code>**fett**</code>). Schema-Version und Schema-Metadaten werden hier nicht bearbeitet</li>
<li><b>Backup</b> – ZIP mit Versionsinfo und SQL nur für Mitgliedschafts-Tabellen (<code>mit_*</code>), nicht Melde-Identity. Download im Browser; Restore mit CSRF und Bestätigung <code>RESTORE</code>; CLI <code>php scripts/restoreBackup.php … --yes</code>. Erfolgreiche Downloads erscheinen im Log</li>
<li><b>Updater</b> – Software-Update vom Remote und Datenbank prüfen/reparieren; der Bericht listet nur Änderungen und Probleme. Nach Deploy ggf. „Datenbank reparieren“ für Schema v4 (<code>mit_Permissions</code>)</li>
' : '').'
'.($canShowLog ? '
<li><b>Log</b> – Anwendungsprotokoll: Stammdaten, Mitgliedschaftsperioden, Beitrittsanträge, SEPA, Dokumente, Rechte und Config (Server-Suche, Live-Aktualisierung, Nachladen beim Scrollen)</li>
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
