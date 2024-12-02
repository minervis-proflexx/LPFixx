<?php

namespace minervis\plugins\LPFixx\Job;

use ILIAS\DI\Exceptions\Exception;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilLPFixxPlugin;
use ilCronJob;
use ilCronJobResult;
use ilObjectFactory;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use ilLoggerFactory;
use ilLPStatusWrapper;
use ilLPStatus;
use ilObject;
use ilDBConstants;
use minervis\plugins\LPFixx\Utils\SummaryLogger;


/**
 * Class CollectionLPFixJob
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class CollectionLPFixJob extends ilCronJob
{

    use LPFixxTrait;

    const CRON_JOB_ID = ilLPFixxPlugin::PLUGIN_ID . "_cron";
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var \ILIAS\DI\Container|mixed
     */
    private $dic;


    /**
     * CollectionLPFixJob constructor
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;

    }


    /**
     * @inheritDoc
     */
    public function getDefaultScheduleType() : int
    {
        return self::SCHEDULE_TYPE_DAILY;
    }


    /**
     * @inheritDoc
     */
    public function getDefaultScheduleValue() : ?int
    {
        return null;
    }


    /**
     * @inheritDoc
     */
    public function getDescription() : string
    {
        return "";
    }


    /**
     * @inheritDoc
     */
    public function getId() : string
    {
        return self::CRON_JOB_ID;
    }


    /**
     * @inheritDoc
     */
    public function getTitle() : string
    {
        return ilLPFixxPlugin::PLUGIN_NAME;
    }


    /**
     * @inheritDoc
     */
    public function hasAutoActivation() : bool
    {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function hasFlexibleSchedule() : bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function run() : ilCronJobResult
    {
        $result = new ilCronJobResult();
        try {
            $iterator = $this->getObjectsPerRule();
            $summary = [
                'passed_updated' => 0,
                'certs_generated' => 0,
                'not_updated' => 0
            ];
            foreach ($iterator as $obj_id => $usr_id){
                $p_info = (ilObjectFactory::getInstanceByObjId($obj_id))->getMembersObject()->getPassedInfo($usr_id);
                $this->updateStatus($obj_id, $usr_id, $p_info, $summary);
            }
            $message = $summary['passed_updated'] . " set to passed.  </br>"  . $summary['not_updated']. " not updated. </br> " . $summary['certs_generated'] . " certificated regenerated";
            $result->setMessage($message);
            $result->setStatus(ilCronJobResult::STATUS_OK);

        }catch (Exception $e){
            $result->setMessage($e->getMessage());
            $result->setStatus(ilCronJobResult::STATUS_FAIL);

        }
        return $result;
    }

    public function updateStatus($a_obj_id, $a_usr_id, $passed_info, &$summary, $a_obj = null,  $a_force_raise = true)
    {
        $log = ilLoggerFactory::getLogger('trac');
        $log->debug(sprintf(
            "obj_id: %s, user id: %s, object: %s",
            $a_obj_id,
            $a_usr_id,
            (is_object($a_obj) ? get_class($a_obj) : 'null')
        ));

        $status = $this->determineStatus($a_obj_id, $a_usr_id, $a_obj);

        if (!$status || ($status && !empty($status['status_changed']) 
                && $status['status_changed'] == ilLPStatus::_lookupStatusChanged($a_obj_id, $a_usr_id)
            )){
            $summary['not_updated'] ++;
            return ;
        }
        $this->updatePassed($a_obj_id, $a_usr_id, $status['status_changed'], $summary);
        
        $old_status = null;
        $changed = self::writeStatus($a_obj_id, $a_usr_id, $status, $passed_info,   false, false, $old_status);
        if (!$changed && (bool) $a_force_raise) { // #15529
            self::raiseEvent($a_obj_id, $a_usr_id, $status['status'], $old_status, false);
            $changed = true;

        }
        SummaryLogger::write($a_usr_id, $a_obj_id, SummaryLogger::REASON_FIX_COLLECTION_LP, $status);
    

    }



    protected static function raiseEvent($a_obj_id, $a_usr_id, $a_status, $a_old_status, $a_percentage)
    {
        global $DIC;

        $ilAppEventHandler = $DIC['ilAppEventHandler'];

        $log = ilLoggerFactory::getLogger('trac');
        $log->debug("obj_id: " . $a_obj_id . ", user id: " . $a_usr_id . ", status: " .
            $a_status . ", percentage: " . $a_percentage);

        $ilAppEventHandler->raise("Services/Tracking", "updateStatus", array(
            "obj_id" => $a_obj_id,
            "usr_id" => $a_usr_id,
            "status" => $a_status,
            "old_status" => $a_old_status,
            "percentage" => $a_percentage
        ));
    }



    public function determineStatus($obj_id, $usr_id, $obj)
    {

        $query = "WITH modules_lp AS (
                    SELECT uc.obj_id, ut.usr_id, uc.item_id as module_id, ut.status, ut.status_changed, ut.status_dirty, ut.percentage 
                    FROM ut_lp_collections uc 
                    INNER JOIN object_reference obr ON uc.item_id=obr.ref_id AND uc.obj_id=%s  
                    INNER JOIN 		ut_lp_marks ut ON ut.obj_id=obr.obj_id AND ut.usr_id=%s),
                preferred_status AS (
                    SELECT obj_id, usr_id, MAX(CASE WHEN status=2 THEN 1 ELSE 0 END) As has_status_2
                    FROM modules_lp
                    GROUP BY obj_id,usr_id
                ),
                max_status AS(
                    SELECT obj_id, usr_id, MAX(status) as max_status
                    FROM modules_lp
                    GROUP BY obj_id, usr_id
                )
                SELECT 
                    DISTINCT mp.* FROM modules_lp mp
                INNER JOIN preferred_status ps ON mp.obj_id = ps.obj_id AND mp.usr_id = ps.usr_id
                INNER JOIN max_status ms ON mp.obj_id = ms.obj_id AND mp.usr_id = ms.usr_id
                WHERE (ps.has_status_2 = 1 AND mp.status = 2) 
                OR (ps.has_status_2 = 0 AND mp.status = ms.max_status)";

        $res = $this->dic->database()->queryF($query, ['integer', 'integer'], [$obj_id,  $usr_id ] );
        if($res->numRows() == 0){
            return false;
        }
        $status = array();
        while($row = $this->dic->database()->fetchAssoc($res)){
            $status = $row;
        }
        return $status;

    }

    /**
     * Update passed status
     */
    public  function updatePassed($a_obj_id, $a_usr_id, $status_changed, array &$summary)
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];
        $ilAppEventHandler = $DIC['ilAppEventHandler'];


        // #11600
        $origin = -1;

        $query = "SELECT passed FROM obj_members " .
            "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
            "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');
        $res = $ilDB->query($query);
        $update_query = '';
        if ($res->numRows()) {
            $old = $ilDB->fetchAssoc($res);
            $update_query = "UPDATE obj_members SET " .
                "passed = " . $ilDB->quote(1, 'integer') . ", " .
                "origin = " . $ilDB->quote($origin, 'integer') . ", " .
                "origin_ts = " . $ilDB->quote(strtotime($status_changed), 'integer') . " " .
                "WHERE obj_id = " . $ilDB->quote($a_obj_id, 'integer') . " " .
                "AND usr_id = " . $ilDB->quote($a_usr_id, 'integer');

        }
        if (strlen($update_query)) {
            $ilDB->manipulate($update_query);
            $ilAppEventHandler->raise('Modules/Course', 'participantHasPassedCourse', array(
                'obj_id' => $a_obj_id,
                'usr_id' => $a_usr_id,
            ));
            $summary['passed_updated'] ++;
        }
        return true;
    }


    public static function writeStatus($a_obj_id, $a_user_id, $a_status, $passed_info = null, $a_percentage = false, $a_force_per = false, &$a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM)
    {
        global $DIC;

        $ilDB = $DIC->database();
        $log = $DIC->logger()->trac();

        $log->debug('Write status for:  ' . "obj_id: " . $a_obj_id . ", user id: " . $a_user_id . ", status: " . $a_status['status'] . ", percentage: " . $a_percentage . ", force: " . $a_force_per);
        $update_dependencies = false;

        $a_old_status = ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM;

        // get status in DB
        $set = $ilDB->query(
            "SELECT usr_id,status,status_dirty, status_changed FROM ut_lp_marks WHERE " .
            " obj_id = " . $ilDB->quote($a_obj_id, "integer") . " AND " .
            " usr_id = " . $ilDB->quote($a_user_id, "integer")
        );
        $rec = $ilDB->fetchAssoc($set);

        // update
        if ($rec) {
            $a_old_status = $rec["status"];

            // status has changed: update
            if ($rec["status"] != $a_status['status'] || ($rec['status_changed'] != $a_status['status_changed']) || ($rec['status_changed'] != $passed_info['timestamp'])) {
                $ret = $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status = " . $ilDB->quote($a_status['status'], "integer") . "," .
                    " status_changed = " . $ilDB->quote($a_status['status_changed'], "datetime") . "," .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote($a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote($a_obj_id, "integer")
                );
                if ($ret != 0) {
                    //$update_dependencies = true;
                }
            }
            // status has not changed: reset dirty flag
            elseif ($rec["status_dirty"]) {
                $ilDB->manipulate(
                    "UPDATE ut_lp_marks SET " .
                    " status_dirty = " . $ilDB->quote(0, "integer") .
                    " WHERE usr_id = " . $ilDB->quote($a_user_id, "integer") .
                    " AND obj_id = " . $ilDB->quote($a_obj_id, "integer")
                );
            }
        }

        $log->debug('Update dependecies is ' . ($update_dependencies ? 'true' : 'false'));

        // update collections
        if ($update_dependencies) {
            $log->debug('update dependencies');


            include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
            ilLPStatusWrapper::_removeStatusCache($a_obj_id, $a_user_id);

            $set = $ilDB->query("SELECT ut_lp_collections.obj_id obj_id FROM " .
                "object_reference JOIN ut_lp_collections ON " .
                "(object_reference.obj_id = " . $ilDB->quote($a_obj_id, "integer") .
                " AND object_reference.ref_id = ut_lp_collections.item_id)");
            while ($rec = $ilDB->fetchAssoc($set)) {
                if (in_array(ilObject::_lookupType($rec["obj_id"]), array("crs", "grp", "fold"))) {
                    $log->debug('Calling update status for collection obj_id: ' . $rec['obj_id']);
                    // just to make sure - remove existing cache entry
                    ilLPStatusWrapper::_removeStatusCache($rec["obj_id"], $a_user_id);
                    ilLPStatusWrapper::_updateStatus($rec["obj_id"], $a_user_id);
                }
            }

            // find all course references
            if (ilObject::_lookupType($a_obj_id) == 'crs') {
                $log->debug('update references');

                $query = 'select obj_id from container_reference ' .
                    'where target_obj_id = ' . $ilDB->quote($a_obj_id, ilDBConstants::T_INTEGER);
                $res = $ilDB->query($query);
                while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
                    $log->debug('Calling update status for reference obj_id: ' . $row->obj_id);
                    \ilLPStatusWrapper::_removeStatusCache($row->obj_id, $a_user_id);
                    \ilLPStatusWrapper::_updateStatus($row->obj_id, $a_user_id);
                }
            }

            self::raiseEvent($a_obj_id, $a_user_id, $a_status['status'], $a_old_status, $a_percentage);
        }

        return $update_dependencies;
    }

    public  function hasUserCertificate($usr_id, $obj_id)
    {
        $sql = "SELECT id from il_cert_user_cert where user_id = %s AND obj_id =%s";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [$usr_id, $obj_id]);
        return ($res->numRows() > 0);
    }
    public function getCertificateInfo($usr_id, $obj_id)
    {
        $sql = "SELECT template_values from il_cert_user_cert where user_id = %s AND obj_id =%s ORDER BY id LIMIT 1";
        $res = $this->dic->database()->queryF($sql, ['integer', 'integer'], [$usr_id, $obj_id]);
        $row = $this->dic->database()->fetchAssoc($res);
        $GLOBALS['DIC']->logger()->root()->dump($row['template_values']);
        return json_decode($row['template_values'], true);
    }
    public static function generateCertificates()
    {

    }

    public function  getObjectsPerRule($rule = null)
    {
        if($rule){}
        else{
            $query = "SELECT DISTINCT uc.obj_id, utl.usr_id 
                FROM ut_lp_collections uc 
                INNER JOIN ut_lp_marks utl ON uc.obj_id = utl.obj_id 
                INNER JOIN object_data obd ON obd.obj_id = uc.obj_id 
                WHERE uc.grouping_id > 0 AND (utl.status = %s OR utl.status = %s) 
                    AND obd.type= %s";
            $res = $this->dic->database()->queryF($query, ['integer','integer', 'text'], [ilLPStatus::LP_STATUS_COMPLETED_NUM, ilLPStatus::LP_STATUS_FAILED_NUM, 'crs']);
            $members = array();
            while($r = $this->dic->database()->fetchAssoc($res)){
                $members [] = [$r['obj_id'] => $r['usr_id']];
            }
            return new RecursiveIteratorIterator(new RecursiveArrayIterator($members));;
        }
    }
}
