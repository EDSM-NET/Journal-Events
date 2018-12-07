<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal;

class Event
{
    protected static $isOK          = false;

    // Default game state
    protected static $gameState     = [
        'systemName'                    => null,
        'systemCoordinates'             => null,
        'stationName'                   => null,
        'shipId'                        => null,

        'isGuestCrew'                   => false,
    ];

    // Default return message
    protected static $return        = [
        'msgnum'    => 100,
        'msg'       => 'OK',
    ];

    // Default description
    protected static $description   = '<ul>
                                           <li>Stored in a temporary table.</li>
                                       </ul>';

    // Exclude ships that don''t belong to main list
    protected static $notShipTypes  = [
        'testbuggy',
        'empire_fighter',
        'federation_fighter',
        'independent_fighter',
        'gdn_hybrid_fighter_v1',
        'gdn_hybrid_fighter_v2',
        'gdn_hybrid_fighter_v3',
    ];

    // Exclude some outfitting that don't belong in main list
    protected static $excludedOutfitting = [
        '$enginecustomisation_blue_name;',

        '$modularcargobaydoor_name;',
        '$modularcargobaydoorfdl_name;',

        '$decal_powerplay_aislingduval_name;',

        '$paintjob_testbuggy_luminous_purple_name;',
        '$paintjob_testbuggy_tactical_grey_name;',
        '$paintjob_testbuggy_vibrant_red_name;',

        '$paintjob_federation_fighter_gladiator_blueblack_name;',

        '$adder_cockpit_name;',
        '$anaconda_cockpit_name;',
        '$asp_cockpit_name;',
        '$asp_scout_cockpit_name;',
        '$belugaliner_cockpit_name;',
        '$cobramkiii_cockpit_name;',
        '$cutter_cockpit_name;',
        '$diamondbackxl_cockpit_name;',
        '$dolphin_cockpit_name;',
        '$eagle_cockpit_name;',
        '$empire_courier_cockpit_name;',
        '$empire_trader_cockpit_name;',
        '$empire_eagle_cockpit_name;',
        '$federation_corvette_cockpit_name;',
        '$federation_dropship_cockpit_name;',
        '$federation_dropship_mkii_cockpit_name;',
        '$federation_gunship_cockpit_name;',
        '$ferdelance_cockpit_name;',
        '$independant_trader_cockpit_name;',
        '$krait_mkii_cockpit_name;',
        '$python_cockpit_name;',
        '$sidewinder_cockpit_name;',
        '$type6_cockpit_name;',
        '$type9_cockpit_name;',
        '$type7_cockpit_name;',
        '$type9_military_cockpit_name;',
        '$typex_cockpit_name;',
        '$viper_cockpit_name;',
        '$viper_mkiv_cockpit_name;',
        '$vulture_cockpit_name;',
    ];

    // Store current user/software
    protected static $user          = null;
    protected static $softwareId    = null;



    // Default method for unknown events
    public static function run($json)
    {
        if(!is_null(static::$user) && !is_null(static::$softwareId))
        {
            try
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refSoftware']  = static::$softwareId;
                $insert['event']        = $json['event'];
                $insert['gameState']    = \Zend_Json::encode(static::$gameState);
                $insert['dateEvent']    = $json['timestamp'];

                // We do not need event and timestamps in the final JSON
                unset($json['event'], $json['timestamp']);

                // If the message came from an erroneous events, store it for further processing
                if(array_key_exists('isError', $json))
                {
                    $insert['isError'] = 1;
                    unset($json['isError']);
                }

                $insert['message']      = \Zend_Json::encode($json);

                $journalModel = new \Models_Journal;
                $journalModel->insert($insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                if(static::$return['msgnum'] != 402)
                {
                    // Based on unique index, this journal entry was already saved.
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        // If it's an error, update the already existing journal
                        // Throw a 500 to avoid our reparser deleting the message yet
                        if(array_key_exists('isError', $insert) && $insert['isError'] == 1)
                        {
                            static::$return['msgnum']   = 500;
                            static::$return['msg']      = 'Message needs to be checked';
                        }
                        else
                        {
                            static::$return['msgnum']   = 101;
                            static::$return['msg']      = 'Message already stored';
                        }
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
                }
            }
        }

        return static::$return;
    }



    /**
     * Init methods
     */
    public static function setUser($user)
    {
        if($user instanceof \Component\User)
        {
            static::$user = $user;
        }
    }

    public static function setSoftware($softwareId)
    {
        static::$softwareId = $softwareId;
    }

    public static function setGameState($gameState)
    {
        static::$gameState = $gameState;
    }



    public static function getDescription($raw = false)
    {
        if($raw === false)
        {
            if(is_array(static::$description))
            {
                return '<ul><li>' . implode('</li></li>', static::$description) . '</li></ul>';
            }
        }

        return static::$description;
    }

