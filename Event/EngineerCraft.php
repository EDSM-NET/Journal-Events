<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class EngineerCraft extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update engineer unlock grade.',
        'Remove the ingredients from the commander inventory.',
    ];



    public static function run($json)
    {
        // Check if Engineer is known in EDSM
        $engineerId        = \Alias\Station\Engineer::getFromFd($json['Engineer']);

        if(is_null($engineerId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer: ' . $json['Engineer']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Check if Blueprint is known in EDSM
        if(array_key_exists('Blueprint', $json) && array_key_exists('BlueprintID', $json))
        {
            $blueprintId        = \Alias\Station\Engineer\Blueprint\Type::getFromFd($json['BlueprintID']);

            if(is_null($blueprintId))
            {
                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Blueprint\Type: ' . $json['Blueprint'] . ' / ' . $json['BlueprintID']);
            }

            // Check if availability is known to EDSM
            if(!is_null($engineerId) && !is_null($blueprintId))
            {
                $availables = \Alias\Station\Engineer::getBlueprintsAvailabletoEngineer($engineerId);

                if(!array_key_exists($blueprintId, $availables))
                {
                    \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Blueprint\Available: ' . $json['Engineer'] . ' / ' . $json['Blueprint'] . ' / ' . $json['BlueprintID']);
                }
                elseif(array_key_exists('Level', $json) && !in_array($json['Level'], $availables[$blueprintId]))
                {
                    \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer\Blueprint\Available: ' . $json['Engineer'] . ' / ' . $json['Blueprint'] . ' / ' . $json['BlueprintID'] . ' / ' . $json['Level']);
                }
            }
        }

        if(array_key_exists('Level', $json))
        {
            $usersEngineersModel    = new \Models_Users_Engineers;
            $currentEngineers       = $usersEngineersModel->getByRefUser(static::$user->getId());
            $currentEngineer        = null;
            $lastEngineerUpdate     = strtotime('5YEAR AGO');

            // Try to find current engineer in the engineers array
            if(!is_null($currentEngineers) && count($currentEngineers) > 0)
            {
                foreach($currentEngineers AS $engineer)
                {
                    if($engineer['refEngineer'] == $engineerId)
                    {
                        $currentEngineer = $engineer;
                        break;
                    }
                }
            }

            if(!is_null($currentEngineer) && array_key_exists('lastUpdate', $currentEngineer))
            {
                $lastEngineerUpdate = strtotime($currentEngineer['lastUpdate']);
            }

            $insert = array();

            if(array_key_exists('Level', $json))
            {
                if(is_null($currentEngineer) || (!is_null($currentEngineer) && $json['Level'] > $currentEngineer['rank']))
                {
                    $insert['rank']             = (int) $json['Level'];
                    $insert['stageProgress']    = new \Zend_Db_Expr('NULL');
                }
            }

            if(count($insert) > 0)
            {
                $insert['refUser']      = static::$user->getId();

                if($lastEngineerUpdate < strtotime($json['timestamp']))
                {
                    $insert['lastUpdate'] = $json['timestamp'];
                }

                try
                {
                    if(!is_null($currentEngineer))
                    {
                        $usersEngineersModel->updateById(
                            $currentEngineer['id'],
                            $insert
                        );
                    }
                    else
                    {
                        $insert['refEngineer']  = $engineerId;

                        $usersEngineersModel->insert($insert);
                    }
                }
                catch(\Zend_Db_Exception $e)
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();
                    $json['isError']            = 1;

                    \Journal\Event::run($json);
                }
            }

            unset($usersEngineersModel, $insert);
        }

        $materialModel      = new \Models_Users_Materials;
        $materialClass      = 'Alias\Commander\Material';
        $currentMaterials   = $materialModel->getByRefUser(static::$user->getId());

        $dataModel          = new \Models_Users_Data;
        $dataClass          = 'Alias\Commander\Data';
        $currentDatas       = $dataModel->getByRefUser(static::$user->getId());

        $commodityModel     = new \Models_Users_Cargo;
        $commodityClass     = 'Alias\Station\Commodity\Type';
        $currentCommodities = $commodityModel->getByRefUser(static::$user->getId());

        foreach($json['Ingredients'] AS $key => $ingredient)
        {
            // Old journal version conversion
            if(!is_array($ingredient))
            {
                $ingredient = array(
                    'Name'      => $key,
                    'Count'     => $ingredient,
                );
            }

            // Is material?
            $isMaterial = $materialClass::getFromFd($ingredient['Name']);

            if(!is_null($isMaterial))
            {
                // Find the current item ID
                $currentItem   = null;
                if(!is_null($currentMaterials))
                {
                    foreach($currentMaterials AS $tempItem)
                    {
                        if($tempItem['type'] == $isMaterial)
                        {
                            $currentItem = $tempItem;
                            break;
                        }
                    }
                }

                // If we have the line, update else, wait for the startUp events
                if(!is_null($currentItem))
                {
                    if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                    {
                        $update                 = array();
                        $update['total']        = max(0, $currentItem['total'] - $ingredient['Count']);
                        $update['lastUpdate']   = $json['timestamp'];

                        $materialModel->updateById($currentItem['id'], $update);

                        unset($update);
                    }
                }

                continue;
            }

            // Is data?
            $isData = $dataClass::getFromFd($ingredient['Name']);

            if(!is_null($isData))
            {
                // Find the current item ID
                $currentItem   = null;
                if(!is_null($currentDatas))
                {
                    foreach($currentDatas AS $tempItem)
                    {
                        if($tempItem['type'] == $isData)
                        {
                            $currentItem = $tempItem;
                            break;
                        }
                    }
                }

                // If we have the line, update else, wait for the startUp events
                if(!is_null($currentItem))
                {
                    if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                    {
                        $update                 = array();
                        $update['total']        = max(0, $currentItem['total'] - $ingredient['Count']);
                        $update['lastUpdate']   = $json['timestamp'];

                        $dataModel->updateById($currentItem['id'], $update);

                        unset($update);
                    }
                }

                continue;
            }

            // Is commodity?
            $isCommodity = $commodityClass::getFromFd($ingredient['Name']);

            if(!is_null($isCommodity))
            {
                // Find the current item ID
                $currentItem   = null;
                if(!is_null($currentCommodities))
                {
                    foreach($currentCommodities AS $tempItem)
                    {
                        if($tempItem['type'] == $isCommodity)
                        {
                            $currentItem = $tempItem;
                            break;
                        }
                    }
                }

                // If we have the line, update else, wait for the startUp events
                if(!is_null($currentItem))
                {
                    if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                    {
                        $update                 = array();
                        $update['total']        = max(0, $currentItem['total'] - $ingredient['Count']);
                        $update['lastUpdate']   = $json['timestamp'];

                        $commodityModel->updateById($currentItem['id'], $update);

                        unset($update);
                    }
                }

                continue;
            }

            // Not found in all classes
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Engineer\Material|Data|Commodity: ' . $ingredient['Name']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        unset(
            $materialModel, $currentMaterials,
            $dataModel, $currentDatas,
            $commodityModel, $currentCommodities
        );

        return static::$return;
    }
}