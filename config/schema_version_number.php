<?php
/**
 * Expected DB schema version number.
 * Bump when DBconfig.json, SchemaManager migrations, or ConfigDefaults.php change.
 * v2: Log.Type int, logListChunkSize
 * v3: HoverEffect config default
 * v4: mit_Permissions (show/edit users, edit permissions)
 * v5: MemberProfile + MembershipTypeChange (user hub)
 * v6: Period-based membership (Tenure + TypePeriod + Application); drop Membership Type/Status flags
 *     + Beitrittsformular SEPA/Verein config defaults
 * v7: Individual AnnualFeeCents; Mindestbeitrag aktiv/fördernd in config
 * v8: MembershipApplication.PaymentMethod (sepa|ueberweisung)
 * v9: Retention + Jubiläen ConfigDefaults (membershipRetentionYears, jubilee*)
 * v10: Geburtstags-Jubiläen wie Verein (jubileeBirthdayAges + jubileeBirthdayStepAfter statt JSON-Regel)
 * v11: obsolete Config jubileeBirthdayRule entfernen
 * v12: perm_showJubilees + Berechtigungsgruppe Jubiläen
 */
return 12;
?>
