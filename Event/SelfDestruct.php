<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SelfDestruct extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Register commander death.',
    ];



    public static function run($json)
    {
        $systemId           = static::findSystemId($json);

        if(!is_null($systemId))
        {
            try
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refSystem']    = $systemId;
                $insert['reason']       = 'SelfDestruct';
                $insert['dateEvent']    = $json['timestamp'];

                $shipId = static::findShipId($json);
                if(!is_null($shipId))
                {
                    $insert['refShip'] = $shipId;
                }

                $usersDeathsModel = new \Models_Users_Deaths;
                $usersDeathsModel->insert($insert);

                unset($usersDeathsModel, $insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this journal entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';
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

        return static::$return;
    }
}