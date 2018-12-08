<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MaterialTrade extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the paid material from the commander inventory.',
        'Add the received material to the commander inventory.',
    ];



    public static function run($json)
    {
        $materialTraderType = null;

        if($json['TraderType'] == 'encoded')
        {
            $databaseModel      = new \Models_Users_Data;
            $aliasClass         = 'Alias\Commander\Data';
            $materialTraderType = 'Encoded';
        }
        elseif(in_array($json['TraderType'], ['raw', 'manufactured']))
        {
            $databaseModel  = new \Models_Users_Materials;
            $aliasClass     = 'Alias\Commander\Material';

            if($json['TraderType'] == 'raw')
            {
                $materialTraderType = 'Raw';
            }
            if($json['TraderType'] == 'manufactured')
            {
                $materialTraderType = 'Manufactured';
            }
        }
        else
        {
            static::$return['msgnum']   = 401;
            static::$return['msg']      = 'Category unknown';

            return static::$return;
        }

        // Update materialTraderType
        if(!is_null($materialTraderType))
        {
            $stationId = static::findStationId($json);
            if(!is_null($stationId))
            {
                $station                    = \EDSM_System_Station::getInstance($stationId);
                $currentMaterialTraderType  = $station->getMaterialTraderType(true);

                if($currentMaterialTraderType != $materialTraderType)
                {
                    $stationsModel = new \Models_Stations;
                    $stationsModel->updateById(
                        $stationId,
                        [
                            'materialTraderType' => $materialTraderType,
                        ]
                    );

                    unset($stationsModel);
                }
            }
        }


        if(array_key_exists('Paid', $json) && array_key_exists('Material', $json['Paid']) && !empty($json['Paid']['Material']))
        {
            // Check if material/data is known in EDSM
            $currentPaidItemId = $aliasClass::getFromFd($json['Paid']['Material']);

            if(is_null($currentPaidItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['Paid']['Material']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('Received', $json) && array_key_exists('Material', $json['Received']) && !empty($json['Received']['Material']))
        {
            // Check if material/data is known in EDSM
            $currentReceivedItemId = $aliasClass::getFromFd($json['Received']['Material']);

            if(is_null($currentReceivedItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['Received']['Material']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(!is_null($currentPaidItemId))
        {
            // Find the current item ID
            $currentItem   = null;
            $currentItems  = $databaseModel->getByRefUser(static::$user->getId());

            if(!is_null($currentItems))
            {
                foreach($currentItems AS $tempItem)
                {
                    if($tempItem['type'] == $currentPaidItemId)
                    {
                        $currentItem = $tempItem;
                        break;
                    }
                }
            }

            unset($currentItems);

            // If we have the line update, else wait for the startUp events
            if(!is_null($currentItem))
            {
                if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                {
                    $update                 = array();
                    $update['total']        = max(0, $currentItem['total'] - $json['Paid']['Quantity']);
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

        if(!is_null($currentReceivedItemId))
        {
            // Find the current item ID
            $currentItem   = null;
            $currentItems  = $databaseModel->getByRefUser(static::$user->getId());

            if(!is_null($currentItems))
            {
                foreach($currentItems AS $tempItem)
                {
                    if($tempItem['type'] == $currentReceivedItemId)
                    {
                        $currentItem = $tempItem;
                        break;
                    }
                }
            }

            unset($currentItems);

            // If we have the line, update else insert the Quantity quantity
            if(!is_null($currentItem))
            {
                if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                {
                    $update                 = array();
                    $update['total']        = $currentItem['total'] + $json['Received']['Quantity'];
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
            else
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['type']         = $currentReceivedItemId;
                $insert['total']        = $json['Received']['Quantity'];
                $insert['lastUpdate']   = $json['timestamp'];

                $databaseModel->insert($insert);

                unset($insert);
            }
        }

        unset($databaseModel);

        return static::$return;
    }
}