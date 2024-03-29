<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

use         Alias\Station\Engineer;

class Location extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update ship current system/station.',
        'Insert FSDJump in case of mismatched location'
    ];



    public static function run($json)
    {
        $systemId       = null;
        $currentShipId  = null;

        if(array_key_exists('Docked', $json) && $json['Docked'] === true)
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

                $system         = $station->getSystem();
                $currentShipId  = static::findShipId($json);

                $systemId       = (int) $system->getId();

                // Update ship parking
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
                            $update['refSystem']        = $systemId;
                            $update['refStation']       = (int) $station->getId();
                            $update['locationUpdated']  = $json['timestamp'];
                        }

                        if(count($update) > 0)
                        {
                            $usersShipsModel->updateById($currentShipId, $update);
                        }

                        unset($update);
                    }

                    unset($usersShipsModel);
                }
            }

            // Give badge
            if(array_key_exists('SystemAddress', $json) && $json['SystemAddress'] == 216770054355
                && array_key_exists('MarketID', $json) && $json['MarketID'] == 128774957
                && strtotime($json['timestamp']) > strtotime('2018-09-01 00:00:00') && strtotime($json['timestamp']) < strtotime('2018-09-30 23:59:59'))
            {
                static::$user->giveBadge(551);
            }
        }

        // Insert a fake FSDJump in case of death or game failure.
        if(is_null($systemId))
        {
            $systemId = static::findSystemId($json, false, true);
        }

        if(!is_null($systemId))
        {
            $systemsLogsModel   = new \Models_Systems_Logs;
            $lastFSDJump        = $systemsLogsModel->fetchRow(
                $systemsLogsModel->select()
                                 ->where('user = ?', static::$user->getId())
                                 ->where('dateVisited <= ?', $json['timestamp'])
                                 ->order('dateVisited DESC')
                                 ->limit(1)
            );

            if(is_null($lastFSDJump) || (!is_null($lastFSDJump) && $lastFSDJump->system != $systemId && $lastFSDJump->dateVisited != $json['timestamp']))
            {
                $insert                 = array();
                $insert['user']         = static::$user->getId();
                $insert['system']       = $systemId;
                $insert['refSoftware']  = static::$softwareId;
                $insert['dateVisited']  = $json['timestamp'];

                $systemsLogsModel->insert($insert);

                unset($insert);
            }

            unset($systemsLogsModel);
        }

        // Handle user faction reputation
        if(array_key_exists('Factions', $json))
        {
            static::handleMyReputation($json['Factions'], $json['timestamp']);
        }

        return static::$return;
    }

    public static function handleMyReputation($factions, $timestamp)
    {
        try
        {
            $factionsModel      = new \Models_Factions;
            $usersFactionsModel = new \Models_Users_Factions;

            foreach($factions AS $faction)
            {
                if(!array_key_exists('MyReputation', $faction))
                {
                    // Old journal, exit the loop
                    break;
                }

                $faction['Name']    = trim($faction['Name']);
                $factionId          = null;

                if(!empty($faction['Name']) && !in_array($faction['Name'], Engineer::getAll()))
                {
                    // Special continue case for Pilots Federation Local Branch
                    if($faction['Name'] == 'Pilots Federation Local Branch' && $faction['Influence'] == 0)
                    {
                        continue;
                    }

                    $factionId          = $factionsModel->getByName($faction['Name']);

                    /*
                    //TODO: Handle a trait to add faction insertion, for now we will simply ignore things until we got the faction from EDDN
                    if(is_null($factionId))
                    {
                        try
                        {
                            $insert     = array('name' => $faction['Name']);
                            $factionId  = $factionsModel->insert($insert);
                            $factionId  = array('id' => $factionId);
                        }
                        catch(\Zend_Db_Exception $e)
                        {
                            if(strpos($e->getMessage(), '1062 Duplicate') !== false) // Can happen when the same faction is submitted twice during the process
                            {
                                $factionId = $factionsModel->getByName($faction['Name']);
                            }
                            else
                            {
                                $factionId = null;
                            }
                        }
                    }
                    */
                }

                if(!is_null($factionId) && array_key_exists('id', $factionId))
                {
                    $factionId = $factionId['id'];

                    $currentReputation = $usersFactionsModel->getByRefUserAndRefFaction(static::$user->getId(), $factionId);

                    if(is_null($currentReputation))
                    {
                        $insert                         = array();
                        $insert['refUser']              = static::$user->getId();
                        $insert['refFaction']           = $factionId;
                        $insert['reputation']           = $faction['MyReputation'];
                        $insert['lastReputationUpdate'] = $timestamp;

                        $usersFactionsModel->insert($insert);

                        unset($insert);
                    }
                    else
                    {
                        if($currentReputation['lastReputationUpdate'] < strtotime($timestamp))
                        {
                            $update                         = array();
                            $update['reputation']           = $faction['MyReputation'];
                            $update['lastReputationUpdate'] = $timestamp;

                            $usersFactionsModel->updateByRefUserAndRefFaction(
                                static::$user->getId(),
                                $factionId,
                                $update
                            );

                            unset($update);
                        }
                    }
                }
            }

            unset($usersFactionsModel);
        }
        catch(\Zend_Db_Exception $e)
        {
            // Based on unique index, this entry was already saved.
            if(strpos($e->getMessage(), '1062 Duplicate') !== false)
            {
                return;
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

        return;
    }
}