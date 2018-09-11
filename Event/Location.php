<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Location extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update ship current system/station.',
    ];
    
    
    
    public static function run($json)
    {
        if(array_key_exists('Docked', $json) && $json['Docked'] === true)
        {
            $stationId = static::findStationId($json);
            
            if(!is_null($stationId))
            {
                $station        = \EDSM_System_Station::getInstance($stationId);
                $system         = $station->getSystem();
                $currentShipId  = static::findShipId($json);
                
                // Update ship parking
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
                            $update['refSystem']        = (int) $system->getId();
                            $update['refStation']       = (int) $station->getId();
                            $update['locationUpdated']  = $json['timestamp'];
                        }
                        
                        if(count($update) > 0)
                        {
                            $usersShipsModel->updateById($currentShipId, $update);
                        }
                        
                        unset($update);
                    }
                    
                    unset($usersShipsModel);
                }
                
                unset($currentShipId);
                
                // Give badge
                if($system->getId() == 28107130
                     && strtotime($json['timestamp']) > strtotime('2018-09-01 00:00:00') && strtotime($json['timestamp']) < strtotime('2018-09-30 00:00:00'))
                {
                    static::$user->giveBadge(550);
                }
                if($system->getId() == 4351697 && $station->getId() == 42167
                     && strtotime($json['timestamp']) > strtotime('2018-09-01 00:00:00') && strtotime($json['timestamp']) < strtotime('2018-09-30 00:00:00'))
                {
                    static::$user->giveBadge(551);
                }
            }
        }
        
        return static::$return;
    }
}