<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MarketBuy extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove commodity buy price from commander credits.',
        'Add commodity to commander cargo hold.',
    ];
    
    
    
    public static function run($json)
    {
        $currentItemId  = \Alias\Station\Commodity\Type::getFromFd($json['Type']);
        
        if(is_null($currentItemId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            \EDSM_Api_Logger_Alias::log(
                '\Alias\Station\Commodity\Type: ' . $json['Type'] . ' (Sofware#' . static::$softwareId . ')',
                [
                    'file'  => __FILE__,
                    'line'  => __LINE__,
                ]
            );
        
            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'MarketBuy')
                              ->where('balance = ?', - (int) $json['TotalCost'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'MarketBuy';
            $insert['balance']      = - (int) $json['TotalCost'];
            $insert['dateUpdated']  = $json['timestamp'];
            
            // Generate details
            $details = static::generateDetails($json);
            if(!is_null($details)){ $insert['details'] = $details; }
            
            $usersCreditsModel->insert($insert);
            
            unset($insert);
            
            // UPDATE CARGO HOLD
            $databaseModel  = new \Models_Users_Cargo;
            
            // Find the current item ID
            $currentItem   = null;
            $currentItems  = $databaseModel->getByRefUser(static::$user->getId());
            
            if(!is_null($currentItems))
            {
                foreach($currentItems AS $tempItem)
                {
                    if($tempItem['type'] == $currentItemId)
                    {
                        $currentItem = $tempItem;
                        break;
                    }
                }
            }
            
            // If we have the line, update else, wait for the startUp events
            if(!is_null($currentItem))
            {
                if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                {
                    $update                 = array();
                    $update['total']        = ($currentItem['total'] + $json['Count']);
                    $update['lastUpdate']   = $json['timestamp'];
                    
                    $databaseModel->updateById($currentItem['id'], $update);
                    
                    unset($update);
                }
            }
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
        $details['qty'] = $json['Count'];
        $currentShipId  = static::findShipId($json);
        
        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }
        
        $commodityType = \Alias\Station\Commodity\Type::getFromFd($json['Type']);
        
        if(!is_null($commodityType))
        {
            $details['type']  = $commodityType;
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