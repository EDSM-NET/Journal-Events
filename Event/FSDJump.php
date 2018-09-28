<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSDJump extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set commander position in flight logs.',
        'Update ship current system.',
        'Remove fuel from ship.',
    ];
    
    
    
    public static function run($json)
    {
        if(static::$gameState['isGuestCrew'] === true)
        {
            static::$return['msgnum']   = 104;
            static::$return['msg']      = 'Crew session';
            
            return static::$return;
        }
        
        $currentSystem      = null;
        $systemId64         = null;
        $systemFoundById64  = false;
        $systemName         = trim($json['StarSystem']);
        
        if(array_key_exists('StarPos', $json))
        {
            $systemCoordinates  = $json['StarPos'];
        }
        else
        {
            $systemCoordinates  = null;
        }
        
        // Convert coordinates to EDSM format
        if(!is_null($systemCoordinates))
        {
            $systemCoordinates  = array(
                'x'  => round($systemCoordinates[0] * 32),
                'y'  => round($systemCoordinates[1] * 32),
                'z'  => round($systemCoordinates[2] * 32),
            );
        }
        
        // Find the best system according to the event
        if(!is_null($systemName) && !empty($systemName))
        {
            $systemsModel   = new \Models_Systems;
            
            if(array_key_exists('SystemAddress', $json))
            {
                $systemId64 = $json['SystemAddress'];
                $system     = $systemsModel->getById64($systemId64);
                
                if(is_null($system))
                {
                    $system = $systemsModel->getByName($systemName);
                }
                else
                {
                    $systemFoundById64 = true;
                }
            }
            else
            {
                $system = $systemsModel->getByName($systemName);
            }
            
            // System creation
            if(is_null($system))
            {
                $systemId               = null;
                $insertSystem           = array();
                $insertSystem['name']   = $systemName;
                
                if(!is_null($systemId64))
                {
                    $insertSystem['id64'] = $systemId64;
                }
                if(!is_null($systemCoordinates))
                {
                   $insertSystem = array_merge($insertSystem, $systemCoordinates);
                }
                
                try
                {
                    $systemId                           = $systemsModel->insert($insertSystem);
                    static::$return['systemCreated']    = true;
                }
                catch(\Zend_Db_Exception $e)
                {
                    $systemId       = null;
                    $system         = $systemsModel->getByName($systemName);
                    
                    if(!is_null($system))
                    {
                        $systemId                           = $system['id'];
                        static::$return['systemCreated']    = false;
                    }
                    else
                    {
                        static::$return['msgnum']   = 500;
                        static::$return['msg']      = 'Exception: ' . $e->getMessage();
                        
                        return static::$return;
                    }
                }
                
                unset($insertSystem);
                
                if(!is_null($systemId))
                {
                    $currentSystem = \EDSM_System::getInstance($systemId);
                }
            }
            // System already exists
            else
            {
                $currentSystem = \EDSM_System::getInstance($system['id']);
                
                // Check system renamed/merged to another
                if($currentSystem->isHidden() === true)
                {
                    $mergedTo = $currentSystem->getMergedTo();
                    
                    if(!is_null($mergedTo))
                    {
                        // Switch systems when they have been renamed
                        $currentSystem = \EDSM_System::getInstance($mergedTo);
                    }
                    else
                    {
                        static::$return['msgnum']   = 451;
                        static::$return['msg']      = 'System probably non existant';
                        
                        return static::$return;
                    }
                }
                
                if(!is_null($systemCoordinates))
                {
                    // Check if system have duplicates if the provided coordinates doesn't match
                    if($systemCoordinates['x'] != $currentSystem->getX() || $systemCoordinates['y'] != $currentSystem->getY() || $systemCoordinates['z'] != $currentSystem->getZ())
                    {
                        $createDuplicate    = true;
                        $minDistance        = 9999999999999999999999999999999;
                        $duplicates         = $currentSystem->getDuplicates();
                        
                        if(!is_null($systemId64) && $systemFoundById64 === true)
                        {
                            $createDuplicate = false;
                        }
                        elseif($currentSystem->isCoordinatesLocked() === false)
                        {
                            $createDuplicate = false;
                        }
                        else
                        {
                            if(!is_null($currentSystem->getX()))
                            {
                                $minDistance = min(
                                    $minDistance,
                                    \EDSM_System_Distances::calculate($currentSystem, $systemCoordinates)
                                );
                            }
                        }
                        
                        // Check if a duplicate block the creation
                        if(!is_null($duplicates) && is_array($duplicates) && count($duplicates) > 0 && $systemFoundById64 === false)
                        {
                            foreach($duplicates AS $duplicate)
                            {
                                $currentSystemTest  = \EDSM_System::getInstance($duplicate);
                                
                                // Try to follow hidden system
                                $mergedTo = $currentSystemTest->getMergedTo();
                                if($currentSystemTest->isHidden() === true && !is_null($mergedTo))
                                {
                                    $currentSystemTest = \EDSM_System::getInstance($mergedTo);
                                }
                                
                                // We do not want to create a new duplicate if one of them is not green
                                if($currentSystemTest->isCoordinatesLocked() === false)
                                {
                                    $createDuplicate = false;
                                }
                                
                                // If coordinates are the same, then swith to that duplicate!
                                if($systemCoordinates['x'] == $currentSystemTest->getX() && $systemCoordinates['y'] == $currentSystemTest->getY() && $systemCoordinates['z'] == $currentSystemTest->getZ())
                                {
                                    $createDuplicate    = false;
                                    $currentSystem      = $currentSystemTest;
                                    
                                    unset($currentSystemTest);
                                    break;
                                }
                                
                                // If we still ok to create a duplicate, test the minimum distance
                                if($createDuplicate === true)
                                {
                                    if(!is_null($currentSystemTest->getX()))
                                    {
                                        $minDistance = min(
                                            $minDistance,
                                            \EDSM_System_Distances::calculate($currentSystemTest, $systemCoordinates)
                                        );
                                    }
                                }
                            }
                        }
                        
                        // All systems are green, and distance is fine, create a new duplicate
                        if($createDuplicate === true && $minDistance > 20 && $minDistance < 999999999999999999999999999999)
                        {
                            // Duplicate the system
                            $oldSystem = $currentSystem;
                            
                            try
                            {
                                $insertSystem           = array_merge(
                                    array(
                                        'name'                  => $currentSystem->getName(),
                                        'coordinatesLocked'     => 0,
                                    ), 
                                    $systemCoordinates
                                );
                                
                                if(!is_null($systemId64))
                                {
                                    $insertSystem['id64'] = $systemId64;
                                }
                                
                                $newId                  = $systemsModel->insert($insertSystem);
                                
                                // Switch current system
                                $currentSystem                      = \EDSM_System::getInstance($newId);
                                static::$return['systemCreated']    = true;
                            }
                            catch(\Zend_Db_Exception $e)
                            {
                                // If something goes wrong, let's get back to the original system
                                $currentSystem                      = $oldSystem;
                                static::$return['systemCreated']    = false;
                                unset($newId);
                            }
                            
                            // If the creation worked, insert the duplicate warning
                            if(static::$return['systemCreated'] === true && isset($newId))
                            {
                                $systemsDuplicatesModel = new \Models_Systems_Duplicates;
                                $systemsDuplicatesModel->insertSystems($currentSystem->getId(), $newId);
                                unset($systemsDuplicatesModel);
                            }
                            
                            unset($oldSystem);
                        }
                        elseif(!is_null($systemId64) && $systemFoundById64 === false)
                        {
                            if(is_null($currentSystem->getId64()))
                            {
                                $systemsModel->updateById(
                                    $currentSystem->getId(),
                                    [
                                        'id64' => $systemId64,
                                    ]
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // Handle coordinates locking and temp coordinates
        if(!is_null($systemCoordinates) && !is_null($currentSystem))
        {
            $systemsCoordinatesTempModel    = new \Models_Systems_CoordinatesTemp;
            
            if($systemCoordinates['x'] == $currentSystem->getX() && $systemCoordinates['y'] == $currentSystem->getY() && $systemCoordinates['z'] == $currentSystem->getZ())
            {
                // All coordinates match, should we lock them?
                if($currentSystem->isCoordinatesLocked() === false)
                {
                    $lockCoordinates    = false;
                    $tempCoordinates    = $systemsCoordinatesTempModel->getByRefSystem($currentSystem->getId());
                    $tempUsers          = array();
                    
                    // Check against the temp coordinates
                    if(!is_null($tempCoordinates) && count($tempCoordinates) > 0)
                    {
                        $lockCoordinates = true;
                        
                        foreach($tempCoordinates AS $temp)
                        {
                            // Skip locking if temp coordinates are wrong
                            if($temp['x'] != $currentSystem->getX() || $temp['y'] != $currentSystem->getY() || $temp['z'] != $currentSystem->getZ())
                            {
                                $lockCoordinates = false;
                                break;
                            }
                            else
                            {
                                // Fill temp users if not EDDN nor the current user
                                if(!in_array($temp['refUser'], array(3, static::$user->getId())))
                                {
                                    $tempUsers[] = $temp['refUser'];
                                }
                            }
                        }
                        
                        // If no other users submitted correct coordinates, do not lock
                        if($lockCoordinates === true && count($tempUsers) == 0)
                        {
                            $lockCoordinates = false;
                        }
                        
                        unset($tempCoordinates, $tempUsers);
                    }
                    else
                    {
                        // No temporary coordinates, check if was trilaterared by EDSM and if all distance are good.
                        $distancesModel = new \Models_Distances;
                        $distances      = $distancesModel->getByRefSystem($currentSystem->getId());
                        
                        if(is_array($distances) && count($distances) > 0)
                        {
                            $goodDistances  = 0;
                            $badDistances   = 0;
                            
                            foreach($distances AS $distance)
                            {
                                if($distance['ref_system1'] == $currentSystem->getId())
                                {
                                    $referenceSystem = \EDSM_System::getInstance($distance['ref_system2']);
                                }
                                else
                                {
                                    $referenceSystem = \EDSM_System::getInstance($distance['ref_system1']);
                                }
                                
                                if(!is_null($referenceSystem->getX()))
                                {
                                    $calculatedDistance = round(
                                        \EDSM_System_Distances::calculate($referenceSystem, $systemCoordinates) 
                                        * 100
                                    );
                                    
                                    if($calculatedDistance == $distance['distance'])
                                    {
                                        $goodDistances++;
                                    }
                                    else
                                    {
                                        $badDistances++;
                                    }
                                }
                            }
                            
                            if($goodDistances >= 3 && $goodDistances > ($badDistances * 2))
                            {
                                $lockCoordinates = true;
                            }
                        }
                    }
                    
                    if($lockCoordinates === true)
                    {
                        $systemsModel->updateById(
                            $currentSystem->getId(),
                            array(
                                'coordinatesLocked' => 1,
                                'lastTrilateration' => new \Zend_Db_Expr('NULL'),
                            )
                        );
                        
                        $systemsCoordinatesTempModel->deleteByRefSystem($currentSystem->getId());
                    }
                    else
                    {
                        // Store temporary coordinates
                        $tempCoordinatesId = $systemsCoordinatesTempModel->insert(array_merge(
                            array(
                                'refSystem'         => $currentSystem->getId(),
                                'refUser'           => static::$user->getId(),
                                'refSoftware'       => static::$softwareId,
                                'dateSubmission'    => new \Zend_Db_Expr('NOW()'),
                            ), 
                            $systemCoordinates
                        ));
                    }
                }
            }
            else
            {
                // The current system doesn't have coordinates yet, store them!
                if(is_null($currentSystem->getX()))
                {
                    $systemsModel->updateById(
                        $currentSystem->getId(),
                        array_merge(
                            array(
                                'coordinatesLocked' => 0,
                                'lastTrilateration' => new \Zend_Db_Expr('NULL'),
                            ), 
                            $systemCoordinates
                        )
                    );
                    
                    $systemsFeaturedModel = new \Models_Systems_Featured;
                    $systemsFeaturedModel->deleteByRefSystem($currentSystem->getId());
                }
                
                // Store temporary coordinates
                $tempCoordinatesId = $systemsCoordinatesTempModel->insert(array_merge(
                    array(
                        'refSystem'         => $currentSystem->getId(),
                        'refUser'           => static::$user->getId(),
                        'refSoftware'       => static::$softwareId,
                        'dateSubmission'    => new \Zend_Db_Expr('NOW()'),
                    ), 
                    $systemCoordinates
                ));
            }
        }
        
        // We have found the right system
        if(!is_null($currentSystem))
        {
            $systemsLogsModel           = new \Models_Systems_Logs;
            static::$return['systemId'] = $currentSystem->getId();
            
            // Check before/after flight logs
            $before = $systemsLogsModel->select()
                                       ->where('user = ?', static::$user->getId())
                                       ->where('dateVisited < ?', $json['timestamp'])
                                       ->limit(1)
                                       ->order('dateVisited DESC');
            $before = $systemsLogsModel->fetchRow($before);
            
            if(!is_null($before))
            {
                if($before->system == $currentSystem->getId())
                {
                    // Check if time difference is more than a couple of seconds
                    if(abs(strtotime($before->dateVisited) - strtotime($json['timestamp'])) >= 20)
                    {
                        static::$return['msgnum']   = 452;
                        static::$return['msg']      = 'An entry for the same system already exists just before the visited date (#' . $before->id . ').';
                        
                        return static::$return;
                    }
                    else
                    {
                        // Delete to store the new precise timestamp
                        $systemsLogsModel->deleteById($before->id);
                        $before = null;
                    }
                }
            }
            
            $after  = $systemsLogsModel->select()
                                       ->where('user = ?', static::$user->getId())
                                       ->where('dateVisited > ?', $json['timestamp'])
                                       ->limit(1)
                                       ->order('dateVisited ASC');
            $after  = $systemsLogsModel->fetchRow($after);
            if(!is_null($after))
            {
                if($after->system == $currentSystem->getId())
                {
                    // Check if time difference is more than a couple of seconds
                    if(abs(strtotime($after->dateVisited) - strtotime($json['timestamp'])) >= 20)
                    {
                        static::$return['msgnum']   = 453;
                        static::$return['msg']      = 'An entry for the same system already exists just after the visited date (#' . $after->id . ').';
                        
                        return static::$return;
                    }
                    else
                    {
                        // Delete to store the new precise timestamp
                        $systemsLogsModel->deleteById($after->id);
                        $after = null;
                    }
                }
            }
            
            $insert                 = array();
            $insert['system']       = static::$return['systemId'];
            $insert['user']         = static::$user->getId();
            $insert['refSoftware']  = static::$softwareId;
            $insert['dateVisited']  = $json['timestamp'];
            
            // Find the current shipID
            $currentShipId = static::findShipId($json);
            
            if(!is_null($currentShipId))
            {
                static::$return['shipId']   = (int) $currentShipId;
                $insert['refShip']          = (int) $currentShipId;
            }
            
            // Do we have the used Fuel?
            if(array_key_exists('FuelUsed', $json))
            {
                $insert['fuelUsed'] = $json['FuelUsed'];
            }
            
            // Do we have the jump distance?
            if(array_key_exists('JumpDist', $json))
            {
                $insert['jumpDistance'] = $json['JumpDist'];
                            
                // BADGES
                if($json['JumpDist'] >= 50) { static::$user->giveBadge(150); }
                if($json['JumpDist'] >= 200) { static::$user->giveBadge(151); }
            }
            
            // Check if statistics for distance to Sol/Colonia are greater
            $usersStatisticsModel = new \Models_Users_Statistics;
            $userStatistics         = $usersStatisticsModel->getByRefUser(static::$user->getId());
            
            if(!is_null($userStatistics))
            {
                // Already backfilled from the old logs, so update if greater
                if(!is_null($userStatistics['explorationGreatestDistanceFromSol']))
                {
                    $currentMaxDistance = \EDSM_System_Distances::calculate(
                        \EDSM_System::getInstance(27), // Sol
                        $currentSystem
                    );
                    
                    if($currentMaxDistance > $userStatistics['explorationGreatestDistanceFromSol'])
                    {
                        if($currentMaxDistance >= 65000) { static::$user->giveBadge(220); }
                        
                        $usersStatisticsModel->updateByRefUser(
                            static::$user->getId(),
                            [
                                'explorationGreatestDistanceFromSol' => $currentMaxDistance,
                            ]
                        );
                    }
                }
                
                // Already backfilled from the old logs, so update if greater
                if(!is_null($userStatistics['explorationGreatestDistanceFromColonia']))
                {
                    $currentMaxDistance = \EDSM_System_Distances::calculate(
                        \EDSM_System::getInstance(3384966), // Colonia
                        $currentSystem
                    );
                    
                    if($currentMaxDistance > $userStatistics['explorationGreatestDistanceFromColonia'])
                    {
                        $usersStatisticsModel->updateByRefUser(
                            static::$user->getId(),
                            [
                                'explorationGreatestDistanceFromColonia' => $currentMaxDistance,
                            ]
                        );
                    }
                }
            }
            
            // Check if flight log already exists
            try
            {
                $flightLogId = $systemsLogsModel->insert($insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';
                    
                    // Find the current flight log
                    $same = $systemsLogsModel->select()
                                             ->where('system = ?', static::$return['systemId'])
                                             ->where('user = ?', static::$user->getId())
                                             ->where('dateVisited = ?', $json['timestamp']);
                    $same = $systemsLogsModel->fetchRow($same);
                    
                    if(!is_null($same))
                    {
                        $update = array();
                        
                        // Ship id?
                        if(!is_null($currentShipId))
                        {
                            if($currentShipId != $same->refShip)
                            {
                                $update['refShip'] = $currentShipId;
                            }
                        }
                        
                        if(array_key_exists('FuelUsed', $json))
                        {
                            if($json['FuelUsed'] != $same->fuelUsed)
                            {
                                $update['fuelUsed'] = $json['FuelUsed'];
                            }
                        }
                        
                        if(array_key_exists('JumpDist', $json))
                        {
                            if($json['JumpDist'] != $same->jumpDistance)
                            {
                                $update['jumpDistance'] = $json['JumpDist'];
                            }
                            
                            // BADGES
                            if($json['JumpDist'] >= 50) { static::$user->giveBadge(150); }
                            if($json['JumpDist'] >= 200) { static::$user->giveBadge(151); }
                        }
                        
                        // Update if needed
                        if(count($update) > 0)
                        {
                            $systemsLogsModel->updateById($same->id, $update);
                        }
                        
                        unset($update);
                    }
                    
                    unset($same);
                }
                else
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();
                }
                
                unset($flightLogId);
            }
            
            // Update ship current system and fuel if needed
            if(is_null($after) && !is_null($currentShipId))
            {
                static::updateCurrentGameShipId($currentShipId, $json['timestamp']);
                
                $usersShipsModel    = new \Models_Users_Ships;
                $currentShipId      = static::$user->getShipById($currentShipId);
                
                if(!is_null($currentShipId))
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();
                    
                    // Update fuel
                    if(!array_key_exists('fuelUpdated', $currentShip) || is_null($currentShip['fuelUpdated']) || strtotime($currentShip['fuelUpdated']) < strtotime($json['timestamp']))
                    {
                        if(array_key_exists('FuelLevel', $json))
                        {
                            $update['fuelMainLevel']     = $json['FuelLevel'];
                            $update['fuelUpdated']       = $json['timestamp'];
                        }
                    }
                    
                    // Update position
                    if(!array_key_exists('locationUpdated', $currentShip) || is_null($currentShip['locationUpdated']) || strtotime($currentShip['locationUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['refSystem']        = static::$return['systemId'];
                        $update['refStation']       = new \Zend_Db_Expr('NULL');
                        $update['locationUpdated']  = $json['timestamp'];
                    }
                    
                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }
                    
                    unset($update);
                }
            }
            
            // Update temp coordinates with the reference log or delete if no further flight log
            if(isset($tempCoordinatesId))
            {
                $systemsCoordinatesTempModel = new \Models_Systems_CoordinatesTemp;
                
                if(isset($flightLogId))
                {
                    $systemsCoordinatesTempModel->updateById(
                        $tempCoordinatesId,
                        [
                            'refLog' => $flightLogId,
                        ]
                    );
                }
                else
                {
                    $systemsCoordinatesTempModel->deleteById($tempCoordinatesId);
                }
            }
            
            unset($systemsCoordinatesTempModel);
            
            // Handle system power?
            if(array_key_exists('Powers', $json) && array_key_exists('PowerplayState', $json))
            {
                static::doPowerplay(static::$return['systemId'], $json);
            }
            else
            {
                // Check if user still involved in powerplay, if not delete everything
                $userPower = static::$user->getPower();
                
                if(is_null($userPower) && strtotime(static::$user->getPowerLastUpdate()) <= strtotime($json['timestamp']))
                {
                    $systemsPowerplayModel  = new \Models_Systems_Powerplay;
                    $systemsPowerplayModel->deleteByRefSystem(static::$return['systemId']);
                    unset($systemsPowerplayModel);
                }
            }
        }
        
        return static::$return;
    }
    
    private static function doPowerplay($refSystem, $json)
    {
        $systemsPowerplayModel  = new \Models_Systems_Powerplay;
        $currentPowerplay       = $systemsPowerplayModel->getByRefSystem($refSystem);
        $updatePowerplay        = true;
        
        if(!is_null($currentPowerplay))
        {
            foreach($currentPowerplay AS $powerplay)
            {
                if(strtotime($powerplay['dateUpdated']) >= strtotime($json['timestamp']))
                {
                    $updatePowerplay = false;
                }
            }
        }
        
        if($updatePowerplay === true)
        {
            $systemsPowerplayModel->deleteByRefSystem($refSystem);
            
            foreach($json['Powers'] AS $power)
            {
                // Check if power is known in EDSM
                $powerId        = \Alias\System\Power::getFromFd($power);
                
                if(!is_null($powerId))
                {
                    try
                    {
                        $systemsPowerplayModel->insert(array(
                            'refSystem'     => $refSystem,
                            'refPower'      => $powerId,
                            'state'         => $json['PowerplayState'],
                            'dateUpdated'   => $json['timestamp'],
                        ));
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        //TODO: Handle expection
                    }
                }
                else
                {
                    \EDSM_Api_Logger_Alias::log(
                        'Alias\System\Power: ' . $power . ' (Sofware#' . static::$softwareId . ')',
                        array('file' => __FILE__, 'line' => __LINE__,)
                    );
                }
            }
        }
        
        unset($systemsPowerplayModel, $currentPowerplay);
    }
}