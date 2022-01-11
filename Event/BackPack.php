<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BackPack extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set the commander backpack.',
    ];

    public static function run($json)
    {
        $databaseModel  = new \Models_Users_MicroResources;
        $aliasClass     = 'Alias\Commander\MicroResource\Type';
        $types          = ['Items', 'Data', 'Components', 'Consumables'];
        $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

        foreach($types AS $mainType)
        {
            if(array_key_exists($mainType, $json))
            {
                foreach($json[$mainType] AS $inventoryItem)
                {
                    // Check if commodity is known in EDSM
                    $currentItemId = $aliasClass::getFromFd($inventoryItem['Name']);

                    if(is_null($currentItemId))
                    {
                        static::$return['msgnum']   = 402;
                        static::$return['msg']      = 'Item unknown';

                        \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $inventoryItem['Name']);

                        // Save in temp table for reparsing
                        $json['isError']            = 1;
                        \Journal\Event::run($json);

                        return static::$return;
                    }
                    else
                    {
                        // Find the current item ID
                        $currentItem   = null;

                        if(!is_null($currentItems))
                        {
                            foreach($currentItems AS $keyItem => $tempItem)
                            {
                                if($tempItem['type'] == $currentItemId)
                                {
                                    $currentItem = $tempItem;

                                    // Remove current item
                                    unset($currentItems[$keyItem]);
                                    break;
                                }
                            }
                        }

                        // If we have the line, set the new quantity
                        if(!is_null($currentItem))
                        {
                            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                            {
                                $update = array();
                                $update['total']        = $inventoryItem['Count'];
                                $update['lastUpdate']   = $json['timestamp'];

                                $databaseModel->updateById(
                                    $currentItem['id'],
                                    $update
                                );

                                unset($update);
                            }
                        }
                        else
                        {
                            $insert                 = array();
                            $insert['refUser']      = static::$user->getId();
                            $insert['type']         = $currentItemId;
                            $insert['total']        = $inventoryItem['Count'];
                            $insert['lastUpdate']   = $json['timestamp'];

                            $databaseModel->insert($insert);

                            unset($insert);
                        }
                    }
                }
            }
        }

        // The remaining current items should be set to 0
        foreach($currentItems AS $currentItem)
        {
            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
            {
                $update = array();
                $update['total']        = 0;
                $update['lastUpdate']   = $json['timestamp'];

                $databaseModel->updateById(
                    $currentItem['id'],
                    $update
                );

                unset($update);
            }
        }

        unset($databaseModel);
        return static::$return;
    }
}