<div class="w3-container <?php echo h($optionsDB['colorTitle']); ?>">
  <h1 class="w3-hide-small"><?php echo h($optionsDB['WebSiteName']); ?></h1>
  <h1 class="w3-hide-large w3-hide-medium"><?php echo h($optionsDB['WebSiteNameShort']); ?></h1>
  <p><?php echo h($_SESSION['username']); ?><?php if(!empty($_SESSION['admin'])) echo ' (Admin)'; ?></p>
</div>
<div class="w3-bar <?php echo h($optionsDB['colorNav']); ?>">
  <a href="index.php" class="w3-bar-item w3-button <?php getPage('home'); ?>" title="Start"><i class="fas fa-home"></i></a>
  <a href="members.php" class="w3-bar-item w3-button <?php getPage('members'); ?>" title="Mitglieder"><i class="fas fa-users"></i> Mitglieder</a>
  <a href="sepa.php" class="w3-bar-item w3-button <?php getPage('sepa'); ?>" title="SEPA"><i class="fas fa-university"></i> SEPA</a>
  <a href="documents.php" class="w3-bar-item w3-button <?php getPage('documents'); ?>" title="Dokumente"><i class="fas fa-file-alt"></i> Dokumente</a>
  <a href="logout.php" class="w3-bar-item w3-button w3-right" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
</div>
