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

        try
        {
            $usersCreditsModel->insert($insert);
        }
        catch(\Zend_Db_Exception $e)
        {
            // Based on unique index, this credit entry was already saved, check if it needs an update
            if(strpos($e->getMessage(), '1062 Duplicate') !== false)
            {
                $isAlreadyStored   = $usersCreditsModel->fetchRow(
                    $usersCreditsModel->select()
                                      ->where('refUser = ?', static::$user->getId())
                                      ->where('reason = ?', $reason)
                                      ->where('balance = ?', $balance)
                                      ->where('dateUpdated = ?', $json['timestamp'])
                );

                if(!is_null($isAlreadyStored))
                {
                    // Those fields cannot change
                    unset($insert['refUser'], $insert['reason'], $insert['balance'], $insert['dateUpdated']);

                    // Check if some fields are different, if not remove them
                    if($isAlreadyStored->details == $insert['details'])
                    {
                        unset($insert['details']);
                    }
                    if($isAlreadyStored->refStation == $insert['refStation'])
                    {
                        unset($insert['refStation']);
                    }
                    if($isAlreadyStored->refShip == $insert['refShip'])
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

        // Insert wasn't catched by the exception
        unset($usersCreditsModel, $isAlreadyStored, $insert);
        return true;
    }
}