<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class LoadGame extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set credits to current value (Reason: Regulation).',
        'Update ship fuel level.',
    ];



    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'LoadGame')
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'LoadGame';
            $insert['balance']      = $json['Credits'];
            $insert['loan']         = $json['Loan'];
            $insert['dateUpdated']  = $json['timestamp'];

            $usersCreditsModel->insert($insert);

            unset($insert);
        }
        else
        {
            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';
        }

        unset($usersCreditsModel, $isAlreadyStored);

        // Update shipID
        if(array_key_exists('Ship', $json) && !in_array(strtolower($json['Ship']), static::$notShipTypes))
        {
            $isShip = \Alias\Ship\Type::getFromFd($json['Ship']);

            if(!is_null($isShip))
            {
                static::updateCurrentGameShipId($json['ShipID'], $json['timestamp']);

                $usersShipsModel    = new \Models_Users_Ships;
                $currentShipId      = static::$user->getShipById($json['ShipID']);

                // Update ship if needed
                if(!is_null($currentShipId))
                {
                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();

                    if(array_key_exists('FuelLevel', $json) && array_key_exists('FuelCapacity', $json))
                    {
                        if(!array_key_exists('fuelUpdated', $currentShip) || is_null($currentShip['fuelUpdated']) || strtotime($currentShip['fuelUpdated']) < strtotime($json['timestamp']))
                        {
                            $update['fuelMainLevel']     = max(0, $json['FuelLevel']);
                            $update['fuelUpdated']       = $json['timestamp'];
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
        }

        return static::$return;
    }
}