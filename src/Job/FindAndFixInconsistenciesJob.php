<?php

namespace minervis\plugins\LPFixx\Job;

use ILIAS\DI\Exceptions\Exception;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use minervis\plugins\LPFixx\Utils\SummaryLogger;
use ilLPFixxPlugin;
use ilCronJob;
use ilCronJobResult;


/**
 * Class Job
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class FindAndFixInconsistenciesJob extends ilCronJob
{

    use LPFixxTrait;

    const CRON_JOB_ID = ilLPFixxPlugin::PLUGIN_ID . "_faf_cron";
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var \ILIAS\DI\Container|mixed
     */
    private $dic;


    /**
     * Job constructor
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;

    }


    public function getDefaultScheduleType() : \ILIAS\Cron\Schedule\CronJobScheduleType
    {
        return \ILIAS\Cron\Schedule\CronJobScheduleType::SCHEDULE_TYPE_DAILY;
    }


    public function getDefaultScheduleValue() : ?int
    {
        return null;
    }


    public function getDescription() : string
    {
        return "Find and Fix LP inconsistencies";
    }


    public function getId() : string
    {
        return self::CRON_JOB_ID;
    }


    public function getTitle() : string
    {
        return ilLPFixxPlugin::PLUGIN_NAME . ": FindAndFixInconsistencies";
    }


    /**
     * @inheritDoc
     */
    public function hasAutoActivation() : bool
    {
        return true;
    }


    public function hasFlexibleSchedule() : bool
    {
        return true;
    }

    public function run() : ilCronJobResult
    {
        $result = new ilCronJobResult();
        try {
            $message = 'Ok. ' . count($this->fixLPInconsistencies()) . " entries";
            
            $result->setMessage($message);
            $result->setStatus(ilCronJobResult::STATUS_OK);

        }catch (Exception $e){
            $result->setMessage($e->getMessage());
            $result->setStatus(ilCronJobResult::STATUS_FAIL);

        }
        return $result;
    }

    public function findLPInconsistencies(int $minutes = 30): array
    {
        $this->dic->database()->query("DROP TEMPORARY TABLE IF EXISTS lp_inconsistencies");
        $query = "CREATE TEMPORARY TABLE lp_inconsistencies AS
                    SELECT
                        s.user_id, 
                        s.obj_id,
                        s.rvalue, 
                        s.lvalue, 
                        m.status AS lp_status, 
                        (CASE WHEN s.rvalue = 'passed' THEN 2 ELSE 1 END) AS scorm_status,
                        s.c_timestamp AS scorm_passed_time, 
                        m.status_changed AS ut_lp_passed_time,
                        ABS(TIMESTAMPDIFF(MINUTE, s.c_timestamp, m.status_changed)) AS time_difference,
                        0 AS reason
                    FROM scorm_tracking s
                    INNER JOIN ut_lp_marks m ON s.lvalue = 'cmi.core.lesson_status' 
                        AND s.rvalue = 'passed' 
                        AND s.user_id = m.usr_id 
                        AND s.obj_id = m.obj_id 
                        AND m.status <> 2

                    UNION

                    SELECT 
                        s.user_id, 
                        s.obj_id, 
                        s.rvalue, 
                        s.lvalue, 
                        m.status AS lp_status, 
                        (CASE WHEN s.rvalue = 'passed' THEN 2 ELSE 1 END) AS scorm_status,
                        s.c_timestamp AS scorm_passed_time, 
                        m.status_changed AS ut_lp_passed_time,
                        ABS(TIMESTAMPDIFF(HOUR, s.c_timestamp, m.status_changed)) AS time_difference,
                        1 AS reason
                    FROM scorm_tracking s 
                    INNER JOIN ut_lp_marks m ON s.lvalue = 'cmi.core.lesson_status' 
                        AND s.rvalue = 'passed' 
                        AND s.user_id = m.usr_id 
                        AND s.obj_id = m.obj_id
                        AND m.status = 2 
                        AND ABS(TIMESTAMPDIFF(HOUR, s.c_timestamp, m.status_changed)) > 1";
        
        $this->dic->database()->query($query);
        //make sure only object members of the parent course are updated
        $query = "SELECT li.* FROM lp_inconsistencies li
                    INNER JOIN object_data obd ON obd.obj_id= li.obj_id 
                    INNER JOIN usr_data u ON u.usr_id=li.user_id
                    INNER JOIN object_reference obr ON obr.obj_id=li.obj_id 
                    INNER JOIN ut_lp_collections uc ON uc.item_id=obr.ref_id
                    INNER JOIN object_data obd2 ON obd2.obj_id = uc.obj_id
                    INNER JOIN obj_members obm ON obm.obj_id=uc.obj_id AND obm.usr_id=u.usr_id";
        $res = $this->dic->database()->query($query);

        
        $items = array();
        
        while($row = $this->dic->database()->fetchAssoc($res)){
            $items [] = $row;
        }
        $this->dic->database()->query("DROP TEMPORARY TABLE IF EXISTS lp_inconsistencies");
        return $items;
    }

    public function fixLPInconsistencies(): array
    {
        //First call the finder
        $items = $this->findLPInconsistencies();
        foreach($items as $item){
            $status = array(
                "status" => $item["scorm_status"],
                "status_changed" => $item["scorm_passed_time"]
            );
            CollectionLPFixJob::writeStatus($item['obj_id'], $item['user_id'], $status);
            SummaryLogger::write($item['user_id'], $item['obj_id'], SummaryLogger::REASON_FIX_INCONSISTENCIES, $status);
        }
        
        return $items;
    }

}
