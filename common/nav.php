<?php
$navColor = htmlspecialchars((string)$optionsDB['colorNav'], ENT_QUOTES, 'UTF-8');
$navAdminColor = htmlspecialchars((string)$optionsDB['colorNavAdmin'], ENT_QUOTES, 'UTF-8');
$isAdminNav = !empty($_SESSION['admin']);
$showAdminNav = $isAdminNav;
$meldeUrl = isset($optionsDB['urlMeldeliste']) ? trim((string)$optionsDB['urlMeldeliste']) : '';
$masterPage = isset($optionsDB['MasterPage']) ? trim((string)$optionsDB['MasterPage']) : '';
$homeUrl = isset($GLOBALS['optionsDB']['WebSiteURL']) && trim((string)$GLOBALS['optionsDB']['WebSiteURL']) !== ''
    ? (string)$GLOBALS['optionsDB']['WebSiteURL']
    : 'index.php';
$siteName = isset($optionsDB['WebSiteName']) ? trim((string)$optionsDB['WebSiteName']) : '';
$siteNameShort = isset($optionsDB['WebSiteNameShort']) ? trim((string)$optionsDB['WebSiteNameShort']) : '';
if($siteName === '') {
    $siteName = 'Mitgliederverwaltung';
}
if($siteNameShort === '') {
    $siteNameShort = $siteName;
}
?>
<div class="app-titlebar <?php echo htmlspecialchars((string)$optionsDB['colorTitle'], ENT_QUOTES, 'UTF-8'); ?>">
  <h1 class="app-titlebar-name w3-hide-small"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></h1>
  <h1 class="app-titlebar-name w3-hide-large w3-hide-medium"><?php echo htmlspecialchars($siteNameShort, ENT_QUOTES, 'UTF-8'); ?></h1>
  <?php if($masterPage !== '') { ?>
  <a class="app-titlebar-logo" href="<?php echo htmlspecialchars($masterPage, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" title="Vereinshomepage">
    <img src="imgs/Logo.png" alt="Vereinshomepage">
  </a>
  <?php } else { ?>
  <img class="app-titlebar-logo" src="imgs/Logo.png" alt="">
  <?php } ?>
  <p class="app-titlebar-user"><?php
      echo htmlspecialchars((string)$_SESSION['username'], ENT_QUOTES, 'UTF-8');
      if($isAdminNav) echo ' (Admin)';
  ?></p>
</div>
<?php if(function_exists('getBranchName') && (getBranchName() != 'master' || !empty($optionsDB['showBranchBannerAlways']))) { ?>
<div class="w3-yellow w3-padding app-banner"><i class="fas fa-code-branch"></i>
<?php echo 'branch: <b>'.htmlspecialchars(getBranchName(), ENT_QUOTES, 'UTF-8').'</b>'; ?>
</div>
<?php } ?>
<?php
if($isAdminNav) {
    try {
        $schemaMgr = new SchemaManager();
        if($schemaMgr->isSchemaOutdated()) {
            $inst = (int)$schemaMgr->getInstalledSchemaVersion();
            $exp = (int)$schemaMgr->getExpectedSchemaVersion();
            echo '<div class="w3-orange w3-padding app-banner"><i class="fas fa-database"></i> '
                .'Neue Datenbank-Version verfügbar (installiert: <b>'.$inst.'</b>, Soll: <b>'.$exp.'</b>). '
                .'Bitte im <a href="updater.php"><b>Updater</b></a> „Datenbank reparieren“ ausführen '
                .'oder „Update durchführen“ (aktualisiert die DB bei Bedarf mit).'
                .'</div>';
        }
    }
    catch(Throwable $e) {
        // Schema-Check darf die Navigation nicht abbrechen
    }
}
?>
<div class="app-shell">
<nav class="app-nav <?php echo $navColor; ?>" aria-label="Hauptnavigation">
  <div class="app-nav-primary">
    <a class="app-nav-item <?php getPage('home', 'system'); ?>" href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Übersicht">
      <i class="fas fa-home" aria-hidden="true"></i><span class="nav-label">Übersicht</span>
    </a>
