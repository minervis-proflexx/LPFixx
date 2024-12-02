<?php

namespace minervis\plugins\LPFixx\Utils;

use minervis\plugins\LPFixx\Repository;
use minervis\plugins\LPFixx\Log\Repository as LogRepository;

/**
 * Trait LPFixxTrait
 *
 *
 * @package minervis\plugins\LPFixx\Utils
 *
 * @author Jephte Abijuru <jephte.abijuru@minervis.com>
 */
trait LPFixxTrait
{

    /**
     * @return Repository
     */
    protected static function lPFixx() : Repository
    {
        return Repository::getInstance();
    }

}
