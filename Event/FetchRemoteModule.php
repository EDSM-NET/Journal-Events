<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FetchRemoteModule extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove module transfer price from the commander credits.',
    ];



    public static function run($json)
    {
        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['StoredItem']);

        if(is_null($outfittingType))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['StoredItem']);

            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Fix missing key in old journal
        if(!array_key_exists('TransferCost', $json) && array_key_exists('', $json))
        {
            $json['TransferCost'] = $json[''];
        }

        if($json['TransferCost'] > 0)
        {
            static::handleCredits(
                'FetchRemoteModule',
                - (int) $json['TransferCost'],
                static::generateDetails($json),
                $json,
                ( (array_key_exists('ShipID', $json)) ? $json['ShipID'] : null )
            );
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details = array();

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['StoredItem']);

        if(!is_null($outfittingType))
        {
            $details['type']  = $outfittingType;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}