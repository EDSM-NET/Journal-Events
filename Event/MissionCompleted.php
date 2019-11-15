<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MissionCompleted extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Set mission status to "Completed".',
        'Add "Reward" to commander credits.',
        'Remove "Donation" from commander credits.',
        'Add commodities rewarded to the commander cargo hold.',
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

        if(array_key_exists('CommodityReward', $json))
        {
            foreach($json['CommodityReward'] AS $key => $commodityReward)
            {
                if(!is_array($commodityReward))
                {
                    $commodityReward = [
                        'Name'  => $key,
                        'Count' => $commodityReward,
                    ];
                }

                $currentCommodityId = \Alias\Station\Commodity\Type::getFromFd($commodityReward['Name']);

                if(is_null($currentCommodityId))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $commodityReward['Name']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }
            }
        }

        if(array_key_exists('MaterialsReward', $json))
        {
            foreach($json['MaterialsReward'] AS $key => $materialReward)
            {
                if(!is_array($materialReward))
                {
                    $materialReward = array('Name' => $key, 'Count' => $materialReward);
                }

                $currentMaterialId  = \Alias\Commander\Material::getFromFd($materialReward['Name']);
                $currentDataId      = \Alias\Commander\Data::getFromFd($materialReward['Name']);

                if(is_null($currentMaterialId) && is_null($currentDataId))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $materialReward['Name']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }
            }
        }

        //TODO: Check user permits
        if(array_key_exists('PermitsAwarded', $json))
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
            try
            {
                $currentMission                     = array();
                $currentMission['id']               = $json['MissionID'];
                $currentMission['refUser']          = static::$user->getId();
                $currentMission['type']             = $missionType;
                $currentMission['status']           = 'Completed';
                $currentMission['dateCompleted']    = $json['timestamp'];

                $usersMissionsModel->insert($currentMission);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this mission entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';

                    $currentMission             = $usersMissionsModel->getById($json['MissionID']);
                }
                else
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();

                    $registry = \Zend_Registry::getInstance();

                    if($registry->offsetExists('sentryClient'))
                    {
                        $sentryClient = $registry->offsetGet('sentryClient');
                        $sentryClient->captureException($e);
                    }
                }

                return static::$return;
            }
        }

        $update         = array();
        $missionDetails = static::generateDetails($json);

        foreach($missionDetails AS $key => $detail)
        {
            // Modified detail
            if(!array_key_exists($key, $currentMission) || $currentMission[$key] != $detail)
            {
                if($key == 'details')
                {
                    $oldDetails = array();

                    if(array_key_exists($key, $currentMission) && !is_null($currentMission[$key]))
                    {
                        $oldDetails = \Zend_Json::decode($currentMission[$key]);
                    }

                    $update[$key] = array_merge($oldDetails, $detail);
                    ksort($update[$key]);
                    $update[$key] = \Zend_Json::encode($update[$key]);
                }
                else
                {
                    $update[$key] = $detail;
                }
            }
        }

        if($currentMission['type'] != $missionType)
        {
            $update['type'] = $missionType;
        }

        if(count($update) > 0)
        {
            $usersMissionsModel->updateById($json['MissionID'], $update);
        }

        unset($usersMissionsModel, $update);

        // Give reward to the commander
        if(array_key_exists('Reward', $json)/* && $json['Reward'] > 0 */) // Also register 0 reward for API
        {
            static::handleCredits(
                'MissionCompleted',
                (int) $json['Reward'],
                static::generateRewardDonationDetails($json),
                $json
            );
        }

        // Remove donation from commander
        if(array_key_exists('Donation', $json)/* && $json['Donation'] > 0 */) // Also register 0 reward for API
        {
            static::handleCredits(
                'MissionCompleted',
                - (int) $json['Donation'],
                static::generateRewardDonationDetails($json),
                $json
            );
        }

        // Attribute commodity reward to cargo hold
        if(array_key_exists('CommodityReward', $json))
        {
            $databaseModel  = new \Models_Users_Cargo;
            $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

            foreach($json['CommodityReward'] AS $key => $commodityReward)
            {
                if(!is_array($commodityReward))
                {
                    $commodityReward = array('Name' => $key, 'Count' => $commodityReward);
                }

                $currentCommodityId = \Alias\Station\Commodity\Type::getFromFd($commodityReward['Name']);
                $currentItem        = null;

                if(!is_null($currentItems) && !is_null($currentCommodityId))
                {
                    foreach($currentItems AS $tempItem)
                    {
                        if($tempItem['type'] == $currentCommodityId)
                        {
                            $currentItem = $tempItem;
                            break;
                        }
                    }
                }

                // If we have the line, update else insert the Count quantity
                if(!is_null($currentItem))
                {
                    if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                    {
                        $update                 = array();
                        $update['total']        = $currentItem['total'] + $commodityReward['Count'];
                        $update['lastUpdate']   = $json['timestamp'];

                        $databaseModel->updateById($currentItem['id'], $update);

                        unset($update);
                    }
                }
                else
                {
                    $insert                 = array();
                    $insert['refUser']      = static::$user->getId();
                    $insert['type']         = $currentCommodityId;
                    $insert['total']        = $commodityReward['Count'];
                    $insert['lastUpdate']   = $json['timestamp'];

                    $databaseModel->insert($insert);

                    unset($insert);
                }
            }

            unset($databaseModel);
        }

        // Attribute material reward to cargo hold
        if(array_key_exists('MaterialsReward', $json))
        {
            $aliasClasses   = [
                'materials'     => 'Alias\Commander\Material',
                'data'          => 'Alias\Commander\Data',
            ];

            $databaseModels = [
                'materials'     => new \Models_Users_Materials,
                'data'          => new \Models_Users_Data,
            ];

            $currentItems   = [
                'materials'     => $databaseModels['materials']->getByRefUser(static::$user->getId()),
                'data'          => $databaseModels['data']->getByRefUser(static::$user->getId()),
            ];

            foreach($json['MaterialsReward'] AS $key => $materialReward)
            {
                if(!is_array($materialReward))
                {
                    $materialReward = array('Name' => $key, 'Count' => $materialReward);
                }

                if(array_key_exists('Name', $materialReward) && !empty($materialReward['Name']))
                {
                    $aliasClass = $aliasType = $currentItemId = null;

                    foreach($aliasClasses AS $type => $class)
                    {
                        // Check if type is known in EDSM
                        $currentItemId = $class::getFromFd($materialReward['Name']);

                        if(!is_null($currentItemId))
                        {
                            $aliasType  = $type;
                            $aliasClass = $class;

                            break;
                        }
                    }

                    if(!is_null($currentItemId) && !is_null($aliasType) && !is_null($aliasClass))
                    {
                        // Find the current item ID
                        $currentItem = null;

                        if(!is_null($currentItems[$aliasType]))
                        {
                            foreach($currentItems[$aliasType] AS $tempItem)
                            {
                                if($tempItem['type'] == $currentItemId)
                                {
                                    $currentItem = $tempItem;
                                    break;
                                }
                            }
                        }

                        // If we have the line, update else insert the Count quantity
                        if(!is_null($currentItem))
                        {
                            if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                            {
                                $update                 = array();
                                $update['total']        = $currentItem['total'] + $materialReward['Count'];
                                $update['lastUpdate']   = $json['timestamp'];

                                $databaseModels[$aliasType]->updateById($currentItem['id'], $update);

                                unset($update);
                            }
                        }
                        else
                        {
                            $insert                 = array();
                            $insert['refUser']      = static::$user->getId();
                            $insert['type']         = $currentItemId;
                            $insert['total']        = $materialReward['Count'];
                            $insert['lastUpdate']   = $json['timestamp'];

                            $databaseModels[$aliasType]->insert($insert);

                            unset($insert);
                        }
                    }
                }
            }

            unset($databaseModels, $currentItems);
        }

        // Remove commodity from cargo hold
        if(array_key_exists('Commodity', $json))
        {
            $databaseModel  = new \Models_Users_Cargo;
            $currentItems   = $databaseModel->getByRefUser(static::$user->getId());

            $currentCommodityId = \Alias\Station\Commodity\Type::getFromFd($json['Commodity']);
            $currentItem        = null;

            if(!is_null($currentItems) && !is_null($currentCommodityId))
            {
                foreach($currentItems AS $tempItem)
                {
                    if($tempItem['type'] == $currentCommodityId)
                    {
                        $currentItem = $tempItem;
                        break;
                    }
                }
            }

            // If we have the line, update
            if(!is_null($currentItem))
            {
                if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                {
                    $update                 = array();
                    $update['total']        = max(0, $currentItem['total'] - $json['Count']);
                    $update['lastUpdate']   = $json['timestamp'];

                    $databaseModel->updateById($currentItem['id'], $update);

                    unset($update);
                }
            }

            unset($databaseModel, $currentItems);
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $missionDetails                     = array();
        $details                            = array();

        $missionDetails['status']           = 'Completed';
        $missionDetails['dateCompleted']    = $json['timestamp'];

        if(array_key_exists('Reward', $json))
        {
            $missionDetails['reward']   = $json['Reward'];
        }

        if(array_key_exists('Donation', $json))
        {
            $missionDetails['donation']   = $json['Donation'];
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
            // Find station from the current systeù
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

        if(array_key_exists('CommodityReward', $json))
        {
            $details['commodityReward'] = array();

            foreach($json['CommodityReward'] AS $key => $commodityReward)
            {
                if(!is_array($commodityReward))
                {
                    $commodityReward = array('Name' => $key, 'Count' => $commodityReward);
                }

                $details['commodityReward'][] = array(
                    'id'    => \Alias\Station\Commodity\Type::getFromFd($commodityReward['Name']),
                    'qty'   => $commodityReward['Count'],
                );
            }
        }

        if(array_key_exists('MaterialsReward', $json))
        {
            $details['materialsReward'] = array();
            $details['dataReward']      = array();

            foreach($json['MaterialsReward'] AS $key => $materialReward)
            {
                if(!is_array($materialReward))
                {
                    $materialReward = array('Name' => $key, 'Count' => $materialReward);
                }

                $aliasClasses   = array(
                    'materialsReward'   => 'Alias\Commander\Material',
                    'dataReward'        => 'Alias\Commander\Data',
                );

                foreach($aliasClasses AS $type => $class)
                {
                    // Check if type is known in EDSM
                    $currentItemId = $class::getFromFd($materialReward['Name']);

                    if(!is_null($currentItemId))
                    {
                        $details[$type][] = array(
                            'id'    => $currentItemId,
                            'qty'   => $materialReward['Count'],
                        );
                    }
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

        if(array_key_exists('FactionEffects', $json))
        {
            $factionsModel              = new \Models_Factions;
            $details['factionEffects']  = array();

            foreach($json['FactionEffects'] AS $factionEffect)
            {
                $tmp = array();

                if(array_key_exists('Faction', $factionEffect) && !empty($factionEffect['Faction']))
                {
                    $allegiance = \Alias\System\Allegiance::getFromFd($factionEffect['Faction']);

                    if(is_null($allegiance))
                    {
                        $currentFaction     = $factionsModel->getByName($factionEffect['Faction']);

                        if(!is_null($currentFaction))
                        {
                            $tmp['factionId'] = $currentFaction['id'];

                            if(array_key_exists('Effects', $factionEffect))
                            {
                                $tmp['effects'] = array();

                                foreach($factionEffect['Effects'] AS $currentEffect)
                                {
                                    $tmpEffect              = array();

                                    if(array_key_exists('Effect', $currentEffect))
                                    {
                                        $effectId               = \Alias\Station\Mission\Effect::getFromFd($currentEffect['Effect']);

                                        if(!is_null($effectId))
                                        {
                                            $tmpEffect['effectId'] = $effectId;
                                        }
                                        else
                                        {
                                            $tmpEffect['effectName'] = $currentEffect['Effect'];
                                            \EDSM_Api_Logger_Alias::log('\Alias\Station\Mission\Effect: ' . $currentEffect['Effect']);
                                        }

                                        if(array_key_exists('Trend', $currentEffect))
                                        {
                                            $tmpEffect['trend']  = $currentEffect['Trend'];
                                        }

                                        $tmp['effects'][]       = $tmpEffect;
                                    }
                                }
                            }

                            if(array_key_exists('Influence', $factionEffect))
                            {
                                $tmp['influence'] = array();

                                foreach($factionEffect['Influence'] AS $currentInfluence)
                                {
                                    $systemId = static::findSystemId($currentInfluence);

                                    if(!is_null($systemId))
                                    {
                                        $tmpInfluence               = array();
                                        $tmpInfluence['systemId']   = $systemId;

                                        if(array_key_exists('Trend', $currentInfluence))
                                        {
                                            $tmpInfluence['trend']  = $currentInfluence['Trend'];
                                        }
                                        if(array_key_exists('Influence', $currentInfluence))
                                        {
                                            $tmpInfluence['influence']  = $currentInfluence['Influence'];
                                        }

                                        $tmp['influence'][]         = $tmpInfluence;
                                    }
                                }
                            }

                            if(array_key_exists('Reputation', $factionEffect))
                            {
                                $tmp['reputation']  = $factionEffect['Reputation'];
                            }
                            if(array_key_exists('ReputationTrend', $factionEffect))
                            {
                                $tmp['reputationTrend']  = $factionEffect['ReputationTrend'];
                            }

                            $details['factionEffects'][] = $tmp;
                        }

                        unset($currentFaction, $currentFactionId);
                    }
                }
            }
        }

        if(count($details) > 0)
        {
            ksort($details);
            $missionDetails['details'] = $details;
        }

        return $missionDetails;
    }

    static private function generateRewardDonationDetails($json)
    {
        $details                = array();
        $details['missionId']   = $json['MissionID'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}