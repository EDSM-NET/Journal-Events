<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardSell extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add ship sell price to commander credits.',
        'Mark the ship as sold in the commander fleet.',
    ];



    public static function run($json)
    {
        $shipType = \Alias\Ship\Type::getFromFd($json['ShipType']);

        if(is_null($shipType))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\Ship\Type : ' . $json['ShipType']);

            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        if($json['ShipPrice'] > 0)
        {
            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ShipyardSell')
                                  ->where('balance = ?', (int) $json['ShipPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ShipyardSell';
                $insert['balance']      = (int) $json['ShipPrice'];
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

            unset($usersCreditsModel, $isAlreadyStored);
        }

        // Sell ship
        $usersShipsModel    = new \Models_Users_Ships;
        $currentShipId      = static::$user->getShipById($json['SellShipID']);

        if(!is_null($currentShipId))
        {
            $shipType = \Alias\Ship\Type::getFromFd($json['ShipType']);

            if(!is_null($shipType))
            {
                $currentShip    = $usersShipsModel->getById($currentShipId);
                $update         = array();

                if($currentShip['type'] == $shipType && $currentShip['sell'] == 0)
                {
                    $update['sell'] = 1;

                    if(is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['dateUpdated']      = $json['timestamp'];
                    }
                }

                if(count($update) > 0)
                {
                    $usersShipsModel->updateById($currentShipId, $update);
                }

                unset($currentShip, $update);
            }
        }

        unset($usersShipsModel);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details = array();

        $shipType = \Alias\Ship\Type::getFromFd($json['ShipType']);

        if(!is_null($shipType))
        {
            $details['shipType']  = $shipType;
        }

        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }

        $details['shipId'] = $json['SellShipID'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}