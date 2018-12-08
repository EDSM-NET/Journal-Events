<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class EjectCargo extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the cargo from the commander cargo hold.',
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

            \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['Type']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
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

        // If we have the line, update else, wait for the startUp events
        if(!is_null($currentItem))
        {
            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
            {
                $update                 = array();
                $update['total']        = max(0, $currentItem['total'] - $json['Count']);
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

        unset($databaseModel, $currentItems);

        return static::$return;
    }
}