<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class TradeMicroResources extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the micro resource from the commander backpack.',
        'Add received micro resource to the commander backpack.',
    ];



    public static function run($json)
    {
        $databaseModel  = new \Models_Users_MicroResources;
        $aliasClass     = 'Alias\Commander\MicroResource\Type';
        $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

        if(array_key_exists('Offered', $json))
        {
            foreach($json['Offered'] AS  $backpackItem)
            {
                // Check if MicroResource is known in EDSM
                $currentItemId = $aliasClass::getFromFd($backpackItem['Name']);

                if(is_null($currentItemId))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $backpackItem['Name']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }

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
                        $update['total']        = max(0, $currentItem['total'] - $backpackItem['Count']);
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
        }
        
        if(array_key_exists('Received', $json) && array_key_exists('Count', $json))
        {
            // Check if MicroResource is known in EDSM
            $currentItemId = $aliasClass::getFromFd($json['Received']);

            if(is_null($currentItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['Received']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

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
                    $update['total']        = max(0, $currentItem['total'] + $json['Count']);
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

        unset($databaseModel, $currentItems);

        return static::$return;
    }
}