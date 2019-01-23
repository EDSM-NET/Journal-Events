<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardTransfer extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove ship trasnfer cost from commander credits.',
        'Update transfered ship parking.',
    ];



    public static function run($json)
    {
        if($json['TransferPrice'] > 0)
        {
            static::handleCredits(
                'ShipyardTransfer',
                - (int) $json['TransferPrice'],
                null,
                $json,
                ( (array_key_exists('ShipID', $json)) ? $json['ShipID'] : null )
            );
        }

        // Update ship parking
        $usersShipsModel    = new \Models_Users_Ships;
        $currentShipId      = static::$user->getShipById($json['ShipID']);

        if(!is_null($currentShipId))
        {
            $currentShip        = $usersShipsModel->getById($currentShipId);
            $update             = array();

            $transferFinishedAt = strtotime($json['timestamp']);

            if(array_key_exists('TransferTime', $json))
            {
                $transferFinishedAt += $json['TransferTime'];
            }

            if(!array_key_exists('locationUpdated', $currentShip) || is_null($currentShip['locationUpdated']) || strtotime($currentShip['locationUpdated']) < $transferFinishedAt)
            {
                $stationId = static::findStationId($json);

                if(!is_null($stationId))
                {
                    $station                    = \EDSM_System_Station::getInstance($stationId);

                    $update['refSystem']        = $station->getSystem()->getId();
                    $update['refStation']       = $station->getId();
                    $update['locationUpdated']  = date('Y-m-d H:i:s', $transferFinishedAt);
                }
            }

            if(count($update) > 0)
            {
                $usersShipsModel->updateById($currentShipId, $update);
            }

            unset($currentShip, $update);
        }

        unset($usersShipsModel);

        return static::$return;
    }
}