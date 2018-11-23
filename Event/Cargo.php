<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Cargo extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set the commander cargo hold.',
    ];



    public static function run($json)
    {
        // 3.3: Don't use the new empty cargo
        if(!array_key_exists('Inventory', $json))
        {
            return static::$return;
        }

        // 3.3: Skip Vessel: "SRV"
        //TODO: Handle SRV cargo ;)
        if(array_key_exists('Vessel', $json) && $json['Vessel'] == 'SRV')
        {
            return static::$return;
        }


        $databaseModel  = new \Models_Users_Cargo;
        $aliasClass     = 'Alias\Station\Commodity\Type';

        $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

        foreach($json['Inventory'] AS $inventoryItem)
        {
            // Check if commodity is known in EDSM
            $currentItemId = $aliasClass::getFromFd($inventoryItem['Name']);

            if(is_null($currentItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log(
                    $aliasClass . ': ' . $inventoryItem['Name'] . ' (Sofware#' . static::$softwareId . ')',
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
            else
            {
                //BADGES: Thargoid Encounter
                if(in_array($currentItemId, [1800, 1802, 1803, 1804]))
                {
                    static::$user->giveBadge(4000);
                }

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
                        $update['totalStolen']  = ( (array_key_exists('Stolen', $inventoryItem)) ? $inventoryItem['Stolen'] : 0 );
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
                    $insert['totalStolen']  = ( (array_key_exists('Stolen', $inventoryItem)) ? $inventoryItem['Stolen'] : 0 );
                    $insert['lastUpdate']   = $json['timestamp'];

                    $databaseModel->insert($insert);

                    unset($insert);
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
                $update['totalStolen']  = 0;
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