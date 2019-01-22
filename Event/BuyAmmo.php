<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyAmmo extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove ammo cost from commander credits.',
    ];



    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'BuyAmmo')
                              ->where('balance = ?', - (int) $json['Cost'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'BuyAmmo';
            $insert['balance']      = - (int) $json['Cost'];
            $insert['dateUpdated']  = $json['timestamp'];

            $stationId = static::findStationId($json);

            if(!is_null($stationId))
            {
                $insert['refStation']   = $stationId;
            }

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
                $usersCreditsModel->updateById($isAlreadyStored->id, ['details' => $details]);
            }

            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';
        }

        unset($usersCreditsModel, $isAlreadyStored);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();
        $currentShipId  = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}