    public static function isOK()
    {
        return static::$isOK;
    }

    public static function resetReturnMessage()
    {
        static::$return['msgnum']   = 100;
        static::$return['msg']      = 'OK';
    }



    /**
     * COMMON METHODS
     */
    protected static function updateCurrentGameShipId($newShipId, $newShipTimestamp)
    {
        $currentShipId          = static::$user->getCurrentGameShipId();
        $lastCurrentShipUpdate  = static::$user->getCurrentGameShipIdLastUpdate();

        if($currentShipId != $newShipId || is_null($lastCurrentShipUpdate))
        {
            // If newer or null, update the ship ID
            if(is_null($lastCurrentShipUpdate) || strtotime($lastCurrentShipUpdate) < strtotime($newShipTimestamp))
            {
                if($newShipId == 4294967295)
                {
                    $newShipId = new \Zend_Db_Expr('NULL');
                }
                else
                {
                    $newShipId = (int) $newShipId;
                }

                $usersModel = new \Models_Users;
                $usersModel->updateById(
                    static::$user->getId(),
                    [
                        'currentShipId'         => $newShipId,
                        'lastCurrentShipUpdate' => $newShipTimestamp,
                    ]
                );
            }
        }
    }

    /**
     * EVENTS DETAILS
     */
    protected static function findSystemId($json, $preventRenamedSystems = false)
    {
        $systemName         = null;
        $systemCoordinates  = null;

        // If event contains SystemAddress
        if(array_key_exists('SystemAddress', $json))
        {
            $systemsModel   = new \Models_Systems;
            $system         = $systemsModel->getById64($json['SystemAddress']);

            if(!is_null($system))
            {
                return $system['id'];
            }
        }

        // If transient state, take the transient state
        if(array_key_exists('_systemAddress', $json) && !is_null($json['_systemAddress']) && !empty($json['_systemAddress']))
        {
            $systemsModel   = new \Models_Systems;
            $system         = $systemsModel->getById64($json['_systemAddress']);

            if(!is_null($system))
            {
                return $system['id'];
            }
        }

        // Some events have the StarSystem
        if(in_array($json['event'], ['Docked', 'Location', 'Scan']) && array_key_exists('StarSystem', $json))
        {
            $systemName = $json['StarSystem'];

            if(array_key_exists('StarPos', $json)) // Not in Docked
            {
                $systemCoordinates = $json['StarPos'];
            }
        }
        // If transient state, take the transient state
        elseif(array_key_exists('_systemName', $json) && !is_null($json['_systemName']))
        {
            $systemName = $json['_systemName'];

            if(array_key_exists('_systemCoordinates', $json))
            {
                $systemCoordinates = $json['_systemCoordinates'];
            }
        }
        // If multiple event have fed the transient state
        elseif(!is_null(static::$gameState['systemName']))
        {
            $systemName = static::$gameState['systemName'];

            if(!is_null(static::$gameState['systemCoordinates']))
            {
                $systemCoordinates = static::$gameState['systemCoordinates'];
            }
        }

        // We have a system name, proceed
        if(!is_null($systemName))
        {
            // Convert coordinates to EDSM format
            if(!is_null($systemCoordinates))
            {
                if($systemCoordinates[0] == 'NaN' || is_nan((float) $systemCoordinates[0]))
                {
                    // Reset old converted netLog
                    $systemCoordinates = null;
                }
                else
                {
                    $systemCoordinates = array(
                        'x'  => round($systemCoordinates[0] * 32),
                        'y'  => round($systemCoordinates[1] * 32),
                        'z'  => round($systemCoordinates[2] * 32),
                    );
                }
            }

            $systemName     = trim($systemName);

            $systemsModel   = new \Models_Systems;
            $system         = $systemsModel->getByName($systemName);

            // System creation
            if(is_null($system))
            {
                $insertSystem           = array();
                $insertSystem['name']   = $systemName;

                if(!is_null($systemCoordinates))
                {
                    $insertSystem = array_merge($insertSystem, $systemCoordinates);
                }
            }

            if(!is_null($system))
            {
                $currentSystem = \Component\System::getInstance($system['id']);

                // Check system renamed/merged to another
                if($currentSystem->isHidden() === true)
                {
                    $mergedTo = $currentSystem->getMergedTo();

                    if(!is_null($mergedTo) && $preventRenamedSystems === false)
                    {
                        // Switch systems when they have been renamed
                        $currentSystem = \Component\System::getInstance($mergedTo);
                    }
                    else
                    {
                        return null;
                    }
                }

                // Check if system have duplicates
                $duplicates = $currentSystem->getDuplicates();

                if(!is_null($duplicates) && is_array($duplicates) && count($duplicates) > 0)
                {
                    // We don't have coordinates to check duplicates...
                    if(is_null($systemCoordinates))
                    {
                        return null;
                    }
                    else
                    {
                        if($systemCoordinates['x'] != $currentSystem->getX() || $systemCoordinates['y'] != $currentSystem->getY() || $systemCoordinates['z'] != $currentSystem->getZ())
                        {
                            foreach($duplicates AS $duplicate)
                            {
                                $duplicateSystem = \Component\System::getInstance($duplicate);

                                if($systemCoordinates['x'] == $duplicateSystem->getX() && $systemCoordinates['y'] == $duplicateSystem->getY() && $systemCoordinates['z'] == $duplicateSystem->getZ())
                                {
                                    if(array_key_exists('SystemAddress', $json) && !is_null($duplicateSystem->getId64()))
                                    {
                                        $systemsModel   = new \Models_Systems;
                                        $systemsModel->updateById(
                                            $duplicateSystem->getId(),
                                            [
                                                'id64' => $json['SystemAddress'],
                                            ]
                                        );
                                    }

                                    return $duplicateSystem->getId();
                                }

                                unset($duplicateSystem);
                            }
                        }
                        else
                        {
                            if(array_key_exists('SystemAddress', $json) && !is_null($currentSystem->getId64()))
                            {
                                $systemsModel   = new \Models_Systems;
                                $systemsModel->updateById(
                                    $currentSystem->getId(),
                                    [
                                        'id64' => $json['SystemAddress'],
                                    ]
                                );
                            }

                            return $currentSystem->getId();
                        }
                    }
                }
                else
                {
                    if(array_key_exists('SystemAddress', $json) && !is_null($currentSystem->getId64()))
                    {
                        $systemsModel   = new \Models_Systems;
                        $systemsModel->updateById(
                            $currentSystem->getId(),
                            [
                                'id64' => $json['SystemAddress'],
                            ]
                        );
                    }

                    return $currentSystem->getId();
                }
            }
        }

        // If timestamp is after last location

        return null;
    }