<?php if(hasPermission('perm_showUsers')) { ?>
    <a class="app-nav-item <?php getPage('members', 'nutzer'); ?>" href="members.php" title="Personen">
      <i class="fas fa-users" aria-hidden="true"></i><span class="nav-label">Personen</span>
    </a>
    <a class="app-nav-item <?php getPage('sepa', 'nutzer'); ?>" href="sepa.php" title="SEPA">
      <i class="fas fa-university" aria-hidden="true"></i><span class="nav-label">SEPA</span>
    </a>
    <a class="app-nav-item <?php getPage('documents', 'nutzer'); ?>" href="documents.php" title="Dokumente">
      <i class="fas fa-file-alt" aria-hidden="true"></i><span class="nav-label">Dokumente</span>
    </a>
<?php } ?>
<?php if($meldeUrl !== '') { ?>
    <a class="app-nav-item app-nav-item--secondary <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Meldeliste">
      <i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="nav-label">Meldeliste</span>
    </a>
<?php } ?>
    <a class="app-nav-item app-nav-item--secondary <?php getPage('help', 'system'); ?>" href="help.php" title="Hilfe">
      <i class="fas fa-circle-question" aria-hidden="true"></i><span class="nav-label">Hilfe</span>
    </a>
  </div>

  <div class="app-nav-more-wrap">
    <button type="button" class="app-nav-item app-nav-more-toggle <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" id="appNavMoreToggle" aria-expanded="false" aria-controls="appNavMorePanel" title="Mehr">
      <i class="fas fa-ellipsis-h" aria-hidden="true"></i><span class="nav-label">Mehr</span>
    </button>
    <div class="app-nav-more-backdrop" id="appNavMoreBackdrop" hidden></div>
    <div class="app-nav-more-panel <?php echo $navColor; ?>" id="appNavMorePanel" role="dialog" aria-label="Weitere Navigation">
      <div class="app-nav-more-head">
        <span>Mehr</span>
        <button type="button" class="app-nav-more-close" id="appNavMoreClose" aria-label="Schließen">&times;</button>
      </div>
      <div class="app-nav-more-body">
<?php if($meldeUrl !== '') { ?>
        <a class="app-nav-item app-nav-more-only-mobile <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($meldeUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Meldeliste">
          <i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="nav-label">Meldeliste</span>
        </a>
<?php } ?>
        <a class="app-nav-item app-nav-more-only-mobile <?php getPage('help', 'system'); ?>" href="help.php" title="Hilfe">
          <i class="fas fa-circle-question" aria-hidden="true"></i><span class="nav-label">Hilfe</span>
        </a>
<?php if($showAdminNav) { ?>
        <div class="admin-nav app-nav-admin">
          <div class="app-nav-admin-title"><i class="fas fa-wrench" aria-hidden="true"></i><span class="nav-label">Admin</span></div>
          <div class="w3-bar-block <?php echo $navAdminColor; ?>">
            <div class="w3-dropdown-hover w3-mobile admin-nav-group<?php echo adminNavGroupActiveClass(array('updater', 'log', 'config', 'backup', 'permissions')); ?>">
              <button type="button" class="w3-button w3-mobile w3-block w3-left-align <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>">Verwaltung <i class="fas fa-caret-right admin-nav-caret"></i></button>
              <div class="w3-dropdown-content w3-bar-block w3-card-4 <?php echo $navAdminColor; ?> w3-mobile">
                <a title="Updater" href="updater.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('updater'); ?>"><i class="fas fa-code-branch"></i> Updater</a>
                <a title="Konfiguration" href="config-menu.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('config'); ?>"><i class="fas fa-cogs"></i> Konfiguration</a>
                <a title="Backup" href="backup.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('backup'); ?>"><i class="fas fa-file-archive"></i> Backup</a>
                <a title="Log" href="log.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('log'); ?>"><i class="fas fa-poll"></i> Log</a>
<?php if(hasPermission('perm_editPermissions')) { ?>
                <a title="Berechtigungen" href="permissions.php" class="w3-bar-item w3-button w3-mobile <?php getAdminPage('permissions'); ?>"><i class="fas fa-lock"></i> Berechtigungen</a>
<?php } ?>
              </div>
            </div>
          </div>
        </div>
<?php } ?>
        <a class="app-nav-item <?php echo htmlspecialchars(navGroupClass('system'), ENT_QUOTES, 'UTF-8'); ?>" href="logout.php" title="Ausloggen">
          <i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="nav-label">Ausloggen</span>
        </a>
      </div>
    </div>
  </div>
</nav>
<div class="app-main">
<?php
$motdShort = isset($optionsDB['MessageOfTheDayShort']) ? trim((string)$optionsDB['MessageOfTheDayShort']) : '';
if($motdShort !== '') {
?>
<div class="w3-container w3-card w3-padding <?php echo htmlspecialchars((string)$GLOBALS['optionsDB']['colorWarning'], ENT_QUOTES, 'UTF-8'); ?>">
  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
  </div>
  <div class="w3-col l6 m6 s8 w3-center">
    <?php echo $motdShort; ?>
  </div>
  <div class="w3-col l3 m3 s2 w3-center">
    <i class="fas fa-exclamation-triangle"></i>
  </div>
</div>
<?php
    ob_start();
?>
<div id="MessageOfTheDay" class="w3-modal">
  <div class="w3-modal-content">
    <div class="w3-container">
      <?php echo isset($optionsDB['MessageOfTheDay']) ? $optionsDB['MessageOfTheDay'] : ''; ?>
      <div class="w3-center"><button class="w3-btn w3-blue w3-padding w3-center" onclick="document.getElementById('MessageOfTheDay').style.display='none'">Verstanden</button></div>
      <div class="w3-container">&nbsp;</div>
    </div>
  </div>
</div>
<?php
    if(!isset($_SESSION['MessageOfTheDay'])) {
        $_SESSION['MessageOfTheDay'] = true;
?>
<script>document.getElementById('MessageOfTheDay').style.display='block';</script>
<?php
    }
    deferPageModalHtml(ob_get_clean());
}
?>
<script src="<?php echo assetUrl('js/app-nav.js'); ?>"></script>
