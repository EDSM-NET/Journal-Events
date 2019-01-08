<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ApproachSettlement extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Check for Planetary Settlement Latitude/Longitude.',
    ];



    public static function run($json)
    {
        if(array_key_exists('Latitude', $json) && array_key_exists('Longitude', $json))
        {
            $stationId = static::findStationId($json);

            if(!is_null($stationId))
            {
                $station            = \EDSM_System_Station::getInstance($stationId);
                $haveCoordinates    = $station->getCoordinates();

                if(is_null($haveCoordinates))
                {
                    // Save until further processing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);
                }
            }
        }

        return static::$return;
    }
}