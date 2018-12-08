<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Materials extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Overwrite the materials to the commander inventory.',
        'Overwrite the data to the commander inventory.',
    ];



    public static function run($json)
    {
        $json['Raw'] = array_merge($json['Raw'], $json['Manufactured']);
        unset($json['Manufactured']);

        static::handleMaterials($json);
        static::handleData($json);

        return static::$return;
    }

    private static function handleMaterials($json)
    {
        $databaseModel  = new \Models_Users_Materials;
        $aliasClass     = 'Alias\Commander\Material';

        $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

        foreach($json['Raw'] AS $inventoryItem)
        {
            if(array_key_exists('Name', $inventoryItem) && !empty($inventoryItem['Name']))
            {
                // Check if material/data is known in EDSM
                $currentItemId = $aliasClass::getFromFd($inventoryItem['Name']);

                if(is_null($currentItemId))
                {
                    \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $inventoryItem['Name']);
                }
                else
                {
                    // Find the current item ID
                    $currentItem = null;

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
                            $update                 = array();
                            $update['total']        = $inventoryItem['Count'];
                            $update['lastUpdate']   = $json['timestamp'];

                            $databaseModel->updateById($currentItem['id'], $update);

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

        // The remaining current items should be set to 0
        foreach($currentItems AS $currentItem)
        {
            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
            {
                $update                 = array();
                $update['total']        = 0;
                $update['lastUpdate']   = $json['timestamp'];

                $databaseModel->updateById($currentItem['id'], $update);

                unset($update);
            }
        }

        unset($databaseModel, $currentItems);
    }

    private static function handleData($json)
    {
        $databaseModel  = new \Models_Users_Data;
        $aliasClass     = 'Alias\Commander\Data';

        $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

        foreach($json['Encoded'] AS $inventoryItem)
        {
            // Check if material/data is known in EDSM
            $currentItemId = $aliasClass::getFromFd($inventoryItem['Name']);

            if(is_null($currentItemId))
            {
                \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $inventoryItem['Name']);
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
                        $update                 = array();
                        $update['total']        = $inventoryItem['Count'];
                        $update['lastUpdate']   = $json['timestamp'];

                        $databaseModel->updateById($currentItem['id'], $update);

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

        // The remaining current items should be set to 0
        foreach($currentItems AS $currentItem)
        {
            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
            {
                $update                 = array();
                $update['total']        = 0;
                $update['lastUpdate']   = $json['timestamp'];

                $databaseModel->updateById($currentItem['id'], $update);

                unset($update);
            }
        }

        unset($databaseModel, $currentItems);
    }
}