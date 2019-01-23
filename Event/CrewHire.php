<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CrewHire extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove the crew hiring cost from the commander credits.',
    ];



    public static function run($json)
    {
        if($json['Cost'] > 0)
        {
            static::handleCredits(
                'CrewHire',
                - (int) $json['Cost'],
                static::generateDetails($json),
                $json
            );
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        if(array_key_exists('Name', $json))
        {
            $details['name']        = $json['Name'];
        }

        if(array_key_exists('CombatRank', $json))
        {
            $details['combatRank']  = $json['CombatRank'];
        }

        if(array_key_exists('Faction', $json))
        {
            $factionsModel  = new \Models_Factions;
            $factionId      = $factionsModel->getByName($json['Faction']);

            if(!is_null($factionId))
            {
                $details['refFaction'] = (int) $factionId['id'];
            }
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}