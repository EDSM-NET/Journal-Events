<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardTransfer extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove ship trasnfer cost from commander credits.',
        'Update transfered ship parking.',
    ];
    
    
    
    public static function run($json)
    {
        if($json['TransferPrice'] > 0)
        {
            $usersCreditsModel = new \Models_Users_Credits;
            
            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ShipyardTransfer')
                                  ->where('balance = ?', - (int) $json['TransferPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );
            
            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ShipyardTransfer';
                $insert['balance']      = - (int) $json['TransferPrice'];
                $insert['dateUpdated']  = $json['timestamp'];
                
                // Generate details
                $details = static::generateDetails($json);
                if(!is_null($details)){ $insert['details'] = $details; }
                
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
        }
           
        // Update ship parking
        $usersShipsModel    = new \Models_Users_Ships;
        $currentShipId      = static::$user->getShipById($json['ShipID']);
        
        if(!is_null($currentShipId))
        {
            $currentShip        = $usersShipsModel->getById($currentShipId);
            $update             = array();
            
            $transferFinishedAt = strtotime($json['timestamp']);
            
            if(array_key_exists('TransferTime', $json))
            {
                $transferFinishedAt += $json['TransferTime'];
            }
            
            if(!array_key_exists('locationUpdated', $currentShip) || is_null($currentShip['locationUpdated']) || strtotime($currentShip['locationUpdated']) < $transferFinishedAt)
            {
                $stationId = static::findStationId($json);
                
                if(!is_null($stationId))
                {
                    $station                    = \EDSM_System_Station::getInstance($stationId);
                    
                    $update['refSystem']        = $station->getSystem()->getId();
                    $update['refStation']       = $station->getId();
                    $update['locationUpdated']  = date('Y-m-d H:i:s', $transferFinishedAt);
                }
            }
            
            if(count($update) > 0)
            {
                $usersShipsModel->updateById($currentShipId, $update);
            }
            
            unset($currentShip, $update);
        }
        
        unset($usersShipsModel);
        
        return static::$return;
    }
    
    static private function generateDetails($json)
    {
        $details            = array();
        $details['shipId']  = $json['ShipID'];
        
        $stationId = static::findStationId($json);
        
        if(!is_null($stationId))
        {
            $details['stationId']   = $stationId;
        }
            
        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }
        
        return null;
    }
}