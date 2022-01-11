<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class RestockVehicle extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove vehicule price from commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'RestockVehicle',
            - (int) $json['Cost'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        $details['qty']     = $json['Count'];
        $details['type']    = $json['Type'];
        $details['loadout'] = $json['Loadout'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}