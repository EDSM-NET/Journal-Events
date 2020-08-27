<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;

class CarrierJump extends FSDJump
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set commander position in flight logs.',
    ];
}