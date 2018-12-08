<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ModuleBuy extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove module cost from commander credits.',
        '<span class="text-warning">Event is inserted only if cost is superior to 0.</span>',
        'Add sell price if old module is sold.',
        'Update ship paintjob.',
    ];



    public static function run($json)
    {
        if($json['BuyPrice'] > 0)
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['BuyItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['BuyItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ModuleBuy')
                                  ->where('balance = ?', - (int) $json['BuyPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ModuleBuy';
                $insert['balance']      = - (int) $json['BuyPrice'];
                $insert['dateUpdated']  = $json['timestamp'];

                // Generate details
                $details = static::generateDetails($json);
                if(!is_null($details)){ $insert['details'] = $details; }

                $usersCreditsModel->insert($insert);

                unset($insert);
            }
            else
            {
                $details = static::generateDetails($json);

                if($isAlreadyStored->details != $details)
                {
                    $usersCreditsModel->updateById(
                        $isAlreadyStored->id,
                        [
                            'details' => $details,
                        ]
                    );
                }

                static::$return['msgnum']   = 101;
                static::$return['msg']      = 'Message already stored';
            }

            unset($isAlreadyStored);
        }
        else
        {
            // Check if it's a paintjob
            if(stripos($json['BuyItem'], '$paintjob_') !== false && !in_array($json['BuyItem'], static::$excludedOutfitting))
            {
                $paintjob = strtolower($json['BuyItem']);
                $paintjob = str_replace('$paintjob_', '', $paintjob);
                $paintjob = str_replace('_name;', '', $paintjob);

                $currentShipId  = static::findShipId($json);

                if(!is_null($currentShipId))
                {
                    $usersShipsModel    = new \Models_Users_Ships;
                    $currentShipId      = static::$user->getShipById($currentShipId);

                    if(!is_null($currentShipId))
                    {
                        $currentShip    = $usersShipsModel->getById($currentShipId);

                        if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) <= strtotime($json['timestamp']))
                        {
                            $update                 = array();
                            $update['paintJob']     = $paintjob;
                            $update['dateUpdated']  = $json['timestamp'];

                            $usersShipsModel->updateById($currentShipId, $update);

                            unset($update);
                        }

                        unset($currentShip);
                    }

                    unset($usersShipsModel, $currentShipId);
                }
            }
        }

        // Does the module implied a sell?
        if(array_key_exists('SellPrice', $json) && $json['SellPrice'] > 0 && !in_array($json['SellItem'], static::$excludedOutfitting))
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['SellItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ModuleSell')
                                  ->where('balance = ?', (int) $json['SellPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ModuleSell';
                $insert['balance']      = (int) $json['SellPrice'];
                $insert['dateUpdated']  = $json['timestamp'];

                // Generate details
                $details = static::generateDetailsSell($json);
                if(!is_null($details)){ $insert['details'] = $details; }

                $usersCreditsModel->insert($insert);

                unset($insert);
            }
            else
            {
                $details = static::generateDetailsSell($json);

                if($isAlreadyStored->details != $details)
                {
                    $usersCreditsModel->updateById(
                        $isAlreadyStored->id,
                        [
                            'details' => $details,
                        ]
                    );
                }
            }

            unset($isAlreadyStored);
        }

        unset($usersCreditsModel);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details = array();

        if(array_key_exists('ShipID', $json))
        {
            $details['shipId'] = $json['ShipID'];
        }

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['BuyItem']);

        if(!is_null($outfittingType))
        {
            $details['type']  = $outfittingType;
        }

        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }

    static private function generateDetailsSell($json)
    {
        $details = array();

        if(array_key_exists('ShipID', $json))
        {
            $details['shipId'] = $json['ShipID'];
        }

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

        if(!is_null($outfittingType))
        {
            $details['type']  = $outfittingType;
        }

        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}