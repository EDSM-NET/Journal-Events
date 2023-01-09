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
        'Update Planetary Settlement Latitude/Longitude.',
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
                $stationsModel      = new \Models_Stations;

                if(is_null($haveCoordinates))
                {
                    $stationsModel->updateById(
                        $station->getId(),
                        [
                            'bodyLatitude'  => $json['Latitude'],
                            'bodyLongitude' => $json['Longitude'],
                        ]
                    );
                }
                else
                {
                    if(!is_null($station->getUpdateTime()) && strtotime($station->getUpdateTime()) <= strtotime($json['timestamp']))
                    {
                        if($haveCoordinates['latitude'] !== $json['Latitude'] || $haveCoordinates['longitude'] !== $json['Longitude'])
                        {
                            $stationsModel->updateById(
                                $station->getId(),
                                [
                                    'bodyLatitude'  => $json['Latitude'],
                                    'bodyLongitude' => $json['Longitude'],
                                ]
                            );
                        }
                    }
                }
            }
        }

        return static::$return;
    }
}