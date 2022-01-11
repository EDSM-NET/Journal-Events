<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MarketBuy extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove commodity buy price from commander credits.',
        'Add commodity to commander cargo hold.',
    ];



    public static function run($json)
    {
        $currentItemId  = \Alias\Station\Commodity\Type::getFromFd($json['Type']);

        if(is_null($currentItemId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('\Alias\Station\Commodity\Type: ' . $json['Type']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        $isNewEntry = static::handleCredits(
            'MarketBuy',
            - (int) $json['TotalCost'],
            static::generateDetails($json),
            $json
        );

        if($isNewEntry === true)
        {
            // UPDATE CARGO HOLD
            $databaseModel  = new \Models_Users_Cargo;

            // Find the current item ID
            $currentItem   = null;
            $currentItems  = $databaseModel->getByRefUser(static::$user->getId());

            if(!is_null($currentItems))
            {
                foreach($currentItems AS $tempItem)
                {
                    if($tempItem['type'] == $currentItemId)
                    {
                        $currentItem = $tempItem;
                        break;
                    }
                }
            }

            // If we have the line, update else, wait for the startUp events
            if(!is_null($currentItem))
            {
                if(strtotime($currentItem['lastUpdate']) < strtotime($json['timestamp']))
                {
                    $update                 = array();
                    $update['total']        = ($currentItem['total'] + $json['Count']);
                    $update['lastUpdate']   = $json['timestamp'];

                    $databaseModel->updateById($currentItem['id'], $update);

                    unset($update);
                }
            }

            unset($databaseModel);
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();
        $details['qty'] = $json['Count'];

        $commodityType = \Alias\Station\Commodity\Type::getFromFd($json['Type']);

        if(!is_null($commodityType))
        {
            $details['type']  = $commodityType;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}