<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ModuleSell extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add module sell price to commander credits.',
        '<span class="text-warning">Event is inserted only if cost is present and superior to 0.</span>',
    ];



    public static function run($json)
    {
        if($json['SellPrice'] > 0 && !in_array($json['SellItem'], static::$excludedOutfitting))
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['SellItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ModuleSell')
                                  ->where('balance = ?', (int) $json['SellPrice'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ModuleSell';
                $insert['balance']      = (int) $json['SellPrice'];
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
        }

        unset($usersCreditsModel, $isAlreadyStored);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details = array();

        if(array_key_exists('ShipID', $json))
        {
            $details['shipId'] = $json['ShipID'];
        }

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

        if(!is_null($outfittingType))
        {
            $details['type']  = $outfittingType;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}