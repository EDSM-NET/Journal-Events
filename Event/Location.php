<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

use         Alias\Station\Engineer;

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
        
        if(array_key_exists('Factions', $json))
        {
            static::handleMyReputation($json['Factions'], $json['timestamp']);
        }
        
        return static::$return;
    }
    
    public static function handleMyReputation($factions, $timestamp)
    {
        $usersFactionsModel = new \Models_Users_Factions;
        
        foreach($factions AS $faction)
        {
            if(!array_key_exists('MyReputation', $faction))
            {
                // Old journal, exit the loop
                break;
            }
            
            $faction['Name']    = trim($faction['Name']);
            $factionId          = null;

            if(!empty($faction['Name']) && !in_array($faction['Name'], Engineer::getAll()))
            {
                // Special continue case for Pilots Federation Local Branch
                if($faction['Name'] == 'Pilots Federation Local Branch' && $faction['Influence'] == 0)
                {
                    continue;
                }

                $factionId          = $factionsModel->getByName($faction['Name']);

                /*
                //TODO: Handle a trait to add faction insertion, for now we will simply ignore things until we got the faction from EDDN
                if(is_null($factionId))
                {
                    try
                    {
                        $insert     = array('name' => $faction['Name']);
                        $factionId  = $factionsModel->insert($insert);
                        $factionId  = array('id' => $factionId);
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        if(strpos($e->getMessage(), '1062 Duplicate') !== false) // Can happen when the same faction is submitted twice during the process
                        {
                            $factionId = $factionsModel->getByName($faction['Name']);
                        }
                        else
                        {
                            $factionId = null;
                        }
                    } 
                }
                */
            }

            if(!is_null($factionId) && array_key_exists('id', $factionId))
            {
                $factionId = $factionId['id'];
                
                $currentReputation = $usersFactionsModel->getByRefUserAndRefFaction(static::$user->getId(), $factionId);
                
                if(is_null($currentReputation))
                {
                    $insert                         = array();
                    $insert['refUser']              = static::$user->getId();
                    $insert['refFaction']           = $factionId;
                    $insert['reputation']           = $faction['MyReputation'];
                    $insert['lastReputationUpdate'] = $timestamp;
                    
                    $usersFactionsModel->inser($insert);
                    
                    unset($insert);
                }
                else
                {
                    if($currentReputation['lastReputationUpdate'] < strtotime($timestamp))
                    {
                        $update                         = array();
                        $update['reputation']           = $faction['MyReputation'];
                        $update['lastReputationUpdate'] = $timestamp;
                        
                        $usersFactionsModel->updateByRefUserAndRefFaction(
                            static::$user->getId(),
                            $factionId,
                            $update
                        );
                        
                        unset($update);
                    }
                }
            }
        }
        
        unset($usersFactionsModel);
        
        return;
    }
}