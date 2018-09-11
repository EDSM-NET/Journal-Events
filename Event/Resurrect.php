<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Resurrect extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove resurrect cost from commander credits',
        'Mark current ship as sold if free rebuy',
    ];
    
    
    
    public static function run($json)
    {
        if($json['Cost'] > 0)
        {
            $usersCreditsModel = new \Models_Users_Credits;
            
            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'Resurrect')
                                  ->where('balance = ?', - (int) $json['Cost'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );
            
            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'Resurrect';
                $insert['balance']      = - (int) $json['Cost'];
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
        // If option if free, then mark the shipId as Sold
        elseif(array_key_exists('Option', $json) && $json['Option'] == 'free')
        {
            $shipId = static::findShipId($json);
            
            if(!is_null($shipId))
            {
                $usersShipsModel    = new \Models_Users_Ships;
                
                $currentShipId      = $usersShipsModel->fetchRow(
                    $usersShipsModel->select()
                                    ->from($usersShipsModel, ['id'])
                                    ->where('refUser = ?', static::$user->getId())
                                    ->where('refShip = ?', $shipId)
                                    ->where('sell = ?', 0)
                                    ->where('dateUpdated < ?', $json['timestamp'])
                );
            
                // Update ship
                if(!is_null($currentShipId))
                {
                    $currentShipId  = $currentShipId->id;
                    
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();
                    
                    if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['sell']             = 1;
                        $update['dateUpdated']      = $json['timestamp'];
                    }
                    
                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }
                    
                    unset($currentShip, $update);
                }
                
                unset($usersShipsModel, $currentShipId);
            }
        }
        
        return static::$return;
    }
    
    static private function generateDetails($json)
    {
        $details    = array();
        
        $shipId     = static::findShipId($json);
        if(!is_null($shipId))
        {
            $details['shipId'] = $shipId;
        }
        
        $systemId   = static::findSystemId($json);
        if(!is_null($systemId))
        {
            $details['systemId'] = $systemId;
        }
        
        $details['bankrupt'] = $json['Bankrupt'];
            
        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }
        
        return null;
    }
}