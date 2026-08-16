<?php
require_once __DIR__.'/libs/sessionBootstrap.php';
mitConfigureSession();
$_SESSION['page'] = 'members';
$_SESSION['adminpage'] = false;
include 'common/header.php';
requirePermission('perm_editUsers');

$flash = '';
if(!empty($_SESSION['personFlash'])) {
    $flash = (string)$_SESSION['personFlash'];
    unset($_SESSION['personFlash']);
}

$actions = '<a class="w3-button '.h($optionsDB['colorBtnSubmit']).'" href="members.php" title="Zurück"><i class="fas fa-arrow-left"></i></a>';
adminListPageBegin('Personen', 'Person anlegen', array('actionsHtml' => $actions));
adminListChromeClose(false);

if($flash !== '') {
    echo '<div class="w3-panel '.h($optionsDB['colorLogError']).' w3-padding"><p>'.h($flash).'</p></div>';
}

$inputBg = h($optionsDB['colorInputBackground']);
?>
<form method="post" action="savePerson.php" class="profile-form person-page person-stammdaten">
  <?php echo csrf_field(); ?>

  <div class="profile-grid profile-grid--3">
    <section class="profile-col" aria-labelledby="new-col-person">
      <h3 id="new-col-person" class="profile-col-title">Person</h3>
      <div class="profile-field">
        <label class="profile-label" for="new-vorname">Vorname</label>
        <input id="new-vorname" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="vorname" autofocus autocomplete="given-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="new-nachname">Nachname</label>
        <input id="new-nachname" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="nachname" autocomplete="family-name" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="new-birthday">Geburtstag</label>
        <input id="new-birthday" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="date" name="birthday" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="new-login">Login</label>
        <input id="new-login" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="login" autocomplete="off" />
      </div>
    </section>

    <section class="profile-col" aria-labelledby="new-col-kontakt">
      <h3 id="new-col-kontakt" class="profile-col-title">Kontakt</h3>
      <div class="profile-field">
        <label class="profile-label" for="new-email">E-Mail</label>
        <input id="new-email" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="email" name="email" autocomplete="email" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="new-email2">E-Mail 2</label>
        <input id="new-email2" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="email" name="email2" />
      </div>
      <div class="profile-field">
        <label class="profile-label" for="new-phone">Telefon</label>
        <input id="new-phone" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="tel" name="phone" autocomplete="tel" />
      </div>
    </section>

    <section class="profile-col" aria-labelledby="new-col-adresse">
      <h3 id="new-col-adresse" class="profile-col-title">Adresse</h3>
      <div class="profile-field">
        <label class="profile-label" for="new-street">Straße</label>
        <input id="new-street" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="street" autocomplete="street-address" />
      </div>
      <div class="profile-fields-inline">
        <div class="profile-field">
          <label class="profile-label" for="new-zip">PLZ</label>
          <input id="new-zip" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="zip" autocomplete="postal-code" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="new-city">Ort</label>
          <input id="new-city" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="city" autocomplete="address-level2" />
        </div>
        <div class="profile-field">
          <label class="profile-label" for="new-country">Land</label>
          <input id="new-country" class="w3-input w3-border profile-control <?php echo $inputBg; ?>" type="text" name="country" value="DE" autocomplete="country" />
        </div>
      </div>
      <div class="profile-actions">
        <button type="submit" name="action" value="create_user" class="w3-button profile-btn-primary <?php echo h($optionsDB['colorBtnSubmit']); ?>">Anlegen</button>
        <button type="submit" name="action" value="create_user_to_form" class="w3-button <?php echo h($optionsDB['colorBtnEdit']); ?>" title="Beitrittsformular">Beitrittsformular</button>
      </div>
    </section>
  </div>
</form>
<?php
adminListPageEnd();
include 'common/footer.php';
?>
