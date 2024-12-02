<?php

namespace minervis\plugins\LPFixx\Job;

use minervis\plugins\LPFixx\Utils\LPFixxTrait;
use ilLPFixxPlugin;

/**
 * Class Repository
 *
 *
 * @package minervis\plugins\LPFixx\Job
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
final class Repository
{

    use LPFixxTrait;

    const PLUGIN_CLASS_NAME = ilLPFixxPlugin::class;
    /**
     * @var self|null
     */
    protected static $instance = null;



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
     * @internal
     */
    public function dropTables() : void
    {

    }


    /**
     * @return Factory
     */
    public function factory() : Factory
    {
        return Factory::getInstance();
    }


    /**
     * @internal
     */
    public function installTables() : void
    {

    }
}
