<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class NpcCrewPaidWage extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove the crew wages from the commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'NpcCrewPaidWage',
            - (int) $json['Amount'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details                = array();
        $details['npcCrewId']   = $json['NpcCrewId'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}