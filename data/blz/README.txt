# Bankleitzahlendatei (Deutsche Bundesbank)
#
# Update:  php scripts/updateBlzData.php
# Cron (quartalsweise):  0 6 1 3,6,9,12 * cd /path/to/mitgliederverwaltung && php scripts/updateBlzData.php
#
# Files:
#   BLZ.CSV      – Originaldatei (ISO-8859-1, Semikolon)
#   lookup.json  – BLZ → {name, bic} UTF-8 (Laufzeit)
#   meta.json    – Quelle / Stand
