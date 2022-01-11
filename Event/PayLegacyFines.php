<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class PayLegacyFines extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove legacy fines from commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'PayLegacyFines',
            - (int) $json['Amount'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        if(array_key_exists('BrokerPercentage', $json))
        {
            $details['brokerPercentage'] = $json['BrokerPercentage'];
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}