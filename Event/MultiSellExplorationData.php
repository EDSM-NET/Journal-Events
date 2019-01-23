<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MultiSellExplorationData extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add exploration data sell price to commander credits.',
    ];



    public static function run($json)
    {
        if(array_key_exists('TotalEarnings', $json))
        {
            if($json['TotalEarnings'] > $json['BaseValue'])
            {
                $balance = (int) $json['TotalEarnings'];
            }
            else
            {
                $balance = (int) $json['BaseValue'];
            }
        }
        else
        {
            $balance = (int) $json['BaseValue'];
        }

        if($balance > 0)
        {
            static::handleCredits(
                'MultiSellExplorationData',
                $balance,
                static::generateDetails($json),
                $json
            );
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details                = array();

        $details['bonus']       = $json['Bonus'];
        $details['baseValue']   = $json['BaseValue'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}