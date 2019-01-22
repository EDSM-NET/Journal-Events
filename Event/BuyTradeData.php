<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyTradeData extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove trade data buy price from commander credits.',
    ];



    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'BuyTradeData')
                              ->where('balance = ?', - (int) $json['Cost'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $insert = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'BuyTradeData';
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

        if(array_key_exists('System', $json))
        {
            $systemsModel   = new \Models_Systems;
            $systemName     = $json['System'];
            $currentSystem  = $systemsModel->getByName($systemName);

            if(!is_null($currentSystem))
            {
                $currentSystem = \Component\System::getInstance($currentSystem['id']);

                // Follow merged systems
                if($currentSystem->isHidden() === true)
                {
                    $mergedTo = $currentSystem->getMergedTo();

                    if(!is_null($mergedTo))
                    {
                        // Switch systems when they have been renamed
                        $currentSystem = \Component\System::getInstance($mergedTo);
                    }
                    else
                    {
                        $details['system'] = $systemName;
                    }
                }

                if(!array_key_exists('system', $details))
                {
                    // Only grab name on duplicate because we do not have coordinates
                    $duplicates = $currentSystem->getDuplicates();
                    if(!is_null($duplicates) && is_array($duplicates) && count($duplicates) > 0)
                    {
                        $details['system'] = $systemName;
                    }
                    else
                    {
                        $details['system'] = $currentSystem->getId();
                    }
                }
            }
            else
            {
                $details['system'] = $systemName;
            }

            unset($systemsModel, $systemName, $currentSystem);
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}