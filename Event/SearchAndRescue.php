<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SearchAndRescue extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add reward to commander credits.',
        'Remove item from commander cargo hold.',
    ];



    public static function run($json)
    {
        $currentItemId  = \Alias\Station\Commodity\Type::getFromFd($json['Name']);

        if(is_null($currentItemId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('\Alias\Station\Commodity\Type: ' . $json['Name']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        $isNewEntry = static::handleCredits(
            'SearchAndRescue',
            (int) $json['Reward'],
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
                    $update['total']        = max(0, $currentItem['total'] - $json['Count']);
                    $update['lastUpdate']   = $json['timestamp'];

                    $databaseModel->updateById($currentItem['id'], $update);

                    unset($update);
                }
            }

            unset($databaseModel, $currentItems);
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();
        $details['qty'] = $json['Count'];

        $commodityType = \Alias\Station\Commodity\Type::getFromFd($json['Name']);

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