<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSSSignalDiscovered extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Check for FSSSignalDiscovered.',
    ];



    public static function run($json)
    {
        // Discard if expired!
        if(array_key_exists('TimeRemaining', $json))
        {
            $timeExpired  = strtotime($json['timestamp']) + $json['TimeRemaining'];
            $timeExpired -= 60; // No need to store if expiring soon...

            if(time() > $timeExpired)
            {
                return static::$return;
            }
        }

        // Save until further processing
        $json['isError']            = 1;
        \Journal\Event::run($json);

        return static::$return;
    }
}