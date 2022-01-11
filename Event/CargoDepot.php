<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CargoDepot extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update mission cargo progression.',
    ];
    
    
    
    public static function run($json)
    {
        $usersMissionsModel = new \Models_Users_Missions;
        $currentMission     = $usersMissionsModel->getById($json['MissionID']);
        
        // Wait until the mission is known to proceed
        if(is_null($currentMission))
        {
            // Wait only if depot is not too old
            if(strtotime($json['timestamp']) >= strtotime('2 WEEKS AGO'))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';
                
                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);
            }
            
            return static::$return;
        }
        
        if(!is_null($currentMission['details']))
        {
            $needUpdate = false;
            $details    = \Zend_Json::decode($currentMission['details']);
            
            if(!array_key_exists('commodityCollected', $details))
            {
                $details['commodityCollected']  = 0;
            }
            
            if(array_key_exists('ItemsCollected', $json) && $json['ItemsCollected'] > $details['commodityCollected'])
            {
                $details['commodityCollected']  = $json['ItemsCollected'];
                $needUpdate                     = true;
            }
            
            if(!array_key_exists('commodityDelivered', $details))
            {
                $details['commodityDelivered']  = 0;
            }
            
            if(array_key_exists('ItemsDelivered', $json) && $json['ItemsDelivered'] > $details['commodityDelivered'])
            {
                $details['commodityDelivered']  = $json['ItemsDelivered'];
                $needUpdate                     = true;
            }
            
            if($needUpdate === true)
            {
                $usersMissionsModel->updateById(
                    $json['MissionID'],
                    [
                        'details' => \Zend_Json::encode($details),
                    ]
                );
            }
        }
        
        unset($usersMissionsModel, $currentMission);
        
        return static::$return;
    }
}