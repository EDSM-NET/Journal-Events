<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SellShipOnRebuy extends Event
{
    use \Journal\Common\Credits;

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

        static::handleCredits(
            'SellShipOnRebuy',
            (int) $json['ShipPrice'],
            static::generateDetails($json),
            $json,
            ( (array_key_exists('SellShipId', $json)) ? $json['SellShipId'] : null )
        );

        // Sell ship
        $usersShipsModel    = new \Models_Users_Ships;
        $currentShipId      = static::$user->getShipById($json['SellShipId']);

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

        unset($usersShipsModel, $currentShipId);

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

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}