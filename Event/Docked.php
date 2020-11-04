<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Docked extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update ship current system/station.',
    ];



    public static function run($json)
    {
        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $station        = \EDSM_System_Station::getInstance($stationId);

            if(in_array($station->getType(), [12, 21, 31]))
            {
                if(strtotime($station->getUpdateTime()) < strtotime($json['timestamp']))
                {
                    $update         = array();
                    $jsonSystemId   = static::findSystemId($json, false, true);
                    $storedSystem   = $station->getSystem();
                    $storedSystemId = null;

                    if(!is_null($storedSystem))
                    {
                        $storedSystemId = $storedSystem->getId();
                    }

                    // Save megaship systems history if moved
                    if(!is_null($jsonSystemId) && $storedSystemId !== $jsonSystemId)
                    {
                        // Add old system to history
                        $systemsHistory             = $station->getSystemsHistory();
                        $systemsHistory[time()]     = $storedSystemId;
                        $systemsHistory             = array_slice($systemsHistory, -100);

                        $update['refSystem']        = $jsonSystemId;
                        $update['systemsHistory']   = \Zend_Json::encode($systemsHistory);
                        $update['refBody']          = new \Zend_Db_Expr('NULL');
                    }

                    if(count($update) > 0)
                    {
                        // Update system/name
                        $stationsModel = new \Models_Stations;
                        $stationsModel->updateById($stationId, $update);
                        $station = \EDSM_System_Station::getInstance($stationId);
                    }
                }
            }

            // Update ship parking
            $currentShipId = static::findShipId($json);

            if(!is_null($currentShipId))
            {
                static::updateCurrentGameShipId($currentShipId, $json['timestamp']);

                $usersShipsModel    = new \Models_Users_Ships;
                $currentShipId      = static::$user->getShipById($currentShipId);

                if(!is_null($currentShipId))
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();

                    if(!array_key_exists('locationUpdated', $currentShip) || is_null($currentShip['locationUpdated']) || strtotime($currentShip['locationUpdated']) < strtotime($json['timestamp']))
                    {
                        $station                    = \EDSM_System_Station::getInstance($stationId);
                        $system                     = $station->getSystem();

                        if(!is_null($system))
                        {
                            $update['refSystem']        = (int) $system->getId();
                        }
                        $update['refStation']       = (int) $station->getId();
                        $update['locationUpdated']  = $json['timestamp'];
                    }

                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }

                    unset($update);
                }

                unset($usersShipsModel, $currentShipId);
            }

            // Update user last docking
            $lastDocked = static::$user->getDateLastDocked();

            if(is_null($lastDocked) || strtotime($lastDocked) < strtotime($json['timestamp']))
            {
                $usersModel = new \Models_Users;
                $usersModel->updateById(
                    static::$user->getId(),
                    [
                        'dateLastDocked' => $json['timestamp'],
                    ]
                );

                unset($usersModel);
            }

            // Is it Hutton?!
            if($stationId == 806)
            {
                static::$user->giveBadge(600);
            }

            // Follow up to get some EDDN feed for console users...
            if(!is_null(static::$user->getPlatform()))
            {
                $station                    = \EDSM_System_Station::getInstance($stationId);
                $system                     = $station->getSystem();

                \EDDN\Station\Information::handle($system->getId(), $stationId, $json);
                \EDDN\Station\Services::handle($stationId, $json);
            }
        }

        // Give badge
        if(array_key_exists('SystemAddress', $json) && $json['SystemAddress'] == 216770054355
            && array_key_exists('MarketID', $json) && $json['MarketID'] == 128774957
            && strtotime($json['timestamp']) > strtotime('2018-09-01 00:00:00') && strtotime($json['timestamp']) < strtotime('2018-09-30 00:00:00'))
        {
            static::$user->giveBadge(551);
        }

        if(array_key_exists('SystemAddress', $json) && $json['SystemAddress'] == 2003342215523)
        {
            static::$user->giveBadge(530);
        }


        return static::$return;
    }
}