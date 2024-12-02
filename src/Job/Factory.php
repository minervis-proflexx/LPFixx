<?php

namespace minervis\plugins\LPFixx\Job;

use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilCronJob;
use ilLPFixxPlugin;

/**
 * Class Factory
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
final class Factory
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var self|null
     */
    protected static ?Factory $instance = null;


    /**
     * Factory constructor
     */
    private function __construct()
    {

    }


    /**
     * @return self
     */
    public static function getInstance() : self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }


    /**
     * @param string $job_id
     *
     * @return ilCronJob|null
     */
    public function newInstanceById(string $job_id) : ?ilCronJob
    {
        switch ($job_id) {
            case CollectionLPFixJob::CRON_JOB_ID:
                return new CollectionLPFixJob();
            case FindAndFixInconsistenciesJob::CRON_JOB_ID:
                return new FindAndFixInconsistenciesJob();
            case CertificateGenerationJob::CRON_JOB_ID:
                return new CertificateGenerationJob();
            default:
                return null;
        }
    }


    /**
     * @return ilCronJob[]
     */
    public function newInstances() : array
    {
        return [
            new CollectionLPFixJob(),
            new FindAndFixInconsistenciesJob(),
            new CertificateGenerationJob()

        ];
    }
}
