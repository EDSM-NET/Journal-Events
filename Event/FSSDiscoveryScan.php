<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSSDiscoveryScan extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert body count in current system.',
        'Update if wrong and not locked',
    ];



    public static function run($json)
    {
        $systemId = static::findSystemId($json);

        if(!is_null($systemId))
        {
            $systemsBodiesCountModel    = new \Models_Systems_Bodies_Count;
            $currentBodiesCount         = $systemsBodiesCountModel->getByRefSystem($systemId);

            if(is_null($currentBodiesCount))
            {
                try
                {
                    $insert                 = array();
                    $insert['refSystem']    = $systemId;
                    $insert['bodyCount']    = $json['BodyCount'];

                    $systemsBodiesCountModel->insert($insert);
                }
                catch(\Zend_Db_Exception $e)
                {
                    // Based on unique index, this entry was already saved.
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        return static::$return;
                    }
                    else
                    {
                        static::$return['msgnum']   = 500;
                        static::$return['msg']      = 'Exception: ' . $e->getMessage();

                        if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                        {
                            \Sentry\captureException($e);
                        }
                    }
                }
            }
            else
            {
                // FSSAllBodiesFound has the real SystemAddress and will lock the body count, so only update if we haven't locked yet
                if($currentBodiesCount['bodyCount'] != $json['BodyCount'] && array_key_exists('isLocked', $currentBodiesCount) && $currentBodiesCount['isLocked'] == 0)
                {
                    $update                 = array();
                    $update['bodyCount']    = $json['BodyCount'];

                    $systemsBodiesCountModel->updateByRefSystem($systemId, $update);
                }
            }

            unset($systemsBodiesCountModel, $currentBodiesCount);
        }

        return static::$return;
    }
}