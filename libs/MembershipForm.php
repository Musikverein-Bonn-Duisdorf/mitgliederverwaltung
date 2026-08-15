<?php
/**
 * Beitrittsformular helpers (print/scan parity with Melde Leihe).
 * Legal copy follows the public MVD Beitrittserklärung PDF, tightened for DSGVO/SEPA.
 */
class MembershipForm
{
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

    /**
     * @param array $file $_FILES entry
     * @return string|false basename
     */
    public static function storeUpload($applicationId, array $file) {
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
        $orig = isset($file['name']) ? (string)$file['name'] : 'scan';
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp');
        if(!in_array($ext, $allowed, true)) {
            return false;
        }
        $dir = self::storageDir($applicationId);
        if(!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return false;
        }
        $name = 'beitritt-'.date('Ymd-His').'.'.$ext;
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        if(!move_uploaded_file($file['tmp_name'], $target)) {
            return false;
        }
        return $name;
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
        $master = self::cfg('MasterPage', 'https://www.musikverein-bonn-duisdorf.de');
        $master = rtrim($master, '/');
        if($master === '' || $master === 'https://example.org') {
            return 'https://www.musikverein-bonn-duisdorf.de';
        }
        return $master;
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

    /** Mindestbeitrag in Cent für Typ aktiv|foerdernd. */
    public static function minFeeCents($type) {
        $type = strtolower(trim((string)$type));
        if($type === 'foerdernd') {
            return max(0, (int)self::cfg('BeitragMindestFoerderndCents', '2000'));
        }
        return max(0, (int)self::cfg('BeitragMindestAktivCents', '2000'));
    }

    /** @return array{aktiv:int,foerdernd:int} */
    public static function minFeeCentsByType() {
        return array(
            'aktiv' => self::minFeeCents('aktiv'),
            'foerdernd' => self::minFeeCents('foerdernd'),
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

    /** Individual fee at least the type minimum. */
    public static function clampFeeCents($cents, $type) {
        $min = self::minFeeCents($type);
        $cents = (int)$cents;
        if($cents < $min) {
            return $min;
        }
        return $cents;
    }

    public static function em($text) {
        return '<strong class="loan-form-em">'.htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8').'</strong>';
    }

    /** Austritt / Mitgliedschaftsregeln. @return list<string> */
    public static function membershipRulesParagraphsHtml() {
        return array(
            'Mit dem Beitritt erkenne ich die Satzung und die Beschlüsse der Mitgliederversammlung an.'
            .' Die Mitgliedschaft beginnt mit dem angegebenen Eintrittsdatum.',
            'Kündigung nur <strong class="loan-form-em">schriftlich</strong> zum'
            .' <strong class="loan-form-em">Kalenderjahresende</strong> (Zugang beim Vorstand).'
            .' Vereinsinventar ist beim Austritt unverzüglich zurückzugeben.',
        );
    }

    /**
     * Einwilligung Bild/Ton/Video — nur für aktive Mitgliedschaft (öffentliche Auftritte).
     * @return list<string> HTML paragraphs
     */
    public static function mediaConsentParagraphsHtml() {
        $org = self::em(self::orgName());
        $url = htmlspecialchars(self::privacyUrl(), ENT_QUOTES, 'UTF-8');
        return array(
            'Als <strong class="loan-form-em">aktives Mitglied</strong> willige ich ein, dass '.$org
            .' bei öffentlichen Auftritten und vergleichbaren Veranstaltungen Bild-, Ton- und Videoaufnahmen'
            .' anfertigen und zur Vereinsdarstellung veröffentlichen darf'
            .' (Website, soziale Medien, Programmhefte, Presse).'
            .' Widerruf mit Wirkung für die Zukunft möglich; Details: <a href="'.$url.'">'.$url.'</a>.',
        );
    }

    /**
     * Datenschutzhinweis (Information nach Art. 13 DSGVO; Mitgliedschaft = Vertrag, keine reine Einwilligung).
     * @return list<string> HTML paragraphs (escaped content, em allowed)
     */
    public static function privacyParagraphsHtml() {
        $org = self::em(self::orgName());
        $url = htmlspecialchars(self::privacyUrl(), ENT_QUOTES, 'UTF-8');
        return array(
            $org.' verarbeitet die Angaben zur Begründung und Durchführung der Mitgliedschaft'
            .' (Art. 6 Abs. 1 lit. b DSGVO). Weitergabe nur bei gesetzlicher Pflicht oder zur Vertragserfüllung'
            .' (z.&nbsp;B. Bank bei Lastschrift). Speicherung für die Mitgliedschaftsdauer und danach'
            .' nach Aufbewahrungspflichten. Rechte: Auskunft, Berichtigung, Löschung, Einschränkung,'
            .' Widerspruch, Beschwerde bei einer Aufsichtsbehörde. Details: <a href="'.$url.'">'.$url.'</a>.',
        );
    }

    /**
     * Einleitung und SEPA-Mandatstext (Core-Lastschrift).
     * @param int|null $individualCents gewählter Jahresbeitrag
     * @param string $type aktiv|foerdernd
     * @return array{intro:list<string>,mandate:list<string>,note:string} HTML fragments
     */
    public static function sepaTextsHtml($individualCents = null, $type = 'aktiv') {
        $org = self::em(self::orgName());
        $type = ($type === 'foerdernd') ? 'foerdernd' : 'aktiv';
        $fee = self::clampFeeCents(
            $individualCents !== null ? (int)$individualCents : self::minFeeCents($type),
            $type
        );
        $feeFmt = htmlspecialchars(self::formatEuroFromCents($fee), ENT_QUOTES, 'UTF-8');

        return array(
            'intro' => array(
                'Ich zahle den Jahresbeitrag von <strong class="loan-form-em">'.$feeFmt.'</strong>'
                .' per SEPA-Lastschrift (erstmals für das Beitrittsjahr, danach jährlich).'
                .' Vorabankündigung in der Regel mindestens 14 Tage vor dem Einzug.',
            ),
            'mandate' => array(
                'Ich ermächtige '.$org.', Zahlungen von meinem Konto per Lastschrift einzuziehen,'
                .' und weise mein Kreditinstitut an, die Lastschriften einzulösen.'
                .' Erstattung binnen acht Wochen ab Belastung nach den Bedingungen meines Kreditinstituts möglich.'
                .' Widerruf des Mandats jederzeit mit Wirkung für die Zukunft.',
            ),
            'note' => 'Bei fehlender Deckung bestehen keine Einlösungspflicht und ggf. Rücklastschriftkosten zu meinen Lasten.',
        );
    }

    /**
     * Kurztext für Beitragszahlung per Überweisung (Vereinskonto).
     * @param int|null $individualCents
     * @param string $type
     * @return list<string>
     */
    public static function transferTextsHtml($individualCents = null, $type = 'aktiv') {
        $type = ($type === 'foerdernd') ? 'foerdernd' : 'aktiv';
        $fee = self::clampFeeCents(
            $individualCents !== null ? (int)$individualCents : self::minFeeCents($type),
            $type
        );
        $feeFmt = htmlspecialchars(self::formatEuroFromCents($fee), ENT_QUOTES, 'UTF-8');
        return array(
            'Ich zahle den Jahresbeitrag von <strong class="loan-form-em">'.$feeFmt.'</strong>'
            .' selbst per Überweisung auf das Vereinskonto (erstmals für das Beitrittsjahr, danach jährlich'
            .' nach Aufforderung bzw. Fälligkeit).',
        );
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
        foreach(array('Phone', 'Phone2', 'Street', 'Zip', 'City', 'Country', 'AccountHolder') as $k) {
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
            $app->AccountHolder = $user->getName();
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
            }
        }
        if($app->DesiredEntryDate === null || $app->DesiredEntryDate === '') {
            $app->DesiredEntryDate = date('Y-m-d');
        }
        if($app->AnnualFeeCents === null || (int)$app->AnnualFeeCents < 1) {
            $app->AnnualFeeCents = self::minFeeCents($app->DesiredType);
        }
        $app->AnnualFeeCents = self::clampFeeCents((int)$app->AnnualFeeCents, $app->DesiredType);
    }

    public static function applyPostFields(MembershipApplication $app, array $post) {
        $app->DesiredType = isset($post['DesiredType']) ? $post['DesiredType'] : 'aktiv';
        $app->PaymentMethod = isset($post['PaymentMethod']) ? $post['PaymentMethod'] : 'sepa';
        $app->DesiredEntryDate = isset($post['DesiredEntryDate']) ? trim((string)$post['DesiredEntryDate']) : null;
        $feeRaw = isset($post['AnnualFeeEuro']) ? $post['AnnualFeeEuro'] : null;
        $parsed = self::parseEuroToCents($feeRaw);
        if($parsed === null) {
            $parsed = self::minFeeCents($app->DesiredType);
        }
        $app->AnnualFeeCents = self::clampFeeCents($parsed, $app->DesiredType);
        $app->Birthday = isset($post['Birthday']) ? trim((string)$post['Birthday']) : null;
        $app->Phone = isset($post['Phone']) ? trim((string)$post['Phone']) : null;
        $app->Phone2 = isset($post['Phone2']) ? trim((string)$post['Phone2']) : null;
        $app->Street = isset($post['Street']) ? trim((string)$post['Street']) : null;
        $app->Zip = isset($post['Zip']) ? trim((string)$post['Zip']) : null;
        $app->City = isset($post['City']) ? trim((string)$post['City']) : null;
        $app->Country = isset($post['Country']) ? trim((string)$post['Country']) : 'DE';
        $app->AccountHolder = isset($post['AccountHolder']) ? trim((string)$post['AccountHolder']) : null;
        $app->BankName = isset($post['BankName']) ? trim((string)$post['BankName']) : null;
        $app->Iban = isset($post['Iban']) ? trim((string)$post['Iban']) : null;
        $app->Bic = isset($post['Bic']) ? trim((string)$post['Bic']) : null;
        $app->Note = isset($post['Note']) ? trim((string)$post['Note']) : null;
        if($app->PaymentMethod !== 'sepa') {
            $app->BankName = null;
            $app->Iban = null;
            $app->Bic = null;
        }
        if($app->Status !== 'applied') {
            $app->Status = 'draft';
        }
    }
}
?>
