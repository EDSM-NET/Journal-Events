<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

/*
 * Parameters:
•	SpawningState: the BGS state that triggered this event, if relevant
•	SpawningFaction: the minor faction, if relevant
•	TimeRemaining: remaining lifetime in seconds, if relevant
•	SystemAddress
•	ThreatLevel (if a USS)
•	USSType: (if a USS) – same types as in USSDrop event

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
        // Skip before Q4 events...
        if(strtotime($json['timestamp']) < strtotime('2018-12-11 12:00:00'))
        {
            return static::$return;
        }

        // Discard if flagged as station
        if(array_key_exists('IsStation', $json) && $json['IsStation'] == true)
        {
            return static::$return;
        }

        // Discard Volatile events...
        $volatileEvents = array(
            '$MULTIPLAYER_SCENARIO42_TITLE;',   // Nav Beacon

            '$MULTIPLAYER_SCENARIO77_TITLE;',   // Resource Extraction Site [Low]
            '$MULTIPLAYER_SCENARIO78_TITLE;',   // Resource Extraction Site [High]
            '$MULTIPLAYER_SCENARIO79_TITLE;',   // Resource Extraction Site [Hazardous]

            '$MULTIPLAYER_SCENARIO81_TITLE;',   // Salvageable Wreckage

            '$Warzone_PointRace_Low;',          // Conflict Zone [Low Intensity]
            '$Warzone_PointRace_Med;',          // Conflict Zone [Medium Intensity]
            '$Warzone_PointRace_High;',         // Conflict Zone [High Intensity]

            '$Aftermath_Large;',                // Distress Call
        );
        if(array_key_exists('SignalName', $json) && in_array($json['SignalName'], $volatileEvents))
        {
            return static::$return;
        }

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

        // Find system
        $systemId = static::findSystemId($json);

        if(!is_null($systemId))
        {
            $currentSystem = \Component\System::getInstance($systemId);

            // Handle messages with a localised signal name
            if(array_key_exists('SignalName', $json) && array_key_exists('SignalName_Localised', $json))
            {
                $refType = \Alias\System\FSSSignal::getFromFd($json['SignalName']);

                if(is_null($refType))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    //\EDSM_Api_Logger_Mission::log('Alias\System\FSSSignal: ' . $json['SignalName']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }

                // So is there any message of interest?
            }
            else
            {
                // Sometime station flag isn't working...
                $stations = $currentSystem->getStations();

                if(!is_null($stations))
                {
                    foreach($stations AS $station)
                    {
                        $station = \EDSM_System_Station::getInstance($station['id']);

                        if($station->getName() == $json['SignalName'])
                        {
                            return static::$return; // Discard it!
                        }
                    }
                }

                // May be just store name and sort later?
            }

            // Last we obviously missing something...
            // Save until further processing
            $json['isError']            = 1;
            \Journal\Event::run($json);
        }

        return static::$return;
    }
}