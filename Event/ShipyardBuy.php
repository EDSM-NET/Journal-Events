<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardBuy extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove ship cost from commander credits.',
        '<span class="text-warning">Ship creation is done in the <code>Loadout</code> event.</span>',
        'Add sell price if old ship is sold.',
    ];
    
    
    
    public static function run($json)
    {
        $shipType = \Alias\Ship\Type::getFromFd($json['ShipType']);
        
        if(is_null($shipType))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            \EDSM_Api_Logger_Alias::log(
                'Alias\Ship\Type : ' . $json['ShipType'] . ' (Sofware#' . static::$softwareId . ')',
                [
                    'file'  => __FILE__,
                    'line'  => __LINE__,
                ]
            );
            
            $json['isError']            = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'ShipyardBuy')
                              ->where('balance = ?', - (int) $json['ShipPrice'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'ShipyardBuy';
            $insert['balance']      = - (int) $json['ShipPrice'];
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
        
        unset($isAlreadyStored);
        
        // Old ship sold?
        if(array_key_exists('SellPrice', $json) && $json['SellPrice'] > 0)
        {
            $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);
        
            if(is_null($shipType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';
                
                \EDSM_Api_Logger_Alias::log(
                    'Alias\Ship\Type : ' . $json['SellOldShip'] . ' (Sofware#' . static::$softwareId . ')',
                    [
                        'file'  => __FILE__,
                        'line'  => __LINE__,
                    ]
                );
                
                $json['isError']            = 1;
                \Journal\Event::run($json);
                
                return static::$return;
            }
            
            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ShipyardSell')
                                  ->where('balance = ?', (int) $json['SellPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );
            
            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ShipyardSell';
                $insert['balance']      = (int) $json['SellPrice'];
                $insert['dateUpdated']  = $json['timestamp'];
                
                // Generate details
                $details = static::generateDetailsSell($json);
                if(!is_null($details)){ $insert['details'] = $details; }
                
                $usersCreditsModel->insert($insert);
                
                unset($insert);
            }
            else
            {
                $details = static::generateDetailsSell($json);
                
                if($isAlreadyStored->details != $details)
                {
                    $usersCreditsModel->updateById($isAlreadyStored->id, array('details' => $details));
                }
            }
               
            // Sell ship
            $usersShipsModel    = new \Models_Users_Ships;
            $currentShipId      = static::$user->getShipById($json['SellShipID']);
            
            if(!is_null($currentShipId))
            {
                $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);
            
                if(!is_null($shipType))
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();
                    
                    if($currentShip['type'] == $shipType && $currentShip['sell'] == 0)
                    {
                        $update['sell'] = 1;
                        
                        if(is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                        {
                            $update['dateUpdated']      = $json['timestamp'];
                        }
                    }
                    
                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }
                    
                    unset($currentShip, $update);
                }
            }
            
            unset($usersShipsModel, $isAlreadyStored);
        }
        
        unset($usersCreditsModel);
        
        return static::$return;
    }
    
    static private function generateDetails($json)
    {
        $details = array();
        
        $shipType = \Alias\Ship\Type::getFromFd($json['ShipType']);
        
        if(!is_null($shipType))
        {
            $details['shipType']  = $shipType;
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
    
    static private function generateDetailsSell($json)
    {
        $details = array();
        
        $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);
        
        if(!is_null($shipType))
        {
            $details['shipType']  = $shipType;
        }
        
        $stationId = static::findStationId($json);
        
        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }
        
        $details['shipId'] = $json['SellOldShip'];
            
        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }
        
        return null;
    }
}