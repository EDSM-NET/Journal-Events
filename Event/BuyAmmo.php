<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyAmmo extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove ammo cost from commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'BuyAmmo',
            - (int) $json['Cost'],
            null,
            $json
        );

        return static::$return;
    }
}