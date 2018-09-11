<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SellDrones extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add drone(s) sell price to commander credits.',
    ];
    
    
    
    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'SellDrones')
                              ->where('balance = ?', (int) $json['TotalSale'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'SellDrones';
            $insert['details']      = static::generateDetails($json);
            $insert['balance']      = (int) $json['TotalSale'];
            $insert['dateUpdated']  = $json['timestamp'];
            
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