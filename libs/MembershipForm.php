<?php
/**
 * Beitrittsformular helpers (print/scan parity with Melde Leihe).
 * Legal copy follows the public MVD Beitrittserklärung PDF, tightened for DSGVO/SEPA.
 */
class MembershipForm
{
    const STUB_VORNAME = '(neu)';
    const STUB_NACHNAME = '(Person)';

    /** True if Melde name is the create-to-form stub. */
    public static function isStubIdentityName($vorname, $nachname) {
        return trim((string)$vorname) === self::STUB_VORNAME
            && trim((string)$nachname) === self::STUB_NACHNAME;
    }

    /** Strip stub placeholder for form inputs (empty = user should fill). */
    public static function identityNameForInput($value, $stubConstant) {
        $v = trim((string)$value);
        return ($v === $stubConstant) ? '' : $v;
    }

    public static function storageDir($applicationId) {
        return dirname(__DIR__).'/uploads/membership/'.(int)$applicationId;
    }

    public static function relativeStorageDir($applicationId) {
        return 'uploads/membership/'.(int)$applicationId;
    }

    public static function resolveStoredFile($applicationId, $stored) {
        $applicationId = (int)$applicationId;
        $stored = trim((string)$stored);
        if($applicationId < 1 || $stored === '') {
            return null;
        }
        $base = realpath(self::storageDir($applicationId));
        if($base === false || !is_dir($base)) {
            return null;
        }
        $name = basename(str_replace('\\', '/', $stored));
        if($name === '' || $name === '.' || $name === '..') {
            return null;
        }
        $full = realpath($base.DIRECTORY_SEPARATOR.$name);
        if($full === false || !is_file($full)) {
            return null;
        }
        if(strpos($full, $base.DIRECTORY_SEPARATOR) !== 0 && $full !== $base) {
            return null;
        }
        return $full;
    }

    /** Basename without extension: MVD-Beitritt-{userId}-{Name} */
    public static function fileBasename($userId, $vorname = '', $nachname = '') {
        $userId = (int)$userId;
        $name = self::sanitizeFileNamePart(trim((string)$vorname.' '.(string)$nachname));
        if($name === '') {
            $name = 'ohne-Namen';
        }
        return 'MVD-Beitritt-'.$userId.'-'.$name;
    }

    public static function sanitizeFileNamePart($raw) {
        $s = trim((string)$raw);
        if($s === '') {
            return '';
        }
        $s = strtr($s, array(
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ));
        $s = preg_replace('/[^A-Za-z0-9._-]+/', '-', $s);
        $s = preg_replace('/-+/', '-', (string)$s);
        return trim((string)$s, '-._');
    }

