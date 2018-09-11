<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Interdiction extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Link Interdiction to current system.',
    ];
    
    
    
    public static function run($json)
    {
        // Convert Faction to Power
        if(array_key_exists('Power', $json) && array_key_exists('Faction', $json))
        {
            $allegianceId   = \Alias\System\Allegiance::getFromFd($json['Power']);
            $powerId        = \Alias\System\Power::getFromFd($json['Faction']);
            
            if((!is_null($allegianceId) || empty($json['Power'])) && !is_null($powerId))
            {
                $json['Power'] = $json['Faction']; 
                unset($json['Faction']);
            }
        }
        
        // Check if power is known in EDSM
        if(array_key_exists('Power', $json))
        {
            $powerId        = \Alias\System\Power::getFromFd($json['Power']);
            
            if(is_null($powerId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';
                
                \EDSM_Api_Logger_Alias::log(
                    'Alias\System\Power: ' . $json['Power'] . ' (Sofware#' . static::$softwareId . ')',
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
        }
        
        $systemId = static::findSystemId($json);
        
        if(!is_null($systemId))
        {
            $systemsInterdictionsModel = new \Models_Systems_Interdictions;
            
            $isAlreadyStored   = $systemsInterdictionsModel->fetchRow(
                $systemsInterdictionsModel->select()
                                  ->where('refInterdictor = ?', static::$user->getId())
                                  ->where('refSystem = ?', $systemId)
                                  ->where('dateEvent = ?', $json['timestamp'])
            );
            
            if(is_null($isAlreadyStored))
            {
                $insert                         = array();
                $insert['refSystem']            = $systemId;
                $insert['refInterdictor']       = static::$user->getId();
                $insert['isPlayerInterdictor']  = 1;
                $insert['dateEvent']            = $json['timestamp'];
                
                if(array_key_exists('Power', $json) && !is_null($powerId))
                {
                    $insert['refPower'] = $powerId;
                }
                
                if(array_key_exists('Success', $json) && $json['Success'] === true)
                {
                    $insert['isSuccess'] = 1;
                }
                else
                {
                    $insert['isSuccess'] = 0;
                }
                
                if(array_key_exists('IsPlayer', $json) && $json['IsPlayer'] === true)
                {
                    $insert['isPlayerInterdicted']  = 1;
                    
                    if(array_key_exists('CombatRank', $json))
                    {
                        $insert['combatRank'] = $json['CombatRank'];
                    }
                    
                    if(array_key_exists('Interdicted', $json))
                    {
                        // Try to find the current player
                        $usersModel = new \Models_Users;
                        $isUser     = $usersModel->getByName($json['Interdicted']);
                        
                        if(!is_null($isUser))
                        {
                            $insert['refInterdicted']       = $isUser['id'];
                        }
                        else
                        {
                            $insert['nameInterdicted']      = $json['Interdicted'];
                        }
                        
                        unset($usersModel, $isUser);
                    }
                }
                else
                {
                    if(array_key_exists('Interdicted', $json))
                    {
                        $insert['nameInterdicted']      = $json['Interdicted'];
                    }
                    
                    $insert['isPlayerInterdicted']  = 0;
                }
                
                if(array_key_exists('Faction', $json) && !empty($json['Faction']))
                {
                    $factionsModel      = new \Models_Factions;
                    $currentFaction     = $factionsModel->getByName($json['Faction']);
                    
                    if(!is_null($currentFaction))
                    {
                        $currentFactionId = $currentFaction['id'];
                    }
                    else
                    {
                        $currentFactionId = $factionsModel->insert(['name' => $json['Faction']]);
                    }
                    
                    $insert['refFaction'] = (int) $currentFactionId;
                    
                    unset($factionsModel, $currentFaction, $currentFactionId);
                }
                
                $systemsInterdictionsModel->insert($insert);
                
                unset($insert);
            }
            else
            {
                static::$return['msgnum']   = 101;
                static::$return['msg']      = 'Message already stored';
            }
            
            unset($systemsInterdictionsModel, $isAlreadyStored);
        }
        
        return static::$return;
    }
}