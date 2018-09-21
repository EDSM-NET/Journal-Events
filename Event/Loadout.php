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
                
                \EDSM_Api_Logger_Alias::log(
                    'Alias\Ship\Type: ' . $json['Ship'] . ' (Sofware#' . static::$softwareId . ')',
                    [
                        'file'  => __FILE__,
                        'line'  => __LINE__,
                    ]
                );
                
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
                
                $currentShipId  = $usersShipsModel->insert($insert);
                $saveModules    = true;
                
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
            
            if($saveModules === true && array_key_exists('Modules', $json))
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
                                    
                                    if(array_key_exists('Engineering', $module))
                                    {
                                        $engineering = array();
                                        
                                        // Check if Engineer is known in EDSM
                                        $engineerId        = \Alias\Station\Engineer::getFromFd($module['Engineering']['EngineerID']);
                                        
                                        if(!is_null($engineerId))
                                        {
                                            $blueprintId        = \Alias\Station\Engineer\Blueprint\Type::getFromFd($module['Engineering']['BlueprintID']);
                                            
                                            if(!is_null($blueprintId))
                                            {
                                                $engineering['engineerId']      = $engineerId;
                                                $engineering['blueprintId']     = $blueprintId;
                                                $engineering['level']           = $module['Engineering']['Level'];
                                                $engineering['quality']         = $module['Engineering']['Quality'];
                                                $engineering['mods']            = $module['Engineering']['Modifiers'];
                                                
                                                if(array_key_exists('ExperimentalEffect', $module['Engineering']))
                                                {
                                                    $effectId        = \Alias\Station\Engineer\Effect\Type::getFromFd($module['Engineering']['ExperimentalEffect']);
                                                    
                                                    if(!is_null($effectId))
                                                    {
                                                        $engineering['effectId'] = $effectId;
                                                    }
                                                    else
                                                    {
                                                        \EDSM_Api_Logger_Alias::log(
                                                            'Alias\Station\Engineer\Effect\Type: ' . $module['Engineering']['ExperimentalEffect'] . ' / ' . $module['Engineering']['ExperimentalEffect_Localised'] . ' (Sofware#' . static::$softwareId . ')',
                                                            [
                                                                'file'  => __FILE__,
                                                                'line'  => __LINE__,
                                                            ]
                                                        );
                                                    }
                                                }
                                                
                                                $insertModule['engineering']    = \Zend_Json::encode($engineering);
                                            }
                                            else
                                            {
                                                \EDSM_Api_Logger_Alias::log(
                                                    'Alias\Station\Engineer\Blueprint\Type: ' . $module['Engineering']['BlueprintID'] . ' / ' . $module['Engineering']['BlueprintName'] . ' / ' . $module['Engineering']['Level'] . ' (Sofware#' . static::$softwareId . ')',
                                                    [
                                                        'file'  => __FILE__,
                                                        'line'  => __LINE__,
                                                    ]
                                                );
                                            }
                                        }
                                        else
                                        {
                                            \EDSM_Api_Logger_Alias::log(
                                                'Alias\Station\Engineer: ' . $module['Engineering']['EngineerID'] . ' / ' . $module['Engineering']['Engineer'] . ' (Sofware#' . static::$softwareId . ')',
                                                [
                                                    'file'  => __FILE__,
                                                    'line'  => __LINE__,
                                                ]
                                            );
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
                                    \EDSM_Api_Logger_Alias::log(
                                        'Alias\Station\Outfitting\Type : ' . $module['Item'] . ' (Sofware#' . static::$softwareId . ')',
                                        [
                                            'file'  => __FILE__,
                                            'line'  => __LINE__,
                                        ]
                                    );
                                }
                            }
                        }
                        else//if(!in_array($module['Item'], static::$excludedOutfitting))
                        {
                            \EDSM_Api_Logger_Alias::log(
                                'Alias\Ship\Slot : ' . $module['Slot'] . ' (Sofware#' . static::$softwareId . ')',
                                [
                                    'file'  => __FILE__,
                                    'line'  => __LINE__,
                                ]
                            );
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