<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyDrones extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove drone(s) cost from commander credits.',
    ];

    public static function run($json)
    {
        static::handleCredits(
            'BuyDrones',
            - (int) $json['TotalCost'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details            = array();
        $details['type']    = $json['Type'];
        $details['qty']     = $json['Count'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}