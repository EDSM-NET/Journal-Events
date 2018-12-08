<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class PowerplayDefect extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Pledge the commander to the new Power.',
    ];



    public static function run($json)
    {
        // Check if power is known in EDSM
        $powerId        = \Alias\System\Power::getFromFd($json['ToPower']);

        if(is_null($powerId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\System\Power: ' . $json['ToPower']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Check user pledge
        $currentPowerId     = static::$user->getPower();
        $lastPowerUpdate    = static::$user->getPowerLastUpdate();

        if($currentPowerId != $powerId || is_null($lastPowerUpdate))
        {
            // If newer or null, update the ship ID
            if(is_null($lastPowerUpdate) || strtotime($lastPowerUpdate) < strtotime($json['timestamp']))
            {
                $update                     = array();
                $update['currentPower']     = (int) $powerId;
                $update['powerDetails']     = new \Zend_Db_Expr('NULL');
                $update['lastPowerUpdate']  = $json['timestamp'];

                $usersModel = new \Models_Users;
                $usersModel->updateById(static::$user->getId(), $update);

                unset($usersModel, $update);
            }
        }

        return static::$return;
    }
}