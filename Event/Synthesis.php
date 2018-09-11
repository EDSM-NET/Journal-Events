<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Synthesis extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the materials/data from the commander inventory if newer than the last update.',
    ];
    
    
    
    public static function run($json)
    {
        $aliasClasses   = [
            'materials'     => 'Alias\Commander\Material',
            'data'          => 'Alias\Commander\Data',
        ];
        
        $databaseModels = [
            'materials'     => new \Models_Users_Materials,
            'data'          => new \Models_Users_Data,
        ];
        
        $currentItems   = [
            'materials'     => $databaseModels['materials']->getByRefUser(static::$user->getId()),
            'data'          => $databaseModels['data']->getByRefUser(static::$user->getId()),
        ];
        
        foreach($json['Materials'] AS $key => $material)
        {
            // Old journal version conversion
            if(!is_array($material))
            {
                $material   = [
                    'Name'      => $key,
                    'Count'     => $material,
                ];
            }
            
            if(array_key_exists('Name', $material) && !empty($material['Name']))
            {
                $aliasClass = $aliasType = $currentItemId = null;
                
                foreach($aliasClasses AS $type => $class)
                {
                    // Check if type is known in EDSM
                    $currentItemId = $class::getFromFd($material['Name']);
                    
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
                            $update                 = array();
                            $update['total']        = max(0, $currentItem['total'] - $material['Count']);
                            $update['lastUpdate']   = $json['timestamp'];
                            
                            $databaseModels[$aliasType]->updateById($currentItem['id'], $update);
                            
                            unset($update);
                        }
                    }
                }
            }
        }
        
        return static::$return;
    }
}