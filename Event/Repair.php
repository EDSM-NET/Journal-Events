<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Repair extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove repair cost from commander credits.',
    ];



    public static function run($json)
    {
        if($json['Cost'] > 0)
        {
            static::handleCredits(
                'Repair',
                - (int) $json['Cost'],
                static::generateDetails($json),
                $json
            );
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        if(array_key_exists('Item', $json))
        {
            if(in_array(strtolower($json['Item']), ['all', 'wear', 'hull', 'paint']))
            {
                $details['type'] = strtolower($json['Item']);
            }
            else
            {
                $outfittingType     = \Alias\Station\Outfitting\Type::getFromFd($json['Item']);
                $details['type']    = 'module';

                if(!is_null($outfittingType))
                {
                    $details['item'] = $outfittingType;
                }
                elseif(!in_array($json['Item'], static::$excludedOutfitting))
                {
                    \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $json['Item']);
                }
            }
        }
        if(array_key_exists('Items', $json))
        {
            $details['items'] = [];

            foreach($json['Items'] AS $item)
            {
                if(in_array(strtolower($item), ['all', 'wear', 'hull', 'paint']))
                {
                    $details['items'][] = strtolower($item);
                }
                else
                {
                    $outfittingType     = \Alias\Station\Outfitting\Type::getFromFd($item);

                    if(!is_null($outfittingType))
                    {
                        $details['items'][] = ['module' => $outfittingType];
                    }
                    elseif(!in_array($item, static::$excludedOutfitting))
                    {
                        \EDSM_Api_Logger_Alias::log('Alias\Station\Outfitting\Type : ' . $item);
                    }
                }
            }
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}