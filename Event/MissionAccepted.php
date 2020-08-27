<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MissionAccepted extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert the new mission.',
    ];



    public static function run($json)
    {
        $missionType            = \Alias\Station\Mission\Type::getFromFd($json['Name']);

        if(is_null($missionType))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            if(array_key_exists('LocalisedName', $json))
            {
                \EDSM_Api_Logger_Mission::log('Alias\Station\Mission\Type: ' . $json['Name'] . ' / ' . $json['LocalisedName']);
            }

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        if(array_key_exists('Commodity', $json))
        {
            $currentItemId      = \Alias\Station\Commodity\Type::getFromFd($json['Commodity']);

            if(is_null($currentItemId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('\Alias\Station\Commodity\Type: ' . $json['Commodity']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('PassengerType', $json))
        {
            $passengerTypeId      = \Alias\Station\Mission\Passenger\Type::getFromFd($json['PassengerType']);

            if(is_null($passengerTypeId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('\Alias\Station\Mission\Passenger\Type: ' . $json['PassengerType']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('Target', $json) && stripos(strtolower($json['Target']), '$missionutil_') !== false)
        {
            $currentTargetId    = \Alias\Station\Mission\Util::getFromFd($json['Target']);

            if(is_null($currentTargetId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('\Alias\Station\Mission\Util: ' . $json['Target']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('TargetType', $json) && stripos(strtolower($json['TargetType']), '$missionutil_') !== false)
        {
            $currentTargetId    = \Alias\Station\Mission\Util::getFromFd($json['TargetType']);

            if(is_null($currentTargetId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('\Alias\Station\Mission\Util: ' . $json['TargetType']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        $usersMissionsModel = new \Models_Users_Missions;
        $currentMission     = $usersMissionsModel->getById($json['MissionID']);

        if(is_null($currentMission))
        {
            $missionDetails                 = static::generateDetails($json);

            if(array_key_exists('details', $missionDetails))
            {
                $missionDetails['details'] = \Zend_Json::encode($missionDetails['details']);
            }

            $missionDetails['id']           = $json['MissionID'];
            $missionDetails['refUser']      = static::$user->getId();
            $missionDetails['type']         = $missionType;
            $missionDetails['status']       = 'Accepted';

            try
            {
                $usersMissionsModel->insert($missionDetails);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this mission entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';
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
        }
        else
        {
            $update         = array();
            $missionDetails = static::generateDetails($json);

            foreach($missionDetails AS $key => $detail)
            {
                // Modified detail
                if(!array_key_exists($key, $currentMission) || $currentMission[$key] != $detail)
                {
                    // Do not update if mission was completed
                    if(in_array($key, array('reward', 'donation')) && !is_null($currentMission['dateCompleted']))
                    {

                    }
                    // Details needs to be merged with the previous one (Specially when redirect or completed)
                    elseif($key == 'details')
                    {
                        $oldDetails = array();

                        if(!is_null($currentMission[$key]))
                        {
                            $oldDetails = \Zend_Json::decode($currentMission[$key]);
                        }

                        $update[$key] = array_merge($detail, $oldDetails);
                        ksort($update[$key]);
                        $update[$key] = \Zend_Json::encode($update[$key]);
                    }
                    else
                    {
                        $update[$key] = $detail;
                    }
                }
            }

            if(count($update) > 0)
            {
                $usersMissionsModel->updateById($json['MissionID'], $update);
            }

            unset($update);
        }

        unset($usersMissionsModel);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $missionDetails                     = array();
        $details                            = array();

        $missionDetails['dateAccepted']     = $json['timestamp'];

        if(array_key_exists('Influence', $json))
        {
            $missionDetails['influence']    = $json['Influence'];
        }

        if(array_key_exists('Reputation', $json))
        {
            $missionDetails['reputation']   = $json['Reputation'];
        }

        if(array_key_exists('Reward', $json))
        {
            $missionDetails['reward']   = $json['Reward'];
        }

        if(array_key_exists('Faction', $json) && !empty($json['Faction']))
        {
            $allegiance = \Alias\System\Allegiance::getFromFd($json['Faction']);

            if(!is_null($allegiance))
            {
                $details['allegiance'] = $allegiance;
            }
            else
            {
                $factionsModel      = new \Models_Factions;
                $currentFaction     = $factionsModel->getByName($json['Faction']);

                if(!is_null($currentFaction))
                {
                    $currentFactionId = $currentFaction['id'];
                }
                else
                {
                    $currentFactionId = $factionsModel->insert(array('name' => $json['Faction']));
                }

                $missionDetails['refFaction'] = $currentFactionId;
            }
        }

        if(array_key_exists('Expiry', $json))
        {
            $missionDetails['dateExpiration'] = str_replace(array('T', 'Z'), array(' ', ''), $json['Expiry']);
        }

        // If mission have waypoints, separate the destination and make a WP array
        if(array_key_exists('DestinationSystem', $json) && stripos($json['DestinationSystem'], '$MISSIONUTIL_MULTIPLE_FINAL_SEPARATOR') !== false)
        {
            $systems = explode('$MISSIONUTIL_MULTIPLE_FINAL_SEPARATOR;', $json['DestinationSystem']);

            if(count($systems) == 2)
            {
                $json['DestinationSystem']      = $systems[1];
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

        if(array_key_exists('DestinationSystem', $json))
        {
            $systemsModel   = new \Models_Systems;
            $system         = $systemsModel->getByName($json['DestinationSystem']);

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
                        $missionDetails['refDestinationSystem'] = $currentSystem->getId();
                    }
                }
            }
        }

        if(array_key_exists('DestinationStation', $json))
        {
            // Find station from the current system
            if(array_key_exists('refDestinationSystem', $missionDetails) && !is_null($missionDetails['refDestinationSystem']))
            {
                $currentSystem = \Component\System::getInstance($missionDetails['refDestinationSystem']);
                $stations      = $currentSystem->getStations(true);

                if(!is_null($stations))
                {
                    foreach($stations AS $station)
                    {
                        $currentStation = \EDSM_System_Station::getInstance($station['id']);

                        if($currentStation->getName() == $json['DestinationStation'])
                        {
                            $missionDetails['refDestinationStation'] = $currentStation->getId();
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
                    $stationsModel->select()->where('name = ?', $json['DestinationStation'])
                );

                if(!is_null($stations) && count($stations) == 1)
                {
                    $stations = $stations->toArray();
                    $missionDetails['refDestinationStation'] = $stations[0]['id'];
                }
            }
        }

        if(array_key_exists('Commodity', $json))
        {
            $details['commodity']           = \Alias\Station\Commodity\Type::getFromFd($json['Commodity']);
        }

        if(array_key_exists('Count', $json))
        {
            $details['commodityCount']      = $json['Count'];
        }

        if(array_key_exists('Target', $json))
        {
            if(stripos(strtolower($json['Target']), '$missionutil_') !== false)
            {
                $details['target']              = \Alias\Station\Mission\Util::getFromFd($json['Target']);
            }
            else
            {
                $details['target']              = $json['Target'];
            }
        }

        if(array_key_exists('TargetType', $json))
        {
            if(stripos(strtolower($json['TargetType']), '$missionutil_') !== false)
            {
                $details['targetType']          = \Alias\Station\Mission\Util::getFromFd($json['TargetType']);
            }
            else
            {
                $details['targetType']          = $json['TargetType'];
            }
        }

        if(array_key_exists('TargetFaction', $json) && !empty($json['TargetFaction']))
        {
            $allegiance = \Alias\System\Allegiance::getFromFd($json['TargetFaction']);

            if(!is_null($allegiance))
            {
                $details['targetAllegiance'] = $allegiance;
            }
            else
            {
                $factionsModel      = new \Models_Factions;
                $currentFaction     = $factionsModel->getByName($json['TargetFaction']);

                if(!is_null($currentFaction))
                {
                    $currentFactionId = $currentFaction['id'];
                }
                else
                {
                    $currentFactionId = $factionsModel->insert(array('name' => $json['TargetFaction']));
                }

                $details['targetFaction'] = (int) $currentFactionId;
            }
        }

        if(array_key_exists('KillCount', $json))
        {
            $details['killCount']           = $json['KillCount'];
        }

        if(array_key_exists('PassengerCount', $json))
        {
            $details['passengerCount']      = $json['PassengerCount'];
        }

        if(array_key_exists('PassengerVIPs', $json))
        {
            $details['passengerVIPs']       = $json['PassengerVIPs'];
        }

        if(array_key_exists('PassengerWanted', $json))
        {
            $details['passengerWanted']     = $json['PassengerWanted'];
        }

        if(array_key_exists('PassengerType', $json))
        {
            $details['passengerType']       = \Alias\Station\Mission\Passenger\Type::getFromFd($json['PassengerType']);
        }

        if(array_key_exists('LocalisedName', $json))
        {
            $missionType    = \Alias\Station\Mission\Type::getFromFd($json['Name']);

            // Extract passenger name from "mission_sightseeing"
            if(!is_null($missionType) && (($missionType > 2000 && $missionType < 2100) || $missionType == 1710))
            {
                if(stripos($json['LocalisedName'], ' seeks sightseeing adventure') !== false)
                {
                    $details['passengerName']   = str_ireplace(' seeks sightseeing adventure', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' cherche à mêler tourisme et aventure dans le système') !== false)
                {
                    $details['passengerName']   = str_ireplace(' cherche à mêler tourisme et aventure dans le système', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' busca una aventura turística') !== false)
                {
                    $details['passengerName']   = str_ireplace(' busca una aventura turística', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' busca una aventura turística.') !== false)
                {
                    $details['passengerName']   = str_ireplace(' busca una aventura turística.', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' procura por aventuras deslumbrantes') !== false)
                {
                    $details['passengerName']   = str_ireplace(' procura por aventuras deslumbrantes', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);

                    //\Zend_Debug::dump($details['passengerName']); exit();
                }
                elseif(stripos($json['LocalisedName'], ' interessiert sich für Sehenswürdigkeiten in') !== false)
                {
                    $details['passengerName']   = str_ireplace(' interessiert sich für Sehenswürdigkeiten in', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' interessiert sich für Sehenswürdigkeiten') !== false)
                {
                    $details['passengerName']   = str_ireplace(' interessiert sich für Sehenswürdigkeiten', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], ' жаждет отдыха и приключений') !== false)
                {
                    $details['passengerName']   = str_ireplace(' жаждет отдыха и приключений', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                else
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }

                if(array_key_exists('passengerName', $details) && $missionType == 1710)
                {
                    $temp                       = explode('-', $details['passengerName']);
                    $details['passengerName']   = trim($temp[1]);
                }
            }

            // Extract passenger name from "mission_passengervip"
            if(!is_null($missionType) && (($missionType > 200 && $missionType < 300) || in_array($missionType, array(3010, 3016))))
            {
                if(stripos($json['LocalisedName'], 'transport ') !== false)
                {
                    $details['passengerName']   = str_ireplace('transport ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], 'transportez ') !== false)
                {
                    $details['passengerName']   = str_ireplace('transportez ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], 'transportar a ') !== false)
                {
                    $details['passengerName']   = str_ireplace('transportar a ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], 'transporta a ') !== false)
                {
                    $details['passengerName']   = str_ireplace('transporta a ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                elseif(stripos($json['LocalisedName'], 'transporte ') !== false)
                {
                    $details['passengerName']   = str_ireplace('transporte ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);

                    //\Zend_Debug::dump($details['passengerName']); exit();
                }
                elseif(stripos($json['LocalisedName'], 'Доставьте ') !== false)
                {
                    $details['passengerName']   = str_ireplace('Доставьте ', '', $json['LocalisedName']);
                    $details['passengerName']   = trim($details['passengerName']);
                }
                else
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }
            }

            // Extract passenger name from "mission_longdistanceexpedition"
            if(!is_null($missionType) && $missionType > 1400 && $missionType < 1500)
            {
                if(stripos($json['LocalisedName'], ' wants to go to ') !== false)
                {
                    $json['LocalisedName']      = explode(' wants to go to ', $json['LocalisedName']);
                    $details['passengerName']   = trim($json['LocalisedName'][0]);
                    $details['expeditionDest']  = trim(str_replace(' and collect data', '', $json['LocalisedName'][1]));
                }
                elseif(stripos($json['LocalisedName'], ' veut aller à ') !== false)
                {
                    $json['LocalisedName']      = explode(' veut aller à ', $json['LocalisedName']);
                    $details['passengerName']   = trim($json['LocalisedName'][0]);
                }
                elseif(stripos($json['LocalisedName'], ' хочет посетить достопримечательность ') !== false)
                {
                    $json['LocalisedName']      = explode(' хочет посетить достопримечательность ', $json['LocalisedName']);
                    $details['passengerName']   = trim($json['LocalisedName'][0]);
                }
                elseif(stripos($json['LocalisedName'], ' quer ir para ') !== false)
                {
                    $json['LocalisedName']      = explode(' quer ir para ', $json['LocalisedName']);
                    $details['passengerName']   = trim($json['LocalisedName'][0]);

                    //\Zend_Debug::dump($details['expeditionDest']);
                    //\Zend_Debug::dump($details['passengerName']); exit();
                }
                else
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }
            }

            // Extract targetStationName "mission_disable_blops"
            if(!is_null($missionType) && in_array($missionType, array(855, 856, 857)))
            {
                // Covert action against %target% at %targetStationName%
                if(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'covert action against ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Covert action against ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' at ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Action secrète contre le %target% de la colonie %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'action secrète contre ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Action secrète contre ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' de la colonie ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Скрытое действие на объекте (Поселение) в поселении %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'Скрытое действие на объекте (') !== false)
                {
                    $json['LocalisedName']      = str_replace('Скрытое действие на объекте ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(') в поселении ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Disable %target% at %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'disable ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Disable ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' at ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Desactivar %target% en %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'desactivar ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Desactivar ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' en ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Отключить объект (%target%) в поселении %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'Отключить объект (') !== false)
                {
                    $json['LocalisedName']      = str_replace('Отключить объект ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(') в поселении ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Take down %target% at %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'take down ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Take down ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' at ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Neutralizar %target% en %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'neutralizar ') !== false)
                {
                    $json['LocalisedName']      = str_replace('Neutralizar ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(' en ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                // Уничтожьте объект (Поселение) в поселении %targetStationName%
                elseif(array_key_exists('LocalisedName', $json) && stripos($json['LocalisedName'], 'Уничтожьте объект (') !== false)
                {
                    $json['LocalisedName']      = str_replace('Уничтожьте объект ', '', $json['LocalisedName']);
                    $json['LocalisedName']      = explode(') в поселении ', $json['LocalisedName']);

                    $details['targetStationName']   = trim($json['LocalisedName'][1]);
                }
                elseif(defined('JOURNAL_DEBUG'))
                {
                    \Zend_Debug::dump($json);
                    exit();
                }
                else
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }
            }

            // Extract donation from "mission_altruismcredits"
            if(!is_null($missionType) && $missionType > 450 && $missionType < 500)
            {
                if(array_key_exists('Donation', $json) && is_int($json['Donation']))
                {
                    $missionDetails['donation'] = $json['Donation'];
                }
                else
                {
                    if(stripos($json['LocalisedName'], ' cr to the cause') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('donate ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(' cr to the cause', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr to prevent a medical emergency') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('donate ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(' cr to prevent a medical emergency', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' to tackle civil unrest') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('provide ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' cr to tackle civil unrest.', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(' cr to tackle civil unrest', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' to help stop the famine') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('provide ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace('please provide ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(' cr to help stop the famine', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr à la cause') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('donnez ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' cr à la cause', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr pour régler le problème des émeutes') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('procurez-nous ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' cr pour régler le problème des émeutes', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr pour lutter contre la famine') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('s\'il vous plaît, faites un don de ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' cr pour lutter contre la famine', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr pour empêcher une situation d\'urgence médicale') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('faites un don de ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' cr pour empêcher une situation d\'urgence médicale', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' Cr a la causa') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' Cr a la causa', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Haz una donación de ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' Cr pela causa') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' Cr pela causa', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Doe ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' Cr para evitar una emergencia médica') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' Cr para evitar una emergencia médica', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Dona ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' Cr para hacer frente a los disturbios civiles') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' Cr para hacer frente a los disturbios civiles', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace('Proporciona ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('.', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' Cr para ayudar a parar la hambruna') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' Cr para ayudar a parar la hambruna', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Por favor proporciona ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' cr für die sache spenden') !== false)
                    {
                        $missionDetails['donation'] = str_ireplace(' cr für die sache spenden', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' CR, um einen medizinischen Notfall zu verhindern') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' CR, um einen medizinischen Notfall zu verhindern', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Spenden Sie ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' CR, um die Hungersnot aufzuhalten') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace(' CR, um die Hungersnot aufzuhalten', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace('Geben Sie uns bitte ', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' CR Beschaffen, um zivile Unruhen einzudämmen') !== false)
                    {
                        $missionDetails['donation'] = str_ireplace(' CR Beschaffen, um zivile Unruhen einzudämmen', '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], 'Пожертвуйте на дело следующую сумму: ') !== false)
                    {
                        $json['LocalisedName']      = str_ireplace('Пожертвуйте на дело следующую сумму: ', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_ireplace(' КР.', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_ireplace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' КР., чтобы предотвратить чрезвычайное положение') !== false)
                    {
                        $json['LocalisedName']      = str_replace(' КР., чтобы предотвратить чрезвычайное положение', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_replace('Пожертвуйте ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_replace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' КР., чтобы справиться с гражданскими беспорядками') !== false)
                    {
                        $json['LocalisedName']      = str_replace(' КР., чтобы справиться с гражданскими беспорядками.', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_replace('Предоставьте ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_replace(chr(194).chr(160), '', $json['LocalisedName']);
                    }
                    elseif(stripos($json['LocalisedName'], ' КР., чтобы помочь остановить голод') !== false)
                    {
                        $json['LocalisedName']      = str_replace(' КР., чтобы помочь остановить голод', '', $json['LocalisedName']);
                        $json['LocalisedName']      = str_replace('Пожалуйста, пожертвуйте ', '', $json['LocalisedName']);
                        $missionDetails['donation'] = str_replace(chr(194).chr(160), '', $json['LocalisedName']);

                        //\Zend_Debug::dump($missionDetails['donation']); exit();
                    }
                    else
                    {
                        static::$return['msgnum']   = 402;
                        static::$return['msg']      = 'Item unknown';

                        // Save in temp table for reparsing
                        $json['isError']            = 1;
                        \Journal\Event::run($json);
                    }

                    if(array_key_exists('donation', $missionDetails))
                    {
                        $missionDetails['donation'] = str_ireplace('.', '', $missionDetails['donation']);
                        $missionDetails['donation'] = str_ireplace(',', '', $missionDetails['donation']);
                    }
                }
            }
        }



        $stationId = static::findStationId($json);
        if(!is_null($stationId))
        {
            $details['acceptedStationId'] = $stationId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            $missionDetails['details'] = $details;
        }

        return $missionDetails;
    }
}