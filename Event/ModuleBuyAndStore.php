<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ModuleBuyAndStore extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove module cost from commander credits.',
        '<span class="text-warning">Event is inserted only if cost is superior to 0.</span>',
        'Add sell price if old module is sold.',
    ];



    public static function run($json)
    {
        if($json['BuyPrice'] > 0)
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['BuyItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['BuyItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            static::handleCredits(
                'ModuleBuyAndStore',
                - (int) $json['BuyPrice'],
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

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['BuyItem']);

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