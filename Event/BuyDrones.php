<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyDrones extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove drone(s) cost from commander credits.',
    ];
    
    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'BuyDrones')
                              ->where('balance = ?', - (int) $json['TotalCost'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'BuyDrones';
            $insert['details']      = static::generateDetails($json);
            $insert['balance']      = - (int) $json['TotalCost'];
            $insert['dateUpdated']  = $json['timestamp'];
            
            $usersCreditsModel->insert($insert);
            
            unset($insert);
        }
        else
        {
            $details = static::generateDetails($json);
            
            if($isAlreadyStored->details != $details)
            {
                $usersCreditsModel->updateById($isAlreadyStored->id, array('details' => $details));
            }
            
            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';
        }
        
        unset($usersCreditsModel, $isAlreadyStored);
        
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
        
        $details['type']    = $json['Type'];
        $details['qty']     = $json['Count'];
        
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