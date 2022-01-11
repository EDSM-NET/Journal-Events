<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MissionRedirected extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Redirect mission to new destination.',
    ];



    public static function run($json)
    {
        $missionType        = \Alias\Station\Mission\Type::getFromFd($json['Name']);

        if(is_null($missionType))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        $usersMissionsModel = new \Models_Users_Missions;
        $currentMission     = $usersMissionsModel->getById($json['MissionID']);

        if(is_null($currentMission))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }
        else
        {
            if(!array_key_exists('dateRedirected', $currentMission) || is_null($currentMission['dateRedirected']) || strtotime($currentMission['dateRedirected']) < strtotime($json['timestamp']))
            {
                $details = array();
                $update  = array();

                if(!is_null($currentMission['details']))
                {
                    $details = \Zend_Json::decode($currentMission['details']);
                }

                $details['oldRefDestinationSystem']     = $currentMission['refDestinationSystem'];
                $details['oldRefDestinationStation']    = $currentMission['refDestinationStation'];

                $update['dateRedirected']               = $json['timestamp'];
                $update['refDestinationSystem']         = null;
                $update['refDestinationStation']        = null;

                // If mission have waypoints, separate the destination and make a WP array
                if(array_key_exists('NewDestinationSystem', $json) && stripos($json['NewDestinationSystem'], '$MISSIONUTIL_MULTIPLE_FINAL_SEPARATOR') !== false)
                {
                    $systems = explode('$MISSIONUTIL_MULTIPLE_FINAL_SEPARATOR;', $json['NewDestinationSystem']);

                    if(count($systems) == 2)
                    {
                        $json['NewDestinationSystem']   = $systems[1];
                        $systems                        = explode('$MISSIONUTIL_MULTIPLE_INNER_SEPARATOR;', $systems[0]);
                        $systemsModel                   = new \Models_Systems;
                        $details['passengerWaypoints']  = array();

                        // If only one waypoint
                        if(!is_array($systems))
                        {
                            $systems = array($systems);
                        }

                        foreach($systems AS $waypoint)
                        {
                            $system         = $systemsModel->getByName($waypoint);

                            if(!is_null($system))
                            {
                                $currentSystem = \Component\System::getInstance($system['id']);

                                // Check system renamed/merged to another
                                if($currentSystem->isHidden() === true)
                                {
                                    $mergedTo = $currentSystem->getMergedTo();

                                    if(!is_null($mergedTo))
                                    {
                                        // Switch systems when they have been renamed
                                        $currentSystem = \Component\System::getInstance($mergedTo);
                                    }
                                    else
                                    {
                                        $currentSystem = null;
                                    }
                                }

                                if(!is_null($currentSystem))
                                {
                                    $duplicates = $currentSystem->getDuplicates();

                                    // Only unique system can be checked without the coordinates
                                    if(is_null($duplicates))
                                    {
                                        $details['passengerWaypoints'][] = $currentSystem->getId();
                                    }
                                }
                            }
                        }
                    }
                }

                if(array_key_exists('NewDestinationSystem', $json))
                {
                    $systemsModel   = new \Models_Systems;
                    $system         = $systemsModel->getByName($json['NewDestinationSystem']);

                    if(!is_null($system))
                    {
                        $currentSystem = \Component\System::getInstance($system['id']);

                        // Check system renamed/merged to another
                        if($currentSystem->isHidden() === true)
                        {
                            $mergedTo = $currentSystem->getMergedTo();

                            if(!is_null($mergedTo))
                            {
                                // Switch systems when they have been renamed
                                $currentSystem = \Component\System::getInstance($mergedTo);
                            }
                            else
                            {
                                $currentSystem = null;
                            }
                        }

                        if(!is_null($currentSystem))
                        {
                            $duplicates = $currentSystem->getDuplicates();

                            // Only unique system can be checked without the coordinates
                            if(is_null($duplicates))
                            {
                                $update['refDestinationSystem'] = $currentSystem->getId();
                            }
                        }
                    }
                }

                if(array_key_exists('NewDestinationStation', $json))
                {
                    // Find station from the current systeù
                    if(array_key_exists('refDestinationSystem', $update) && !is_null($update['refDestinationSystem']))
                    {
                        $currentSystem = \Component\System::getInstance($update['refDestinationSystem']);
                        $stations      = $currentSystem->getStations(true);

                        if(!is_null($stations))
                        {
                            foreach($stations AS $station)
                            {
                                $currentStation = \EDSM_System_Station::getInstance($station['id']);

                                if($currentStation->getName() == $json['NewDestinationStation'])
                                {
                                    $update['refDestinationStation'] = $currentStation->getId();
                                    break;
                                }
                            }
                        }
                    }
                    // Try to find a unique station
                    else
                    {
                        // Find if it's a unique station
                        $stationsModel = new \Models_Stations;
                        $stations      = $stationsModel->fetchAll(
                            $stationsModel->select()->where('name = ?', $json['NewDestinationStation'])
                        );

                        if(!is_null($stations) && count($stations) == 1)
                        {
                            $stations = $stations->toArray();
                            $update['refDestinationStation'] = $stations[0]['id'];
                        }
                    }
                }

                ksort($details);
                $update['details'] = \Zend_Json::encode($details);

                $usersMissionsModel->updateById(
                    $currentMission['id'],
                    $update
                );

                unset($update);
            }
            else
            {
                static::$return['msgnum']   = 101;
                static::$return['msg']      = 'Message already stored';
            }
        }

        unset($usersMissionsModel);

        return static::$return;
    }
}