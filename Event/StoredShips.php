<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class StoredShips extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update/Insert all commander stored ships',
    ];



    public static function run($json)
    {
        $usersShipsModel    = new \Models_Users_Ships;

        // Loop into the current station
        if(array_key_exists('ShipsHere', $json))
        {
            $currentStationId = static::findStationId($json);

            foreach($json['ShipsHere'] AS $ship)
            {
                $shipType           = \Alias\Ship\Type::getFromFd($ship['ShipType']);
                $currentShipId      = static::$user->getShipById($ship['ShipID'], $shipType);

                if(is_null($shipType))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown (' . $ship['ShipType'] . ')';

                    \EDSM_Api_Logger_Alias::log('Alias\Ship\Type: ' . $ship['ShipType']);

                    continue;
                }

                // Insert ship
                if(is_null($currentShipId))
                {
                    $insert                 = array();
                    $insert['refUser']      = static::$user->getId();
                    $insert['refShip']      = $ship['ShipID'];
                    $insert['type']         = $shipType;
                    $insert['dateUpdated']  = $json['timestamp'];

                    if(array_key_exists('Name', $ship))
                    {
                        $insert['customName'] = $ship['Name'];
                    }

                    if(!is_null($currentStationId))
                    {
                        $currentStation         = \EDSM_System_Station::getInstance($currentStationId);

                        if($currentStation->isValid() && !is_null($currentStation->getSystem()))
                        {
                            $insert['refStation']   = $currentStationId;
                            $insert['refSystem']    = $currentStation->getSystem()->getId();
                        }
                    }

                    try
                    {
                        $usersShipsModel->insert($insert);
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        // Based on unique index, this ship entry was already saved.
                        if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                        {
                            // CONTINUE...
                        }
                        else
                        {
                            static::$return['msgnum']   = 500;
                            static::$return['msg']      = 'Exception: ' . $e->getMessage();

                            if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                            {
                                \Sentry\captureException($e);
                            }
                        }
                    }

                    unset($insert);
                }
                // Update ship if needed
                else
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();

                    if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['type']             = $shipType;
                        $update['sell']             = 0; // If in stored ships, cannot be sold.
                        $update['dateUpdated']      = $json['timestamp'];

                        if(array_key_exists('Name', $ship))
                        {
                            $update['customName'] = $ship['Name'];
                        }

                        if(!is_null($currentStationId))
                        {
                            $currentStation         = \EDSM_System_Station::getInstance($currentStationId);

                            if($currentStation->isValid() && !is_null($currentStation->getSystem()))
                            {
                                $update['refStation']   = $currentStationId;
                                $update['refSystem']    = $currentStation->getSystem()->getId();
                            }
                        }
                    }

                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }

                    unset($currentShip, $update);
                }
            }
        }

        // Loop for foreign ships
        if(array_key_exists('ShipsRemote', $json))
        {
            $stationsModel  = new \Models_Stations;

            foreach($json['ShipsRemote'] AS $ship)
            {
                $currentStationId = null;

                if(array_key_exists('ShipMarketID', $ship))
                {
                    $station        = $stationsModel->getByMarketId($ship['ShipMarketID']);

                    if(!is_null($station))
                    {
                        $currentStationId = $station['id'];
                    }
                }

                $shipType           = \Alias\Ship\Type::getFromFd($ship['ShipType']);
                $currentShipId      = static::$user->getShipById($ship['ShipID'], $shipType);

                if(is_null($shipType))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown (' . $ship['ShipType'] . ')';

                    \EDSM_Api_Logger_Alias::log('Alias\Ship\Type: ' . $ship['ShipType']);

                    continue;
                }

                // Insert ship
                if(is_null($currentShipId))
                {
                    $insert                 = array();
                    $insert['refUser']      = static::$user->getId();
                    $insert['refShip']      = $ship['ShipID'];
                    $insert['type']         = $shipType;
                    $insert['dateUpdated']  = $json['timestamp'];

                    if(array_key_exists('Name', $ship))
                    {
                        $insert['customName'] = $ship['Name'];
                    }

                    if(!is_null($currentStationId))
                    {
                        $currentStation         = \EDSM_System_Station::getInstance($currentStationId);

                        if($currentStation->isValid() && !is_null($currentStation->getSystem()))
                        {
                            $insert['refStation']   = $currentStationId;
                            $insert['refSystem']    = $currentStation->getSystem()->getId();
                        }
                    }

                    try
                    {
                        $usersShipsModel->insert($insert);
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        // Based on unique index, this ship entry was already saved.
                        if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                        {
                            // CONTINUE...
                        }
                        else
                        {
                            static::$return['msgnum']   = 500;
                            static::$return['msg']      = 'Exception: ' . $e->getMessage();

                            if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                            {
                                \Sentry\captureException($e);
                            }
                        }
                    }

                    unset($insert);
                }
                // Update ship if needed
                else
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();

                    if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['type']             = $shipType;
                        $update['sell']             = 0; // If in stored ships, cannot be sold.
                        $update['dateUpdated']      = $json['timestamp'];

                        if(array_key_exists('Name', $ship))
                        {
                            $update['customName'] = $ship['Name'];
                        }

                        if(!is_null($currentStationId))
                        {
                            $currentStation         = \EDSM_System_Station::getInstance($currentStationId);

                            if($currentStation->isValid() && !is_null($currentStation->getSystem()))
                            {
                                $update['refStation']   = $currentStationId;
                                $update['refSystem']    = $currentStation->getSystem()->getId();
                            }
                        }
                    }

                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }

                    unset($currentShip, $update);
                }
            }

            unset($stationsModel);
        }

        unset($usersShipsModel);

        return static::$return;
    }
}