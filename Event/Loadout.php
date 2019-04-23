<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Loadout extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update current shipID.',
        'Insert ship if not known.',
        'Update ship paintjob.',
        'Update ship hull value, modules value and rebuy.',
        'Store modules.',
        'Unlock Technology Broker.',
    ];


    protected static $excludedSlot  = [
        'Decal1', 'Decal2', 'Decal3',

        'Bobble01', 'Bobble02',
        'Bobble03', 'Bobble04',
        'Bobble05', 'Bobble06',
        'Bobble07', 'Bobble08',
        'Bobble09', 'Bobble10',

        'ShipName0', 'ShipName1',
        'ShipID0', 'ShipID1',

        'PaintJob',
        'EngineColour', 'WeaponColour',
        'StringLights',
        'ShipKitSpoiler', 'ShipKitWings', 'ShipKitBumper', 'ShipKitTail',

        'VesselVoice', 'ShipCockpit',
    ];



    public static function run($json)
    {
        // Update shipID
        if(!in_array(strtolower($json['Ship']), static::$notShipTypes))
        {
            $shipType = \Alias\Ship\Type::getFromFd($json['Ship']);

            if(is_null($shipType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown (' . $json['Ship'] . ')';

                \EDSM_Api_Logger_Alias::log('Alias\Ship\Type: ' . $json['Ship']);

                return static::$return;
            }

            static::updateCurrentGameShipId($json['ShipID'], $json['timestamp']);

            $saveModules        = false;
            $usersShipsModel    = new \Models_Users_Ships;
            $currentShipId      = static::$user->getShipById($json['ShipID'], $shipType);

            // Insert ship
            if(is_null($currentShipId))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refShip']      = $json['ShipID'];
                $insert['type']         = $shipType;
                $insert['customName']   = $json['ShipName'];
                $insert['customIdent']  = $json['ShipIdent'];
                $insert['paintJob']     = static::findPaintJob($json);
                $insert['dateUpdated']  = $json['timestamp'];

                // Ship value
                if(array_key_exists('HullValue', $json) && $json['HullValue'] <= PHP_INT_MAX){ $insert['hullValue'] = $json['HullValue']; }
                if(array_key_exists('HullHealth', $json)){ $insert['hullHealth'] = $json['HullHealth']; }
                if(array_key_exists('ModulesValue', $json) && $json['ModulesValue'] <= PHP_INT_MAX){ $insert['modulesValue'] = $json['ModulesValue']; }
                if(array_key_exists('Rebuy', $json) && $json['Rebuy'] <= PHP_INT_MAX){ $insert['rebuyValue'] = $json['Rebuy']; }

                try
                {
                    $currentShipId  = $usersShipsModel->insert($insert);
                    $saveModules    = true;
                }
                catch(\Zend_Db_Exception $e)
                {
                    // Based on unique index, this ship entry was already saved.
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        $currentShipId  = null;
                        $saveModules    = false;
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

                unset($insert);
            }
            // Update ship if needed
            else
            {
                $currentShip    = $usersShipsModel->getById($currentShipId);
                $update         = array();

                if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) <= strtotime($json['timestamp']))
                {
                    $update['type']             = $shipType;
                    $update['sell']             = 0; // If new loadout, cannot be sold.

                    $update['customName']       = $json['ShipName'];
                    $update['customIdent']      = $json['ShipIdent'];
                    $update['paintJob']         = static::findPaintJob($json);

                    // Ship value
                    if(array_key_exists('HullValue', $json) && $json['HullValue'] <= PHP_INT_MAX){ $update['hullValue'] = $json['HullValue']; }
                    if(array_key_exists('ModulesValue', $json) && $json['ModulesValue'] <= PHP_INT_MAX){ $update['modulesValue'] = $json['ModulesValue']; }
                    if(array_key_exists('Rebuy', $json) && $json['Rebuy'] <= PHP_INT_MAX){ $update['rebuyValue'] = $json['Rebuy']; }

                    $update['dateUpdated']      = $json['timestamp'];
                }

                if(count($update) > 0)
                {
                    $usersShipsModel->updateById($currentShipId, $update);
                    $saveModules = true;
                }

                unset($update);
            }

            unset($usersShipsModel);

            if($saveModules === true && array_key_exists('Modules', $json) && !is_null($currentShipId))
            {
                $usersShipsModulesModel = new \Models_Users_Ships_Modules;
                $usersShipsModulesModel->deleteByRefShip($currentShipId);

                $technologyBrokerUnlocked   = array();

                foreach($json['Modules'] AS $module)
                {
                    if(array_key_exists('Slot', $module) && !in_array($module['Slot'], static::$excludedSlot))
                    {
                        $slotType = \Alias\Ship\Slot::getFromFd($module['Slot']);

                        if(!is_null($slotType))
                        {
                            if(array_key_exists('Item', $module))
                            {
                                $outfittingType     = \Alias\Station\Outfitting\Type::getFromFd($module['Item']);

                                if(!is_null($outfittingType))
                                {
                                    $insertModule                   = array();
                                    $insertModule['refShip']        = $currentShipId;
                                    $insertModule['refSlot']        = $slotType;
                                    $insertModule['refOutfitting']  = $outfittingType;

                                    $insertModule['on']             = (array_key_exists('On', $module) && $module['On'] == true) ? 1 : 0;
                                    $insertModule['priority']       = (array_key_exists('Priority', $module)) ? $module['Priority'] : new \Zend_Db_Expr('NULL');
                                    $insertModule['health']         = (array_key_exists('Health', $module)) ? $module['Health'] : new \Zend_Db_Expr('NULL');
                                    $insertModule['value']          = (array_key_exists('Value', $module)) ? $module['Value'] : new \Zend_Db_Expr('NULL');

                                    $insertModule['ammoInClip']     = (array_key_exists('AmmoInClip', $module)) ? $module['AmmoInClip'] : new \Zend_Db_Expr('NULL');
                                    $insertModule['ammoInHopper']   = (array_key_exists('AmmoInHopper', $module)) ? $module['AmmoInHopper'] : new \Zend_Db_Expr('NULL');

                                    if(array_key_exists('Engineering', $module) && count($module['Engineering']) > 0)
                                    {
                                        $engineering = array();

                                        // Check if Engineer is known in EDSM
                                        $engineerId        = \Alias\Station\Engineer::getFromFd($module['Engineering']['EngineerID']);

                                        if(!is_null($engineerId))
                                        {
                                            if(!array_key_exists('BlueprintID', $module['Engineering']))
                                            {
                                                $blueprintId = null;

                                                // Try to guess it from name
                                                if(array_key_exists('BlueprintName', $module['Engineering']))
                                                {
                                                    // Convert cAPI Blueprint name...
                                                    // Done on cAPI importer too...
                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_Sensor_', 'Sensor_', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('LifeSupport_', 'Misc_', $module['Engineering']['BlueprintName']);

                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_SurfaceScanner_LongRange', 'Sensor_LongRange', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_KillWarrantScanner_LongRange', 'Sensor_LongRange', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_WakeScanner_LongRange', 'Sensor_LongRange', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('HeatSinkLauncher_HeatSinkCapacity', 'Misc_HeatSinkCapacity', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('ChaffLauncher_ChaffCapacity', 'Misc_ChaffCapacity', $module['Engineering']['BlueprintName']);

                                                    $module['Engineering']['BlueprintName'] = str_replace('ChaffLauncher_Reinforced', 'Misc_Reinforced', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('PointDefence_Reinforced', 'Misc_Reinforced', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('HeatSinkLauncher_Reinforced', 'Misc_Reinforced', $module['Engineering']['BlueprintName']);

                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_KillWarrantScanner_FastScan', 'Sensor_FastScan', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('Sensor_SurfaceScanner_FastScan', 'Sensor_FastScan', $module['Engineering']['BlueprintName']);

                                                    $module['Engineering']['BlueprintName'] = str_replace('ChaffLauncher_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('HeatSinkLauncher_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('PointDefence_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('WakeScanner_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('CollectionLimpet_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('FuelTransferLimpet_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('PointDefence_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('KillWarrantScanner_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('ProspectingLimpet_LightWeight', 'Misc_LightWeight', $module['Engineering']['BlueprintName']);

                                                    $module['Engineering']['BlueprintName'] = str_replace('ChaffLauncher_Shielded', 'Misc_Shielded', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('AMF_Shielded', 'Misc_Shielded', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('PointDefence_Shielded', 'Misc_Shielded', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('FuelScoop_Shielded', 'Misc_Shielded', $module['Engineering']['BlueprintName']);
                                                    $module['Engineering']['BlueprintName'] = str_replace('CollectionLimpet_Shielded', 'Misc_Shielded', $module['Engineering']['BlueprintName']);

                                                    $guess = array_search(
                                                        strtolower($module['Engineering']['BlueprintName']),
                                                        \Alias\Station\Engineer\Blueprint\Type::$nameLoadout
                                                    );

                                                    if($guess !== false)
                                                    {
                                                        $blueprintId = $guess;
                                                    }
                                                    else
                                                    {
                                                        \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Blueprint\Type::$nameLoadout: ' . $module['Engineering']['BlueprintName'] . ' / ' . $module['Engineering']['Level']);
                                                    }
                                                }
                                            }
                                            else
                                            {
                                                $blueprintId        = \Alias\Station\Engineer\Blueprint\Type::getFromFd($module['Engineering']['BlueprintID']);
                                            }

                                            if(!is_null($blueprintId))
                                            {
                                                $engineering['engineerId']      = $engineerId;
                                                $engineering['blueprintId']     = $blueprintId;
                                                $engineering['level']           = $module['Engineering']['Level'];
                                                $engineering['quality']         = $module['Engineering']['Quality'];
                                                $engineering['mods']            = array();

                                                // Loop modules and remove unneeded fields ;)
                                                foreach($module['Engineering']['Modifiers'] AS $moduleModifier)
                                                {
                                                    // For each modifiers, check that we have a class existing, and the module original value :)
                                                    // This will be use to remove OriginalValue from the database when we need to create it again for Coriolis...
                                                    if(array_key_exists('OriginalValue', $moduleModifier) || array_key_exists('ValueStr', $moduleModifier))
                                                    {
                                                        $useClass   = 'Alias\Station\Outfitting\\' . $moduleModifier['Label'];

                                                        if(file_exists(LIBRARY_PATH . '/' . str_replace(['\\', '_'], ['/', '/'], $useClass) . '.php'))
                                                        {
                                                            $getOriginalValue = $useClass::get($insertModule['refOutfitting']);

                                                            if(is_null($getOriginalValue))
                                                            {
                                                                if(array_key_exists('ValueStr', $moduleModifier) && !array_key_exists('OriginalValue', $moduleModifier))
                                                                {
                                                                    \EDSM_Api_Logger_Alias::log('Outfitting OriginalValue missing: ' . $useClass . '::' . $insertModule['refOutfitting'] . ': ' . $moduleModifier['ValueStr']);
                                                                }
                                                                else
                                                                {
                                                                    \EDSM_Api_Logger_Alias::log('Outfitting OriginalValue missing: ' . $useClass . '::' . $insertModule['refOutfitting'] . ': ' . $moduleModifier['OriginalValue']);
                                                                }
                                                            }
                                                            elseif(array_key_exists('OriginalValue', $moduleModifier))
                                                            {
                                                                if($getOriginalValue != $moduleModifier['OriginalValue'] && $insertModule['refOutfitting'] != 4011 && $useClass != 'Alias\Station\Outfitting\Mass')
                                                                {
                                                                    \EDSM_Api_Logger_Alias::log('Outfitting OriginalValue wrong: ' . $useClass . '::' . $insertModule['refOutfitting'] . ': ' . $moduleModifier['OriginalValue']);
                                                                }
                                                                else
                                                                {
                                                                    // We have the static value, no need to save it...
                                                                    unset($moduleModifier['OriginalValue']);
                                                                }
                                                            }

                                                            if(array_key_exists('LessIsGood', $moduleModifier))
                                                            {
                                                                $getLessIsGood = (int) $useClass::getLessIsGood($insertModule['refOutfitting']);

                                                                if($getLessIsGood != $moduleModifier['LessIsGood'])
                                                                {
                                                                    \EDSM_Api_Logger_Alias::log('Outfitting LessIsGood wrong: ' . $useClass . '::' . (bool) $moduleModifier['LessIsGood']);
                                                                }
                                                                else
                                                                {
                                                                    // We have the static value, no need to save it...
                                                                    unset($moduleModifier['LessIsGood']);
                                                                }
                                                            }
                                                        }
                                                        else
                                                        {
                                                            \EDSM_Api_Logger_Alias::log('Outfitting class do not exists: ' . $useClass);
                                                        }
                                                    }

                                                    $engineering['mods'][] = $moduleModifier;
                                                }

                                                if(array_key_exists('ExperimentalEffect', $module['Engineering']))
                                                {
                                                    $effectId        = \Alias\Station\Engineer\Effect\Type::getFromFd($module['Engineering']['ExperimentalEffect']);

                                                    if(!is_null($effectId))
                                                    {
                                                        $engineering['effectId'] = $effectId;
                                                    }
                                                    else
                                                    {
                                                        \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Effect\Type: ' . $module['Engineering']['ExperimentalEffect']);
                                                    }
                                                }

                                                $insertModule['engineering']    = \Zend_Json::encode($engineering);
                                            }
                                            elseif(array_key_exists('BlueprintID', $module['Engineering']))
                                            {
                                                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Blueprint\Type: ' . $module['Engineering']['BlueprintID'] . ' / ' . $module['Engineering']['BlueprintName'] . ' / ' . $module['Engineering']['Level']);
                                            }
                                        }
                                        elseif($module['Engineering']['EngineerID'] <= PHP_INT_MAX)
                                        {
                                            if(array_key_exists('EngineerName', $module['Engineering']))
                                            {
                                                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer: ' . $module['Engineering']['EngineerID'] . ' / ' . $module['Engineering']['EngineerName']);
                                            }
                                            elseif(array_key_exists('Engineer', $module['Engineering']))
                                            {
                                                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer: ' . $module['Engineering']['EngineerID'] . ' / ' . $module['Engineering']['Engineer']);
                                            }
                                            else
                                            {
                                                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer: ' . $module['Engineering']['EngineerID']);
                                            }
                                        }
                                    }

                                    try
                                    {
                                        $usersShipsModulesModel->insert($insertModule);
                                    }
                                    catch(\Zend_Db_Exception $e)
                                    {
                                        // Based on unique index, this journal entry was already saved.
                                        if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                                        {

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

                                    unset($insertModule);

                                    // Check if module is one of the the technology broker unlock
                                    $currentTechnologyBrokerId = \Alias\Commander\TechnologyBroker::getFromFd($module['Item']);
                                    if(!is_null($currentTechnologyBrokerId) && !in_array($currentTechnologyBrokerId, $technologyBrokerUnlocked))
                                    {
                                        $technologyBrokerUnlocked[] = $currentTechnologyBrokerId;
                                    }
                                }
                                else//if(!in_array($module['Item'], static::$excludedOutfitting))
                                {
                                    \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $module['Item']);
                                }
                            }
                        }
                        else//if(!in_array($module['Item'], static::$excludedOutfitting))
                        {
                            \EDSM_Api_Logger_Alias::log('Alias\Ship\Slot : ' . $module['Slot']);
                        }

                    }
                }

                unset($usersShipsModulesModel);

                // Unlock technology broker for items currently equipped
                if(count($technologyBrokerUnlocked) > 0)
                {
                    $usersTechnologyBrokersModel    = new \Models_Users_TechnologyBrokers;
                    $currentTechnologies            = $usersTechnologyBrokersModel->getByRefUser(static::$user->getId());

                    foreach($technologyBrokerUnlocked AS $unlockedItem)
                    {
                        $currentTechnology      = null;

                        // Try to find current technology in the technologies array
                        if(!is_null($currentTechnologies) && count($currentTechnologies) > 0)
                        {
                            foreach($currentTechnologies AS $technology)
                            {
                                if($technology['refTechnology'] == $unlockedItem)
                                {
                                    $currentTechnology = $technology;
                                    break;
                                }
                            }
                        }

                        if(is_null($currentTechnology))
                        {
                            try
                            {
                                $insert                     = array();
                                $insert['lastUpdate']       = $json['timestamp'];
                                $insert['refUser']          = static::$user->getId();
                                $insert['refTechnology']    = $unlockedItem;

                                $usersTechnologyBrokersModel->insert($insert);

                                unset($insert);
                            }
                            catch(\Zend_Db_Exception $e)
                            {

                            }
                        }
                    }

                    unset($usersTechnologyBrokersModel, $currentTechnologies);
                }
            }
        }

        return static::$return;
    }

    static private function findPaintJob($json)
    {
        if(array_key_exists('Modules', $json))
        {
            foreach($json['Modules'] AS $module)
            {
                if(array_key_exists('Slot', $module) && strtolower($module['Slot']) == 'paintjob')
                {
                    if(array_key_exists('Item', $module))
                    {
                        $paintjob = $module['Item'];
                        $paintjob = strtolower($paintjob);
                        $paintjob = str_replace('paintjob_', '', $paintjob);

                        return $paintjob;
                    }
                }
            }
        }

        return null;
    }
}