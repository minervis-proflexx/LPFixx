<?php
declare(strict_types=1);


namespace minervis\plugins\LPFixx\Job;


use ILIAS\Cron\Schedule\CronJobScheduleType;
use ILIAS\DI\Exceptions\Exception;
use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilLPFixxPlugin;
use ilCronJob;
use ilCronJobResult;
use ilCertificateTemplateRepository;
use minervis\plugins\LPFixx\Utils\SummaryLogger;

/**
 * Class CertificateGenerationJob
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class CertificateGenerationJob extends ilCronJob
{

    use LPFixxTrait;

    const CRON_JOB_ID = ilLPFixxPlugin::PLUGIN_ID . "_certg_cron";
    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var \ILIAS\DI\Container|mixed
     */
    private $dic;
    /**
     * @var ilCertificateTemplateRepository
     */
    private ilCertificateTemplateRepository $templateRepository;

    /**
     * CertificateGenerationJob constructor
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        

    }


    /**
     * @inheritDoc
     */
    public function getDefaultScheduleType() : CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_DAILY;
    }


    public function getDefaultScheduleValue() : ?int
    {
        return null;
    }


    public function getDescription() : string
    {
        return "Deletes/Deactivates Certificates of users who have not passed. For more info about the summary of which user in which course has been affected, contact the admin";
    }


    public function getId() : string
    {
        return self::CRON_JOB_ID;
    }


    public function getTitle() : string
    {
        return ilLPFixxPlugin::PLUGIN_NAME . ": Clean Certiticates";
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
            $rows = $this->deleteWrongCertificates();
            $message = 'Ok. A total of '. $rows .' have been cleaned';
            
            $result->setMessage($message);
            $result->setStatus(ilCronJobResult::STATUS_OK);

        }catch (Exception $e){
            $result->setMessage($e->getMessage());
            $result->setStatus(ilCronJobResult::STATUS_FAIL);

        }
        return $result;
    }

    private function deleteWrongCertificates(): int
    {
        global $DIC;
        $query = "SELECT DISTINCT
                    c.user_id, 
                    c.obj_id,
                    c.obj_type
                FROM il_cert_user_cert c
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM ut_lp_marks m
                    WHERE m.usr_id = c.user_id
                    AND m.obj_id = c.obj_id
                    AND m.status = 2
                )  
                AND c.currently_active = 1
                AND c.obj_type ='crs'";
        $rows = $DIC->database()->query($query);

        $numberRows = $this->dic->database()->numRows($rows);

        while($row = $this->dic->database()->fetchAssoc($rows)){

            $this->deleteQuery($row['obj_id'], $row['user_id'], $row['obj_type']);
            SummaryLogger::write($row['user_id'], $row['obj_id'], SummaryLogger::REASON_FIX_CERTIFICATE, $row);
            //break;
        }

        return $numberRows;
       
        
    }

    private function deleteQuery($obj_id, $usr_id, $obj_type): void
    {
        $sql = "UPDATE il_cert_user_cert c
                SET c.currently_active = 0
                WHERE c.obj_id = %s 
                    AND c.user_id = %s 
                    AND c.obj_type = %s
        ";
        $this->dic->database()->manipulateF(
            $sql,
            ['integer', 'interger', 'text'],
            [$obj_id, $usr_id, $obj_type]
        );
    }
    
}
