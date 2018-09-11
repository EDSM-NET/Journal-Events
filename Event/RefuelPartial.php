<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class RefuelPartial extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove refuel cost.',
        '<span class="text-danger"><ins>TODO:</ins> Refuel ship if newer than the stored value and transient state available.</span>',
    ];
    
    
    
    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'RefuelPartial')
                              ->where('balance = ?', - (int) $json['Cost'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'RefuelPartial';
            $insert['balance']      = - (int) $json['Cost'];
            $insert['dateUpdated']  = $json['timestamp'];
            
            // Generate details
            $details = static::generateDetails($json);
            if(!is_null($details)){ $insert['details'] = $details; }
            
            // Insert
            $usersCreditsModel->insert($insert);
            
            unset($insert);
        }
        else
        {
            $details = static::generateDetails($json);
            
            if($isAlreadyStored->details != $details)
            {
                $usersCreditsModel->updateById(
                    $isAlreadyStored->id,
                    [
                        'details' => $details,
                    ]
                );
            }
            
            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';
            
            // No need to go further if we already have handled this event
            return static::$return;
        }
        
        unset($usersCreditsModel, $isAlreadyStored);
        
        //TODO: Refuel ship
        
        
        return static::$return;
    }
    
    static private function generateDetails($json)
    {
        $details        = array();
        $currentShipId  = static::findShipId($json);
        
        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }
        
        $stationId = static::findStationId($json);
        
        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }
            
        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }
        
        return null;
    }
}