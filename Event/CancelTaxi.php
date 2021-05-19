<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CancelTaxi extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Refund book taxi cost to commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'CancelTaxi',
            (int) $json['Refund'],
            null,
            $json
        );

        return static::$return;
    }
}