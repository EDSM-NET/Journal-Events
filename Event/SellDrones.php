<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SellDrones extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add drone(s) sell price to commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'SellDrones',
            (int) $json['TotalSale'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

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