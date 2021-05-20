<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Undocked extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove current ship station.',
    ];



    public static function run($json)
    {
        // Skip Taxi
        if(array_key_exists('Taxi', $json) && $json['Taxi'] === true)
        {
            return static::$return;
        }

        // Update ship parking
        $currentShipId = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            static::updateCurrentGameShipId($currentShipId, $json['timestamp']);

            $usersShipsModel    = new \Models_Users_Ships;
            $currentShipId      = static::$user->getShipById($currentShipId);

            if(!is_null($currentShipId))
            {
                $currentShip    = $usersShipsModel->getById($currentShipId);
                $update         = array();

                if(!array_key_exists('locationUpdated', $currentShip) || is_null($currentShip['locationUpdated']) || strtotime($currentShip['locationUpdated']) < strtotime($json['timestamp']))
                {
                    $update['refStation']       = new \Zend_Db_Expr('NULL');
                    $update['locationUpdated']  = $json['timestamp'];
                }

                if(count($update) > 0)
                {
                    $usersShipsModel->updateById($currentShipId, $update);
                }

                unset($update);
            }

            unset($usersShipsModel, $currentShipId);
        }

        return static::$return;
    }
}