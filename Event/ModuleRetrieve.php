<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ModuleRetrieve extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove module retrieve cost from commander credits.',
        '<span class="text-warning">Event is inserted only if cost is present and superior to 0.</span>',
    ];



    public static function run($json)
    {
        if(array_key_exists('Cost', $json) && $json['Cost'] > 0)
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['RetrievedItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['RetrievedItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'ModuleRetrieve')
                                  ->where('balance = ?', - (int) $json['Cost'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'ModuleRetrieve';
                $insert['balance']      = - (int) $json['Cost'];
                $insert['dateUpdated']  = $json['timestamp'];

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
        $details = array();

        if(array_key_exists('ShipID', $json))
        {
            $details['shipId'] = $json['ShipID'];
        }

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['RetrievedItem']);

        if(!is_null($outfittingType))
        {
            $details['type']  = $outfittingType;
        }

        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}