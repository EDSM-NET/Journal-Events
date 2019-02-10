<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Common;

trait Credits
{
    protected static function handleCredits($reason, $balance, $details, $json, $forcedShipId = null)
    {
        $usersCreditsModel      = new \Models_Users_Credits;

        $insert                 = array();
        $insert['refUser']      = static::$user->getId();
        $insert['reason']       = $reason;
        $insert['balance']      = $balance;
        $insert['dateUpdated']  = $json['timestamp'];

        $stationId = static::findStationId($json);
        if(!is_null($stationId))
        {
            $insert['refStation']   = $stationId;
        }

        if(!is_null($forcedShipId))
        {
            $insert['refShip'] = $forcedShipId;
        }
        else
        {
            $currentShipId  = static::findShipId($json);
            if(!is_null($currentShipId))
            {
                $insert['refShip'] = $currentShipId;
            }
        }

        // Check for valid input (ShipyardSell in particular return the Type instead of the ID)
        if(array_key_exists('refShip', $insert) && (array_key_exists(strtolower($insert['refShip']), \Alias\Ship\Type::getAllFromFd()) || $insert['refShip'] < 0 || $insert['refShip'] > 65535))
        {
            $insert['refShip'] = null;
        }

        if(!is_null($details))
        {
            $insert['details'] = $details;
        }

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', $reason)
                              ->where('balance = ?', $balance)
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $usersCreditsModel->insert($insert);
        }
        else
        {
            // Those fields cannot change
            unset($insert['refUser'], $insert['reason'], $insert['balance'], $insert['dateUpdated']);

            // Check if some fields are different, if not remove them
            if(array_key_exists('details', $insert) && $isAlreadyStored->details == $insert['details'])
            {
                unset($insert['details']);
            }
            if(array_key_exists('refStation', $insert) && $isAlreadyStored->refStation == $insert['refStation'])
            {
                unset($insert['refStation']);
            }
            if(array_key_exists('refShip', $insert) && $isAlreadyStored->refShip == $insert['refShip'])
            {
                unset($insert['refShip']);
            }

            if(count($insert) > 0)
            {
                $usersCreditsModel->updateById($isAlreadyStored->id, $insert);
            }

            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';

            // Return FALSE for an update
            unset($usersCreditsModel, $isAlreadyStored, $insert);
            return false;
        }

        unset($usersCreditsModel, $isAlreadyStored, $insert);
        return true;
    }
}