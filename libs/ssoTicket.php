<?php
/**
 * Redeem Meldeliste SSO tickets from shared DB (MIT-2).
 */
class SsoTicket
{
    private static function tableName() {
        return identityPrefix().'SsoTicket';
    }

    public static function redeem($token) {
        $token = trim((string)$token);
        if($token === '') {
            return null;
        }
        $esc = mysqli_real_escape_string($GLOBALS['conn'], $token);
        $table = self::tableName();
        $sql = sprintf(
            "SELECT `Index`, `User` FROM `%s` WHERE `Token` = '%s' AND `Used` = 0 AND `Expires` > NOW() LIMIT 1;",
            $table,
            $esc
        );
        $dbr = mysqli_query($GLOBALS['conn'], $sql);
        sqlerror();
        if(!$dbr || !($row = mysqli_fetch_assoc($dbr))) {
            return null;
        }
        $idx = (int)$row['Index'];
        $userId = (int)$row['User'];
        $upd = sprintf(
            "UPDATE `%s` SET `Used` = 1 WHERE `Index` = %d AND `Used` = 0;",
            $table,
            $idx
        );
        mysqli_query($GLOBALS['conn'], $upd);
        sqlerror();
        if(mysqli_affected_rows($GLOBALS['conn']) < 1) {
            return null;
        }
        return $userId;
    }
}
?>
