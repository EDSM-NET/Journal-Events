<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class EngineerContribution extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove donation from the commander credits.',
        'Remove commodities from the commander cargo hold.',
        'Remove materials/data from the commander inventory.',
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

        if(array_key_exists('Type', $json))
        {
            if($json['Type'] == 'Commodity')
            {
                $commodityModel     = new \Models_Users_Cargo;
                $commodityClass     = 'Alias\Station\Commodity\Type';
                $currentCommodities = $commodityModel->getByRefUser(static::$user->getId());

                $isCommodity = $commodityClass::getFromFd($json['Commodity']);

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
                            $update['total']        = max(0, $currentItem['total'] - $json['Quantity']);
                            $update['lastUpdate']   = $json['timestamp'];

                            $commodityModel->updateById($currentItem['id'], $update);

                            unset($update);
                        }
                    }
                }
                else
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log('Alias\Station\Commodity\Type: ' . $json['Commodity']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }

                unset($commodityModel, $currentCommodities);
            }
            elseif($json['Type'] == 'Credits')
            {
                $usersCreditsModel = new \Models_Users_Credits;

                $isAlreadyStored   = $usersCreditsModel->fetchRow(
                    $usersCreditsModel->select()
                                      ->where('refUser = ?', static::$user->getId())
                                      ->where('reason = ?', 'EngineerContribution')
                                      ->where('balance = ?', - (int) $json['Quantity'])
                                      ->where('dateUpdated = ?', $json['timestamp'])
                );

                if(is_null($isAlreadyStored))
                {
                    $insert                 = array();
                    $insert['refUser']      = static::$user->getId();
                    $insert['reason']       = 'EngineerContribution';
                    $insert['balance']      = - (int) $json['Quantity'];
                    $insert['dateUpdated']  = $json['timestamp'];

                    $usersCreditsModel->insert($insert);

                    unset($insert);
                }

                unset($usersCreditsModel, $isAlreadyStored);
            }
            elseif($json['Type'] == 'Materials')
            {
                $materialModel      = new \Models_Users_Materials;
                $materialClass      = 'Alias\Commander\Material';
                $currentMaterials   = $materialModel->getByRefUser(static::$user->getId());

                // Is material?
                $isMaterial = $materialClass::getFromFd($json['Material']);

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
                            $update['total']        = max(0, $currentItem['total'] - $json['Quantity']);
                            $update['lastUpdate']   = $json['timestamp'];

                            $materialModel->updateById($currentItem['id'], $update);

                            unset($update);
                        }
                    }
                }

                unset($materialModel, $currentMaterials);

                $dataModel          = new \Models_Users_Data;
                $dataClass          = 'Alias\Commander\Data';
                $currentDatas       = $dataModel->getByRefUser(static::$user->getId());

                // Is data?
                $isData = $dataClass::getFromFd($json['Material']);

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
                            $update['total']        = max(0, $currentItem['total'] - $json['Quantity']);
                            $update['lastUpdate']   = $json['timestamp'];

                            $dataModel->updateById($currentItem['id'], $update);

                            unset($update);
                        }
                    }
                }

                unset($dataModel, $currentDatas);

                // Not found in all classes
                if(is_null($isMaterial) && is_null($isData))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log('Engineer\Material|Data: ' . $json['Material']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }
            }
            elseif($json['Type'] == 'Bond')
            {

            }
            elseif($json['Type'] == 'Bounty')
            {

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
        else
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);
        }

        return static::$return;
    }
}