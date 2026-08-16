<?php
/**
 * Deutsche Bundesbank BLZ directory (data/blz/lookup.json).
 */
class BlzDirectory
{
    /** @var array<string,array{name:string,bic:string}>|null */
    private static $map = null;

    public static function dataDir() {
        return dirname(__DIR__).'/data/blz';
    }

    public static function lookupPath() {
        return self::dataDir().'/lookup.json';
    }

    /** @return array<string,array{name:string,bic:string}> */
    public static function map() {
        if(self::$map !== null) {
            return self::$map;
        }
        $path = self::lookupPath();
        if(!is_readable($path)) {
            self::$map = array();
            return self::$map;
        }
        $raw = file_get_contents($path);
        $data = json_decode((string)$raw, true);
        self::$map = is_array($data) ? $data : array();
        return self::$map;
    }

    /** German IBAN → 8-digit BLZ, or ''. */
    public static function blzFromIban($iban) {
        $iban = normalizeIban($iban);
        if(strlen($iban) < 12 || substr($iban, 0, 2) !== 'DE') {
            return '';
        }
        $blz = substr($iban, 4, 8);
        return preg_match('/^\d{8}$/', $blz) ? $blz : '';
    }

    /**
     * @return array{blz:string,name:string,bic:string}|null
     */
    public static function lookupBlz($blz) {
        $blz = preg_replace('/\D/', '', (string)$blz);
        if(strlen($blz) !== 8) {
            return null;
        }
        $map = self::map();
        if(!isset($map[$blz]) || !is_array($map[$blz])) {
            return null;
        }
        $name = isset($map[$blz]['name']) ? trim((string)$map[$blz]['name']) : '';
        if($name === '') {
            return null;
        }
        return array(
            'blz' => $blz,
            'name' => $name,
            'bic' => isset($map[$blz]['bic']) ? trim((string)$map[$blz]['bic']) : '',
        );
    }

    /**
     * @return array{blz:string,name:string,bic:string}|null
     */
    public static function lookupIban($iban) {
        $blz = self::blzFromIban($iban);
        if($blz === '') {
            return null;
        }
        return self::lookupBlz($blz);
    }

    /** Kreditinstitut-Name aus DE-IBAN, sonst ''. */
    public static function bankNameFromIban($iban) {
        $hit = self::lookupIban($iban);
        return $hit ? $hit['name'] : '';
    }
}
?>
