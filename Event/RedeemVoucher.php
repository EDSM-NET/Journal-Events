<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class RedeemVoucher extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add voucher redeem amount to commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'RedeemVoucher',
            (int) $json['Amount'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details            = array();
        $details['type']    = strtolower($json['Type']);

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