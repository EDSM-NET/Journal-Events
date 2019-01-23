<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class ModuleSell extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add module sell price to commander credits.',
        '<span class="text-warning">Event is inserted only if cost is present and superior to 0.</span>',
    ];



    public static function run($json)
    {
        if($json['SellPrice'] > 0 && !in_array($json['SellItem'], static::$excludedOutfitting))
        {
            $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

            if(is_null($outfittingType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['SellItem']);

                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            static::handleCredits(
                'ModuleSell',
                (int) $json['SellPrice'],
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

        $outfittingType = \Alias\Station\Outfitting\Type::getFromFd($json['SellItem']);

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