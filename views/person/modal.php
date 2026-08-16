<?php
/**
 * Person detail modal (profile-shell). Read-only summary; full edit on person.php.
 *
 * Expected: $user, $profile, $memberLabel, $addr, $entryDateLabel,
 *           $email, $email2, $phone, $birthdayLabel, $sepaRows,
 *           $showOpenButton, $canEdit
 */
$name = $user->getName();
$btn = $GLOBALS['optionsDB']['colorBtnSubmit'];
?>
<div class="profile-shell modal-shell user-modal">
  <header class="profile-hero">
    <div class="profile-hero-text">
      <p class="profile-kicker">Person</p>
      <h2 class="profile-title"><?php echo h($name !== '' ? $name : 'Profil'); ?></h2>
    </div>
    <div class="profile-hero-actions">
<?php if(!empty($showOpenButton)) { ?>
      <a class="w3-btn profile-btn-primary <?php echo h($btn); ?> w3-border w3-mobile"
         href="person.php?id=<?php echo (int)$user->Index; ?>"><?php echo !empty($canEdit) ? 'Bearbeiten' : 'Öffnen'; ?></a>
<?php } ?>
      <button type="button" class="modal-close w3-button" onclick="closeModal()" aria-label="Schließen">&times;</button>
    </div>
  </header>

  <div class="profile-grid profile-grid--3">
    <section class="profile-col" aria-labelledby="person-modal-person">
      <h3 id="person-modal-person" class="profile-col-title">Person</h3>
      <div class="profile-field">
        <span class="profile-label">Mitgliedschaft</span>
        <div class="profile-value"><?php echo h($memberLabel); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Eintritt</span>
        <div class="profile-value"><?php echo h($entryDateLabel !== '' ? $entryDateLabel : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Geburtstag</span>
        <div class="profile-value"><?php echo h($birthdayLabel !== '' ? $birthdayLabel : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Login</span>
        <div class="profile-value"><?php echo h((string)$user->login !== '' ? (string)$user->login : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">User-ID</span>
        <div class="profile-value"><?php echo (int)$user->Index; ?></div>
      </div>
    </section>

    <section class="profile-col" aria-labelledby="person-modal-kontakt">
      <h3 id="person-modal-kontakt" class="profile-col-title">Kontakt</h3>
      <div class="profile-field">
        <span class="profile-label">E-Mail</span>
        <div class="profile-value"><?php
          if($email !== '') {
              echo '<a href="mailto:'.h($email).'">'.h($email).'</a>';
          }
          else {
              echo '—';
          }
        ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">E-Mail 2</span>
        <div class="profile-value"><?php
          if($email2 !== '') {
              echo '<a href="mailto:'.h($email2).'">'.h($email2).'</a>';
          }
          else {
              echo '—';
          }
        ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Telefon</span>
        <div class="profile-value"><?php echo h($phone !== '' ? $phone : '—'); ?></div>
      </div>
    </section>

    <section class="profile-col" aria-labelledby="person-modal-adresse">
      <h3 id="person-modal-adresse" class="profile-col-title">Adresse</h3>
      <div class="profile-field">
        <span class="profile-label">Anschrift</span>
        <div class="profile-value"><?php echo h($addr !== '' ? $addr : '—'); ?></div>
      </div>
      <div class="profile-field">
        <span class="profile-label">Kontoinhaber</span>
        <div class="profile-value"><?php echo h((string)$profile->AccountHolder !== '' ? (string)$profile->AccountHolder : '—'); ?></div>
      </div>
<?php
$sepaRows = (isset($sepaRows) && is_array($sepaRows)) ? $sepaRows : array();
if(count($sepaRows)) {
?>
      <div class="profile-field">
        <span class="profile-label">SEPA</span>
        <div class="profile-value person-modal-sepa">
<?php foreach($sepaRows as $sr) {
    $ref = isset($sr['ref']) ? (string)$sr['ref'] : '';
    $iban = isset($sr['iban']) ? (string)$sr['iban'] : '';
    $valid = isset($sr['valid']) ? (string)$sr['valid'] : '';
    $active = !empty($sr['active']);
    ?>
          <div class="person-modal-sepa-row">
            <span class="person-modal-sepa-ref"><?php echo h($ref !== '' ? $ref : 'Mandat'); ?></span>
            <?php echo ibanRevealHtml($iban); ?>
            <span class="w3-small w3-text-grey"><?php
              echo h($valid);
              echo $active ? ' · aktiv' : ' · inaktiv';
            ?></span>
          </div>
<?php } ?>
        </div>
      </div>
<?php } ?>
    </section>
  </div>
</div>
