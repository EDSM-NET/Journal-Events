<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Powerplay extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Pledge the commander to the Power.',
        'Update rank, merits, votes and time pledged.',
    ];



    public static function run($json)
    {
        // Check if power is known in EDSM
        $powerId        = \Alias\System\Power::getFromFd($json['Power']);

        if(is_null($powerId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\System\Power: ' . $json['Power']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Check user pledge
        $currentPowerId     = static::$user->getPower();
        $lastPowerUpdate    = static::$user->getPowerLastUpdate();

        if($currentPowerId != $powerId || is_null($lastPowerUpdate) || strtotime($lastPowerUpdate) < strtotime($json['timestamp']) )
        {
            // If newer or null, update the power
            if(is_null($lastPowerUpdate) || strtotime($lastPowerUpdate) < strtotime($json['timestamp']))
            {
                $powerDetails                   = array();
                $powerDetails['rank']           = $json['Rank'];
                $powerDetails['merits']         = $json['Merits'];
                $powerDetails['votes']          = $json['Votes'];
                $powerDetails['timePledged']    = $json['TimePledged'];

                $update                         = array();
                $update['currentPower']         = (int) $powerId;
                $update['powerDetails']         = \Zend_Json::encode($powerDetails);
                $update['lastPowerUpdate']      = $json['timestamp'];

                $usersModel = new \Models_Users;
                $usersModel->updateById(static::$user->getId(), $update);

                unset($usersModel, $update, $powerDetails);
            }
        }

        return static::$return;
    }
}