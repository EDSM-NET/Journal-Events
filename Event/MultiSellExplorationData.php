<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MultiSellExplorationData extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add exploration data sell price to commander credits.',
    ];



    public static function run($json)
    {
        if(array_key_exists('TotalEarnings', $json))
        {
            if($json['TotalEarnings'] > $json['BaseValue'])
            {
                $balance = (int) $json['TotalEarnings'];
            }
            else
            {
                $balance = (int) $json['BaseValue'];
            }
        }
        else
        {
            $balance = (int) $json['BaseValue'];
        }

        if($balance > 0)
        {
            $usersCreditsModel  = new \Models_Users_Credits;

            $isAlreadyStored    = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'MultiSellExplorationData')
                                  ->where('balance = ?', $balance)
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'SellExplorationData';
                $insert['balance']      = $balance;
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
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details                = array();

        $details['bonus']       = $json['Bonus'];
        $details['baseValue']   = $json['BaseValue'];

        $currentShipId          = static::findShipId($json);

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