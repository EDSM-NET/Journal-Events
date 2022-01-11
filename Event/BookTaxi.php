<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BookTaxi extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove book taxi cost from commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'BookTaxi',
            - (int) $json['Cost'],
            null,
            $json
        );

        return static::$return;
    }
}