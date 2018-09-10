<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class TechnologyBroker extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Unlock the items',
        'Remove the materials/data/commodities from the commander inventory if newer than the last update.',
    ];
    
    
    
    public static function run($json)
    {
        // Force broker type in database
        if(array_key_exists('BrokerType', $json))
        {
            $stationId = static::findStationId($json);
            if(!is_null($stationId))
            {
                $station                    = \EDSM_System_Station::getInstance($stationId);
                $currentBrokerType          = $station->getBrokerType(true);
                
                if($currentBrokerType != $json['BrokerType'])
                {
                    $stationsModel = new \Models_Stations;
                    $stationsModel->updateById(
                        $stationId,
                        [
                            'brokerType' => ucfirst($json['BrokerType']),
                        ]
                    );
                    
                    unset($stationsModel, $currentBrokerType);
                }
            }
        }
        
        $aliasClass = 'Alias\Commander\TechnologyBroker';
        
        // Check if unlocked items are known
        foreach($json['ItemsUnlocked'] AS $unlockedItem)
        {
            $currentItemId = $aliasClass::getFromFd($unlockedItem['Name']);
            
            if(is_null($currentItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';
                
                if(array_key_exists('Name_Localised', $unlockedItem))
                {
                    \EDSM_Api_Logger_Alias::log(
                        $aliasClass . ': ' . $unlockedItem['Name'] . ' / ' . $unlockedItem['Name_Localised'] . ' (Sofware#' . static::$softwareId . ')',
                        [
                            'file'  => __FILE__,
                            'line'  => __LINE__,
                        ]
                    );
                }
                else
                {
                    \EDSM_Api_Logger_Alias::log(
                        $aliasClass . ': ' . $unlockedItem['Name'] . ' (Sofware#' . static::$softwareId . ')',
                        [
                            'file'  => __FILE__,
                            'line'  => __LINE__,
                        ]
                    );
                }
                
                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);
                
                return static::$return;
            }
        }
        
        static::handleItemsUnlocked($json);
        static::handleIngredients($json);
        
        return static::$return;
    }
    
    private static function handleItemsUnlocked($json)
    {
        $unlockedItems  = array();
        $aliasClass     = 'Alias\Commander\TechnologyBroker';
        
        foreach($json['ItemsUnlocked'] AS $unlockedItem)
        {
            $currentItemId = $aliasClass::getFromFd($unlockedItem['Name']);
            
            if(!is_null($currentItemId) && !in_array($currentItemId, $unlockedItems))
            {
                $unlockedItems[] = $currentItemId;
            }
        }
        
        if(count($unlockedItems) > 0)
        {
            $usersTechnologyBrokersModel    = new \Models_Users_TechnologyBrokers;
            $currentTechnologies            = $usersTechnologyBrokersModel->getByRefUser(static::$user->getId());
            
            foreach($unlockedItems AS $unlockedItem)
            {
                $currentTechnology      = null;
                
                // Try to find current technology in the technologies array
                if(!is_null($currentTechnologies) && count($currentTechnologies) > 0)
                {
                    foreach($currentTechnologies AS $technology)
                    {
                        if($technology['refTechnology'] == $unlockedItem)
                        {
                            $currentTechnology = $technology;
                            break;
                        }
                    }
                }
                
                if(is_null($currentTechnology))
                {
                    try
                    {
                        $insert                     = array();
                        $insert['lastUpdate']       = $json['timestamp'];
                        $insert['refUser']          = static::$user->getId();
                        $insert['refTechnology']    = $unlockedItem;
                            
                        $usersTechnologyBrokersModel->insert($insert);
                        
                        unset($insert);
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        static::$return['msgnum']   = 500;
                        static::$return['msg']      = 'Exception: ' . $e->getMessage();
                        $json['isError']            = 1;
                        
                        \Journal\Event::run($json);
                    }
                }
            }
            
            unset($usersTechnologyBrokersModel, $currentTechnologies, $unlockedItems);
        }
    }
    
    private static function handleIngredients($json)
    {
        $aliasClasses   = [
            'materials'     => 'Alias\Commander\Material',
            'data'          => 'Alias\Commander\Data',
            'cargo'         => 'Alias\Station\Commodity\Type',
        ];
        
        $databaseModels = [
            'materials'     => new \Models_Users_Materials,
            'data'          => new \Models_Users_Data,
            'cargo'         => new \Models_Users_Cargo,
        ];
        
        $currentItems   = [
            'materials'     => $databaseModels['materials']->getByRefUser(static::$user->getId()),
            'data'          => $databaseModels['data']->getByRefUser(static::$user->getId()),
            'cargo'         => $databaseModels['cargo']->getByRefUser(static::$user->getId()),
        ];
        
        // Convert to new journal format.
        if(!array_key_exists('Ingredients', $json))
        {
            $json['Ingredients'] = array();
            
            if(array_key_exists('Commodities', $json))
            {
                foreach($json['Commodities'] AS $ingredient)
                {
                    $json['Ingredients'][] = $ingredient;
                }
                
                unset($json['Commodities']);
            }
            
            if(array_key_exists('Materials', $json))
            {
                foreach($json['Materials'] AS $ingredient)
                {
                    $json['Ingredients'][] = $ingredient;
                }
                
                unset($json['Materials']);
            }
        }
        
        foreach($json['Ingredients'] AS $ingredient)
        {
            if(array_key_exists('Name', $ingredient) && !empty($ingredient['Name']))
            {
                $aliasClass = $aliasType = $currentItemId = null;
                
                foreach($aliasClasses AS $type => $class)
                {
                    // Check if type is known in EDSM
                    $currentItemId = $class::getFromFd($ingredient['Name']);
                    
                    if(!is_null($currentItemId))
                    {
                        $aliasType  = $type;
                        $aliasClass = $class;
                        
                        break;
                    }
                }
                
                if(!is_null($currentItemId) && !is_null($aliasType) && !is_null($aliasClass))
                {
                    // Find the current item ID
                    $currentItem = null;
                    
                    if(!is_null($currentItems[$aliasType]))
                    {
                        foreach($currentItems[$aliasType] AS $tempItem)
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
                            $databaseModels[$aliasType]->updateById(
                                $currentItem['id'],
                                [
                                    'total'         => max(0, $currentItem['total'] - $ingredient['Count']),
                                    'lastUpdate'    => $json['timestamp'],
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}