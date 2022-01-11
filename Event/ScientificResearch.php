<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ScientificResearch extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the material/data from the commander inventory.',
    ];



    public static function run($json)
    {
        if($json['Category'] == 'Encoded')
        {
            $databaseModel  = new \Models_Users_Data;
            $aliasClass     = 'Alias\Commander\Data';
        }
        elseif(in_array($json['Category'], ['Raw', 'Manufactured']))
        {
            $databaseModel  = new \Models_Users_Materials;
            $aliasClass     = 'Alias\Commander\Material';
        }
        else
        {
            static::$return['msgnum']   = 401;
            static::$return['msg']      = 'Category unknown';

            return static::$return;
        }

        if(array_key_exists('Name', $json) && !empty($json['Name']))
        {
            // Check if material/data is known in EDSM
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
                    $update = array();
                    $update['total']        = max(0, $currentItem['total'] - $json['Count']);
                    $update['lastUpdate']   = $json['timestamp'];

                    $databaseModel->updateById($currentItem['id'], $update);

                    unset($update);
                }
                else
                {
                    static::$return['msgnum']   = 102;
                    static::$return['msg']      = 'Message older than the stored one';
                }
            }
        }

        unset($databaseModel);

        return static::$return;
    }
}