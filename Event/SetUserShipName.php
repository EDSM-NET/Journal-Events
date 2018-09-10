<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SetUserShipName extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update ship name/identification',
    ];
    
    
    
    public static function run($json)
    {
        $usersShipsModel    = new \Models_Users_Ships;
        $currentShipId      = static::$user->getShipById($json['ShipID']);
        
        if(!is_null($currentShipId))
        {
            $currentShip    = $usersShipsModel->getById($currentShipId);
            $update         = array();
            
            if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
            {
                $update['customName']       = $json['UserShipName'];
                
                if(array_key_exists('UserShipId', $json))
                {
                    $update['customIdent']      = $json['UserShipId'];
                }
                
                $update['dateUpdated']      = $json['timestamp'];
            }
            
            if(count($update) > 0)
            {
                $usersShipsModel->updateById($currentShipId, $update);
            }
            
            unset($currentShip, $update);
        }
        
        unset($usersShipsModel, $currentShipId);
        
        return static::$return;
    }
}