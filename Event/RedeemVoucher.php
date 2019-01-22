<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class RedeemVoucher extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add voucher redeem amount to commander credits.',
    ];



    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'RedeemVoucher')
                              ->where('balance = ?', (int) $json['Amount'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'RedeemVoucher';
            $insert['balance']      = (int) $json['Amount'];
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
        $details            = array();
        $details['type']    = strtolower($json['Type']);
        $currentShipId      = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }

        // Convert single faction event to array
        if(array_key_exists('Faction', $json) && !array_key_exists('Factions', $json))
        {
            if(!empty($json['Faction']))
            {
                $json['Factions'] = [
                    [
                        'Faction'   => $json['Faction'],
                        'Amount'    => $json['Amount'],
                    ]
                ];
            }
        }

        if(array_key_exists('Factions', $json))
        {
            $factionsModel       = new \Models_Factions;
            $details['factions'] = array();

            foreach($json['Factions'] AS $faction)
            {
                if(!empty($faction['Faction']))
                {
                    $allegiance = \Alias\System\Allegiance::getFromFd($faction['Faction']);

                    if(!is_null($allegiance))
                    {
                        $currentFactionId = strtolower($faction['Faction']);
                    }
                    else
                    {
                        $currentFaction = $factionsModel->getByName($faction['Faction']);

                        if(!is_null($currentFaction))
                        {
                            $currentFactionId = (int) $currentFaction['id'];
                        }
                        else
                        {
                            $currentFactionId = (int) $factionsModel->insert(['name' => $faction['Faction']]);
                        }
                    }


                    if(!array_key_exists($currentFactionId, $details['factions']))
                    {
                        $details['factions'][$currentFactionId] = 0;
                    }
                    $details['factions'][$currentFactionId] += $faction['Amount'];
                }
            }
        }

        if(array_key_exists('BrokerPercentage', $json))
        {
            $details['brokerPercentage'] = $json['BrokerPercentage'];
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}