<?php

namespace minervis\plugins\LPFixx\Utils;

use minervis\plugins\LPFixx\Repository;
use minervis\plugins\LPFixx\Log\Repository as LogRepository;

/**
 * Class SummaryLogger
 *
 *
 * @package minervis\plugins\LPFixx\Utils
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class  SummaryLogger
{
    const REASON_FIX_INCONSISTENCIES = "1";
    const REASON_FIX_COLLECTION_LP = "2";
    const REASON_FIX_CERTIFICATE = "3";
    const TABLE_NAME = "cron_crnhk_lpfixx_log";


    public  static function write($usr_id, $obj_id, $reason, array $status)
    {
        global $DIC;
       
        $data = array(
            $DIC->database()->nextID(self::TABLE_NAME),
            $usr_id,
            $obj_id,
            $reason,
            date('Y-m-d H:i:s'),
            json_encode($status)
        );
        $types = array(
            "integer",
            "integer",
            "integer",
            "integer",
            "timestamp",
            "text"
        );

        $DIC->database()->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (id, usr_id, obj_id, reason, created_at, status) VALUES (%s, %s, %s, %s, %s, %s)",
            $types,
            $data
        );
    }
}
