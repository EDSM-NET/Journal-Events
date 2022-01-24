<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class UseConsumable extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove consumable from commander backpack.',
    ];



    public static function run($json)
    {
        if(array_key_exists('Name', $json))
        {
            $aliasClass     = 'Alias\Commander\MicroResource\Type';
            
            // Check if MicroResource is known in EDSM
            $currentItemId = $aliasClass::getFromFd($json['Name']);

            if(is_null($currentItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['Name']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
            
            $databaseModel  = new \Models_Users_MicroResources;        
            $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

            // Find the current item ID
            $currentItem   = null;
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
                    $update['total']        = max(0, $currentItem['total'] - 1);
                    $update['lastUpdate']   = $json['timestamp'];

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
        }

        return static::$return;
    }
}