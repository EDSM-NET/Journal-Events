<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardBuy extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove ship cost from commander credits.',
        '<span class="text-warning">Ship creation is done in the <code>Loadout</code> event.</span>',
        'Add sell price if old ship is sold.',
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
            'ShipyardBuy',
            - (int) $json['ShipPrice'],
            static::generateDetails($json),
            $json
        );

        // Old ship sold?
        if(array_key_exists('SellPrice', $json) && $json['SellPrice'] > 0)
        {
            $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);

            if(is_null($shipType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Ship\Type : ' . $json['SellOldShip']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            static::handleCredits(
                'ShipyardSell',
                (int) $json['SellPrice'],
                static::generateDetailsSell($json),
                $json,
                ( (array_key_exists('SellOldShip', $json)) ? $json['SellOldShip'] : null )
            );

            // Sell ship
            $usersShipsModel    = new \Models_Users_Ships;
            $currentShipId      = static::$user->getShipById($json['SellShipID']);

            if(!is_null($currentShipId))
            {
                $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);

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

            unset($usersShipsModel, $isAlreadyStored);
        }

        unset($usersCreditsModel);

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

    static private function generateDetailsSell($json)
    {
        $details = array();

        $shipType = \Alias\Ship\Type::getFromFd($json['SellOldShip']);

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