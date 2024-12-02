<?php

require_once __DIR__ . "/../vendor/autoload.php";

use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ILIAS\DI\Container;


/**
 * Class ilLPFixxPlugin
 *
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
class ilLPFixxPlugin extends ilCronHookPlugin
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = self::class;
    const PLUGIN_ID = "lpfixx";
    const PLUGIN_NAME = "LPFixx";
    /**
     * @var self|null
     */
    protected static $instance = null;


    /**
     * @return self
     */
    public static function getInstance() : self
    {
        global $DIC;
        if (self::$instance === null) {
            /** @var $component_factory ilComponentFactory */
            $component_factory = $DIC['component.factory'];

            self::$instance = $component_factory->getPlugin(self::PLUGIN_ID);
        }

        return self::$instance;
    }





    /**
     * @inheritDoc
     */
    public function getCronJobInstance(string $jobId) : ilCronJob
    {
        return self::lPFixx()->jobs()->factory()->newInstanceById($jobId);
    }


    /**
     * @inheritDoc
     */
    public function getCronJobInstances() : array
    {
        return self::lPFixx()->jobs()->factory()->newInstances();
    }


    public function getPluginName() : string
    {
        return self::PLUGIN_NAME;
    }


    protected function afterUninstall() : void
    {
        self::lPFixx()->dropTables();
    }


    protected function shouldUseOneUpdateStepOnly() : bool
    {
        return false;
    }
}