    protected static function findStationId($json)
    {
        $systemId    = null;
        $stationName = null;

        // If event contain MarketID
        if(array_key_exists('MarketID', $json))
        {
            $stationsModel  = new \Models_Stations;
            $station        = $stationsModel->getByMarketId($json['MarketID']);

            if(!is_null($station))
            {
                return $station['id'];
            }
        }

        // If transient state, take the transient state
        if(array_key_exists('_marketId', $json) && !is_null($json['_marketId']) && !empty($json['_marketId']))
        {
            $stationsModel  = new \Models_Stations;
            $station        = $stationsModel->getByMarketId($json['_marketId']);

            if(!is_null($station))
            {
                return $station['id'];
            }
        }

        // Some events have the StationName
        if(in_array($json['event'], ['Docked', 'Location']) && array_key_exists('StationName', $json))
        {
            $stationName = $json['StationName'];
        }
        // If transient state, take the transient state
        elseif(array_key_exists('_stationName', $json) && !is_null($json['_stationName']))
        {
            $stationName = $json['_stationName'];
        }
        // If multiple event have fed the transient state
        elseif(!is_null(static::$gameState['stationName']))
        {
            $stationName = static::$gameState['stationName'];
        }

        if(!is_null($stationName))
        {
             $systemId = static::findSystemId($json);

            if(!is_null($systemId))
            {
                $currentSystem  = \Component\System::getInstance($systemId);
                $stations       = $currentSystem->getStations();

                foreach($stations AS $station)
                {
                    $currentStation = \EDSM_System_Station::getInstance($station['id']);

                    if($currentStation->getName() == $stationName)
                    {
                        if(array_key_exists('MarketID', $json) && is_null($currentStation->getMarketId()))
                        {
                            $stationsModel = new \Models_Stations;
                            $stationsModel->updateById(
                                $currentStation->getId(),
                                [
                                    'marketId' => $json['MarketID'],
                                ]
                            );
                        }

                        return $currentStation->getId();
                    }
                }
            }
            else
            {
                // Find if it's a unique station
                $stationsModel = new \Models_Stations;
                $stations      = $stationsModel->fetchAll(
                    $stationsModel->select()->where('name = ?', $stationName)
                );

                if(!is_null($stations) && count($stations) == 1)
                {
                    $stations = $stations->toArray();
                    return $stations[0]['id'];
                }
            }
        }

        //TODO: If timestamp is after last docking, take the docked station


        return null;
    }

    protected static function findShipId($json)
    {
        // If transient state, take the transient state
        if(array_key_exists('_shipId', $json) && !is_null($json['_shipId']))
        {
            if((int) $json['_shipId'] >= 0)
            {
                return $json['_shipId'];
            }
        }

        // If multiple event have fed the transient state
        if(!is_null(static::$gameState['shipId']))
        {
            return static::$gameState['shipId'];
        }

        return null;
    }
}