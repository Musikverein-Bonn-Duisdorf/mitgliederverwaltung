<?php
/**
 * Default Beitrittsformular texts (Admin → Konfiguration, Type text).
 * Placeholders: {org} {name} {fee} {privacyUrl}. Emphasis: **wort**.
 * Paragraphs: blank line.
 *
 * @return array<string,string> parameter => default value
 */
function getMembershipFormTextDefaults() {
    return array(
        'membershipFormLead' => 'Hiermit erkläre ich {name} den Beitritt zum {org} als',
        'membershipFormRules' =>
            "Mit dem Beitritt erkenne ich die Satzung und die Beschlüsse der Mitgliederversammlung an."
            ." Die Mitgliedschaft beginnt mit dem angegebenen Eintrittsdatum.\n\n"
            ."Kündigung nur **schriftlich** zum **Kalenderjahresende** (Zugang beim Vorstand)."
            ." Vereinsinventar ist beim Austritt unverzüglich zurückzugeben.",
        'membershipFormPrivacy' =>
            '{org} verarbeitet die Angaben zur Begründung und Durchführung der Mitgliedschaft'
            .' (Art. 6 Abs. 1 lit. b DSGVO). Weitergabe nur bei gesetzlicher Pflicht oder zur Vertragserfüllung'
            .' (z. B. Bank bei Beitragseinzug). Speicherung für die Mitgliedschaftsdauer und danach'
            .' nach Aufbewahrungspflichten. Rechte: Auskunft, Berichtigung, Löschung, Einschränkung,'
            .' Widerspruch, Beschwerde bei einer Aufsichtsbehörde. Details: {privacyUrl}.',
        'membershipFormMediaConsent' =>
            'Als **aktives Mitglied** willige ich ein, dass {org}'
            .' bei öffentlichen Auftritten und vergleichbaren Veranstaltungen Bild-, Ton- und Videoaufnahmen'
            .' anfertigen und zur Vereinsdarstellung veröffentlichen darf'
            .' (Website, soziale Medien, Programmhefte, Presse).'
            .' Widerruf mit Wirkung für die Zukunft möglich; Details: {privacyUrl}.',
        'membershipFormSepaIntro' =>
            'Ich zahle den Jahresbeitrag von {fee}'
            .' per SEPA-Lastschrift (erstmals für das Beitrittsjahr, danach jährlich).'
            .' Vorabankündigung in der Regel mindestens 14 Tage vor dem Einzug.',
        'membershipFormSepaMandate' =>
            'Ich ermächtige {org}, Zahlungen von meinem Konto per Lastschrift einzuziehen,'
            .' und weise mein Kreditinstitut an, die Lastschriften einzulösen.'
            .' Erstattung binnen acht Wochen ab Belastung nach den Bedingungen meines Kreditinstituts möglich.'
            .' Widerruf des Mandats jederzeit mit Wirkung für die Zukunft.',
        'membershipFormSepaNote' =>
            'Bei fehlender Deckung bestehen keine Einlösungspflicht und ggf. Rücklastschriftkosten zu meinen Lasten.',
        'membershipFormTransfer' =>
            'Ich zahle den Jahresbeitrag von {fee}'
            .' selbst per Überweisung auf das Vereinskonto (erstmals für das Beitrittsjahr, danach jährlich'
            .' nach Aufforderung bzw. Fälligkeit).',
    );
}
?>
