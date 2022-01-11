<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ShipyardSwap extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update current shipID.',
    ];
    
    
    
    public static function run($json)
    {
        static::updateCurrentGameShipId($json['ShipID'], $json['timestamp']);
        
        return static::$return;
    }
}