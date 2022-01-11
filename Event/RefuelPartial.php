<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class RefuelPartial extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove refuel cost.',
        '<span class="text-danger"><ins>TODO:</ins> Refuel ship if newer than the stored value and transient state available.</span>',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'RefuelPartial',
            - (int) $json['Cost'],
            static::generateDetails($json),
            $json
        );

        //TODO: Refuel ship


        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}