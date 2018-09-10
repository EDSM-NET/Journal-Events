<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CollectCargo extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add the cargo to the commander cargo hold.',
    ];
    
    
    
    public static function run($json)
    {
        $databaseModel  = new \Models_Users_Cargo;
        $aliasClass     = 'Alias\Station\Commodity\Type';
        
        // Check if cargo is known in EDSM
        $currentItemId = $aliasClass::getFromFd($json['Type']);
        
        if(is_null($currentItemId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            \EDSM_Api_Logger_Alias::log(
                $aliasClass . ': ' . $json['Type'] . ' (Sofware#' . static::$softwareId . ')',
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
        
        //BADGE: Thargoid Encounter
        if(in_array($currentItemId, [1800, 1802, 1803, 1804]))
        {
            static::$user->giveBadge(4000);
        }
        
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
        
        // If we have the line, update else insert the Count quantity
        if(!is_null($currentItem))
        {
            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
            {
                $update                 = array();
                $update['total']        = $currentItem['total'] + 1;
                $update['lastUpdate']   = $json['timestamp'];
                
                if(array_key_exists('Stolen', $json) && $json['Stolen'] == true)
                {
                    $update['totalStolen'] = $currentItem['totalStolen'] + 1;
                }
                
                $databaseModel->updateById(
                    $currentItem['id'],
                    $update
                );
                
                unset($update);
            }
            else
            {
                static::$return['msgnum']   = 102;
                static::$return['msg']      = 'Message older than the stored one';
            }
        }
        else
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['type']         = $currentItemId;
            $insert['total']        = 1;
            $insert['totalStolen']  = ( (array_key_exists('Stolen', $json) && $json['Stolen'] == true) ? 1 : 0 );
            $insert['lastUpdate']   = $json['timestamp'];
            
            $databaseModel->insert($insert);
            
            unset($insert);
        }
        
        unset($databaseModel);
        
        return static::$return;
    }
}