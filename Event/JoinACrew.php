<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class JoinACrew extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Only used to set the crew session state.',
    ];
    
    
    
    public static function run($json)
    {
        return static::$return;
    }
}