    /**
     * @param array $file $_FILES entry
     * @param int $userId Melde user id for filename
     * @param string $vorname
     * @param string $nachname
     * @return string|false basename
     */
    public static function storeUpload($applicationId, array $file, $userId = 0, $vorname = '', $nachname = '') {
        $applicationId = (int)$applicationId;
        if($applicationId < 1) {
            return false;
        }
        if(!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        if(!empty($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $ext = self::uploadExtension($file);
        if($ext === null) {
            return false;
        }
        $dir = self::storageDir($applicationId);
        if(!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return false;
        }
        $stem = self::fileBasename($userId > 0 ? $userId : $applicationId, $vorname, $nachname);
        $name = $stem.'.'.$ext;
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        if(is_file($target)) {
            $name = $stem.'-'.date('Ymd-His').'.'.$ext;
            $target = $dir.DIRECTORY_SEPARATOR.$name;
        }
        if(!move_uploaded_file($file['tmp_name'], $target)) {
            return false;
        }
        return $name;
    }

    /**
     * Resolve allowed scan/doc extension from filename and/or file contents.
     * Accepts PDF, JPEG, PNG (also gif/webp).
     * @param array $file $_FILES entry
     * @return string|null normalized extension (jpg not jpeg)
     */
    public static function uploadExtension(array $file) {
        $orig = isset($file['name']) ? (string)$file['name'] : 'scan';
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $allowed = array('pdf', 'jpg', 'png', 'gif', 'webp');
        if(in_array($ext, $allowed, true)) {
            return $ext;
        }
        $tmp = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
        if($tmp === '' || !is_file($tmp)) {
            return null;
        }
        $mime = '';
        if(function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if($fi) {
                $mime = (string)finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        $byMime = array(
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        );
        return isset($byMime[$mime]) ? $byMime[$mime] : null;
    }

    public static function deleteScan(MembershipApplication $app) {
        if((int)$app->Index < 1) {
            return false;
        }
        $path = self::resolveStoredFile((int)$app->Index, (string)$app->ScanFile);
        if($path !== null && is_file($path)) {
            @unlink($path);
        }
        $app->ScanFile = null;
        if($app->Status === 'ready') {
            $app->Status = 'draft';
        }
        return $app->save();
    }

    public static function cfg($key, $default = '') {
        if(isset($GLOBALS['optionsDB'][$key]) && trim((string)$GLOBALS['optionsDB'][$key]) !== '') {
            return trim((string)$GLOBALS['optionsDB'][$key]);
        }
        return $default;
    }

    public static function orgName() {
        return self::cfg('VereinName', 'Musikverein Bonn-Duisdorf gegr. 1949 e.V.');
    }

    public static function orgNameShort() {
        return self::cfg('VereinNameShort', 'Musikverein Bonn-Duisdorf');
    }

    public static function privacyUrl() {
        $url = self::cfg('PrivacyUrl', '');
        if($url === '') {
            $url = self::cfg('MasterPage', '');
        }
        $url = rtrim(trim((string)$url), '/');
        if($url === '' || $url === 'https://example.org') {
            return '';
        }
        return $url;
    }

    public static function privacyLinkHtml() {
        $raw = self::privacyUrl();
        if($raw === '') {
            return '';
        }
        $url = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        return '<a href="'.$url.'">'.$url.'</a>';
    }

    /**
     * Creditor (Verein) bank details as shown on the public PDF.
     * @return array{bank:string,iban:string,bic:string,creditorId:string}
     */
    public static function creditorBank() {
        return array(
            'bank' => self::cfg('SepaCreditorBank', 'VR-Bank Bonn Rhein-Sieg eG'),
            'iban' => self::cfg('SepaCreditorIban', 'DE12 3706 9520 8008 6120 14'),
            'bic' => self::cfg('SepaCreditorBic', 'GENODED1RST'),
            'creditorId' => self::cfg('SepaCreditorId', ''),
        );
    }

    /** Mindestbeitrag in Cent (Config in €). $reduced → ermäßigt (Studierende/Minderjährige). */
    public static function minFeeCents($type, $reduced = false) {
        if($reduced) {
            return self::minFeeCentsReduced();
        }
        $type = strtolower(trim((string)$type));
        $euroKey = ($type === 'foerdernd') ? 'BeitragMindestFoerdernd' : 'BeitragMindestAktiv';
        $centKey = ($type === 'foerdernd') ? 'BeitragMindestFoerderndCents' : 'BeitragMindestAktivCents';
        if(isset($GLOBALS['optionsDB'][$euroKey]) && trim((string)$GLOBALS['optionsDB'][$euroKey]) !== '') {
            $parsed = self::parseEuroToCents($GLOBALS['optionsDB'][$euroKey]);
            if($parsed !== null) {
                return max(0, $parsed);
            }
        }
        if(isset($GLOBALS['optionsDB'][$centKey]) && trim((string)$GLOBALS['optionsDB'][$centKey]) !== '') {
            return max(0, (int)$GLOBALS['optionsDB'][$centKey]);
        }
        $parsed = self::parseEuroToCents('20,00');
        return $parsed !== null ? max(0, $parsed) : 2000;
    }

    /** Ermäßigter Mindestbeitrag (Studierende/Minderjährige), Config BeitragMindestErmaessigt. */
    public static function minFeeCentsReduced() {
        if(isset($GLOBALS['optionsDB']['BeitragMindestErmaessigt'])
            && trim((string)$GLOBALS['optionsDB']['BeitragMindestErmaessigt']) !== '') {
            $parsed = self::parseEuroToCents($GLOBALS['optionsDB']['BeitragMindestErmaessigt']);
            if($parsed !== null) {
                return max(0, $parsed);
            }
        }
        $parsed = self::parseEuroToCents('10,00');
        return $parsed !== null ? max(0, $parsed) : 1000;
    }

    /** @return array{aktiv:int,foerdernd:int,ermaessigt:int} */
    public static function minFeeCentsByType() {
        return array(
            'aktiv' => self::minFeeCents('aktiv', false),
            'foerdernd' => self::minFeeCents('foerdernd', false),
            'ermaessigt' => self::minFeeCentsReduced(),
        );
    }

    public static function formatEuroFromCents($cents) {
        $cents = (int)$cents;
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    /** Parse "20", "20,00", "20.00 €" → cents. */
    public static function parseEuroToCents($raw) {
        if($raw === null || $raw === '') {
            return null;
        }
        if(is_int($raw)) {
            // Values >= 1000 treated as already-cents only if caller passes cents intentionally;
            // form posts euro amounts as strings — ints from DB are cents elsewhere.
            return max(0, $raw);
        }
        if(is_float($raw)) {
            return (int)round($raw * 100);
        }
        $s = trim((string)$raw);
        $s = str_replace(array('€', ' '), '', $s);
        if(strpos($s, ',') !== false && strpos($s, '.') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        elseif(strpos($s, ',') !== false) {
            $s = str_replace(',', '.', $s);
        }
        if(!is_numeric($s)) {
            return null;
        }
        return (int)round(((float)$s) * 100);
    }

    /** Individual fee at least the applicable minimum (standard or ermäßigt). */
    public static function clampFeeCents($cents, $type, $reduced = false) {
        $min = self::minFeeCents($type, $reduced);
        $cents = (int)$cents;
        if($cents < $min) {
            return $min;
        }
        return $cents;
    }

    public static function em($text) {
        return '<strong class="loan-form-em">'.htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8').'</strong>';
    }

    /** @return array<string,string> */
    public static function formTextDefaults() {
        if(!function_exists('getMembershipFormTextDefaults')) {
            require_once dirname(__DIR__).'/config/MembershipFormTextDefaults.php';
        }
        return getMembershipFormTextDefaults();
    }

    public static function formText($key) {
        $defaults = self::formTextDefaults();
        $def = isset($defaults[$key]) ? $defaults[$key] : '';
        return self::cfg($key, $def);
    }

    /**
     * Escape template, apply **bold**, inject HTML placeholders ({org}, {fee}, …).
     * @param array<string,string> $htmlVars placeholder => trusted HTML
     */
    public static function formatFormTemplate($template, array $htmlVars = array()) {
        $out = htmlspecialchars((string)$template, ENT_QUOTES, 'UTF-8');
        $out = preg_replace('/\*\*(.+?)\*\*/s', '<strong class="loan-form-em">$1</strong>', $out);
        foreach($htmlVars as $key => $html) {
            $out = str_replace('{'.htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8').'}', $html, $out);
            $out = str_replace('{'.$key.'}', $html, $out);
        }
        return $out;
    }

    /**
     * @param array<string,string> $htmlVars
     * @return list<string>
     */
    public static function formTextParagraphsHtml($key, array $htmlVars = array()) {
        $raw = str_replace("\r\n", "\n", self::formText($key));
        $parts = preg_split("/\n\s*\n/", $raw);
        $out = array();
        foreach($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', str_replace("\n", ' ', (string)$part)));
            if($part === '') {
                continue;
            }
            $out[] = self::formatFormTemplate($part, $htmlVars);
        }
        if(!$out) {
            $out[] = self::formatFormTemplate(self::formText($key), $htmlVars);
        }
        return $out;
    }

    public static function leadSentenceHtml($nameHtml) {
        return self::formatFormTemplate(self::formText('membershipFormLead'), array(
            'name' => $nameHtml,
            'org' => self::em(self::orgName()),
        ));
    }

    /** @return list<string> */
    public static function membershipRulesParagraphsHtml() {
        return self::formTextParagraphsHtml('membershipFormRules');
    }

    /**
     * @return list<string>
     */
    public static function mediaConsentParagraphsHtml() {
        return self::formTextParagraphsHtml('membershipFormMediaConsent', array(
            'org' => self::em(self::orgName()),
            'privacyUrl' => self::privacyLinkHtml(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function privacyParagraphsHtml() {
        return self::formTextParagraphsHtml('membershipFormPrivacy', array(
            'org' => self::em(self::orgName()),
            'privacyUrl' => self::privacyLinkHtml(),
        ));
    }

    /**
     * @param int|null $individualCents
     * @param string $type
     * @param bool $reduced
     * @return array{intro:list<string>,mandate:list<string>,note:string}
     */
    public static function sepaTextsHtml($individualCents = null, $type = 'aktiv', $reduced = false) {
        $type = ($type === 'foerdernd') ? 'foerdernd' : 'aktiv';
        $fee = self::clampFeeCents(
            $individualCents !== null ? (int)$individualCents : self::minFeeCents($type, $reduced),
            $type,
            $reduced
        );
        $vars = array(
            'org' => self::em(self::orgName()),
            'fee' => self::feeLiveHtml($fee),
        );
        $noteParas = self::formTextParagraphsHtml('membershipFormSepaNote', $vars);
        return array(
            'intro' => self::formTextParagraphsHtml('membershipFormSepaIntro', $vars),
            'mandate' => self::formTextParagraphsHtml('membershipFormSepaMandate', $vars),
            'note' => isset($noteParas[0]) ? $noteParas[0] : '',
        );
    }

    /**
     * @param int|null $individualCents
     * @param string $type
     * @param bool $reduced
     * @return list<string>
     */
    public static function transferTextsHtml($individualCents = null, $type = 'aktiv', $reduced = false) {
        $type = ($type === 'foerdernd') ? 'foerdernd' : 'aktiv';
        $fee = self::clampFeeCents(
            $individualCents !== null ? (int)$individualCents : self::minFeeCents($type, $reduced),
            $type,
            $reduced
        );
        return self::formTextParagraphsHtml('membershipFormTransfer', array(
            'org' => self::em(self::orgName()),
            'fee' => self::feeLiveHtml($fee),
        ));
    }

    /** Jahresbeitrag markup; `.membership-fee-live` is synced by form JS. */
    public static function feeLiveHtml($cents) {
        return '<strong class="loan-form-em membership-fee-live">'
            .htmlspecialchars(self::formatEuroFromCents((int)$cents), ENT_QUOTES, 'UTF-8')
            .'</strong>';
    }

    /** @return bool */
    public static function isFilled($value) {
        return trim((string)$value) !== '';
    }

    /**
     * Prefill application from Melde user + MIT profile / existing membership fee.
     * Entry date: open tenure DateFrom if member, else kept/desired, else today.
     */
    public static function prefill(MembershipApplication $app, IdentityUser $user, MemberProfile $profile) {
        if($app->Birthday === null || $app->Birthday === '') {
            $app->Birthday = $profile->Birthday;
        }
        foreach(array('Phone', 'Street', 'Zip', 'City', 'Country', 'AccountHolder') as $k) {
            if($app->$k === null || $app->$k === '') {
                $app->$k = $profile->$k;
            }
        }
        if($app->Country === null || $app->Country === '') {
            $app->Country = 'DE';
        }
        if($app->DesiredType === null || $app->DesiredType === '') {
            $app->DesiredType = 'aktiv';
        }
        if($app->PaymentMethod === null || $app->PaymentMethod === '') {
            $app->PaymentMethod = 'sepa';
        }
        if($app->AccountHolder === null || $app->AccountHolder === '') {
            if(!self::isStubIdentityName($user->Vorname, $user->Nachname)) {
                $name = trim((string)$user->getName());
                if($name !== '') {
                    $app->AccountHolder = $name;
                }
            }
        }

        $mem = new Membership();
        $hasMem = $mem->load_by_user((int)$user->Index);
        if($hasMem) {
            $open = MembershipPeriod::openForMembership((int)$mem->Index);
            if($open && $open->DateFrom) {
                $app->DesiredEntryDate = $open->DateFrom;
                $typeNow = MembershipTypePeriod::userTypeOn((int)$user->Index);
                if($typeNow) {
                    $app->DesiredType = $typeNow;
                }
            }
            if($app->AnnualFeeCents === null || (int)$app->AnnualFeeCents < 1) {
                if($mem->AnnualFeeCents !== null && (int)$mem->AnnualFeeCents > 0) {
                    $app->AnnualFeeCents = (int)$mem->AnnualFeeCents;
                }
                if((int)$mem->FeeReduced === 1) {
                    $app->FeeReduced = 1;
                }
            }
        }
        if($app->DesiredEntryDate === null || $app->DesiredEntryDate === '') {
            $app->DesiredEntryDate = date('Y-m-d');
        }
        $reduced = (int)$app->FeeReduced === 1;
        if($app->AnnualFeeCents === null || (int)$app->AnnualFeeCents < 1) {
            $app->AnnualFeeCents = self::minFeeCents($app->DesiredType, $reduced);
        }
        $app->AnnualFeeCents = self::clampFeeCents((int)$app->AnnualFeeCents, $app->DesiredType, $reduced);
    }

    public static function applyPostFields(MembershipApplication $app, array $post) {
        $app->DesiredType = isset($post['DesiredType']) ? $post['DesiredType'] : 'aktiv';
        $app->PaymentMethod = isset($post['PaymentMethod']) ? $post['PaymentMethod'] : 'sepa';
        $app->FeeReduced = !empty($post['FeeReduced']) ? 1 : 0;
        $app->DesiredEntryDate = isset($post['DesiredEntryDate']) ? trim((string)$post['DesiredEntryDate']) : null;
        $feeRaw = isset($post['AnnualFeeEuro']) ? $post['AnnualFeeEuro'] : null;
        $reduced = (int)$app->FeeReduced === 1;
        $parsed = self::parseEuroToCents($feeRaw);
        if($parsed === null) {
            $parsed = self::minFeeCents($app->DesiredType, $reduced);
        }
        $app->AnnualFeeCents = self::clampFeeCents($parsed, $app->DesiredType, $reduced);
        $app->Birthday = isset($post['Birthday']) ? trim((string)$post['Birthday']) : null;
        $app->Phone = isset($post['Phone']) ? trim((string)$post['Phone']) : null;
        $app->Street = isset($post['Street']) ? trim((string)$post['Street']) : null;
        $app->Zip = isset($post['Zip']) ? trim((string)$post['Zip']) : null;
        $app->City = isset($post['City']) ? trim((string)$post['City']) : null;
        $app->Country = isset($post['Country']) ? trim((string)$post['Country']) : 'DE';
        $app->AccountHolder = isset($post['AccountHolder']) ? trim((string)$post['AccountHolder']) : null;
        $bankName = isset($post['BankName']) ? trim((string)$post['BankName']) : '';
        $ibanRaw = isset($post['Iban']) ? trim((string)$post['Iban']) : '';
        $app->Iban = $ibanRaw !== '' ? formatIbanDisplay($ibanRaw) : null;
        if($bankName === '' && $app->Iban !== null && class_exists('BlzDirectory')) {
            $bankName = BlzDirectory::bankNameFromIban($app->Iban);
        }
        $app->BankName = $bankName !== '' ? $bankName : null;
        $app->Note = isset($post['Note']) ? trim((string)$post['Note']) : null;
        if($app->PaymentMethod !== 'sepa') {
            $app->BankName = null;
            $app->Iban = null;
        }
        if($app->Status !== 'applied') {
            $app->Status = 'draft';
        }
    }

    /**
     * Persist Melde Stammdaten + MIT profile from form POST / application snapshot.
     * @return string empty on success, else flash error
     */
    public static function syncPersonFromPost(IdentityUser $user, MemberProfile $profile, MembershipApplication $app, array $post) {
        $vorname = isset($post['Vorname']) ? trim((string)$post['Vorname']) : '';
        $nachname = isset($post['Nachname']) ? trim((string)$post['Nachname']) : '';
        if($vorname === '' || $nachname === ''
            || self::isStubIdentityName($vorname, $nachname)
            || $vorname === self::STUB_VORNAME
            || $nachname === self::STUB_NACHNAME) {
            return 'Vor- und Nachname sind Pflicht (keine Platzhalter).';
        }
        $user->Vorname = $vorname;
        $user->Nachname = $nachname;
        $user->Email = isset($post['Email']) ? trim((string)$post['Email']) : '';
        if(!$user->saveStammdaten()) {
            return 'Name/E-Mail konnten nicht gespeichert werden.';
        }

        $profile->load_or_create((int)$user->Index);
        $profile->Birthday = $app->Birthday;
        $profile->Phone = $app->Phone;
        $profile->Street = $app->Street;
        $profile->Zip = $app->Zip;
        $profile->City = $app->City;
        $profile->Country = $app->Country;
        $holder = trim((string)$app->AccountHolder);
        if($holder === '') {
            $holder = trim($vorname.' '.$nachname);
        }
        $profile->AccountHolder = $holder !== '' ? $holder : null;
        $profile->save();

        if($app->AccountHolder === null || trim((string)$app->AccountHolder) === '') {
            $app->AccountHolder = $profile->AccountHolder;
        }
        return '';
    }
}
?>
