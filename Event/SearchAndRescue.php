<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SearchAndRescue extends Event
{
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

        $usersCreditsModel = new \Models_Users_Credits;

        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'SearchAndRescue')
                              ->where('balance = ?', (int) $json['Reward'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );

        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'SearchAndRescue';
            $insert['balance']      = (int) $json['Reward'];
            $insert['dateUpdated']  = $json['timestamp'];

            // Generate details
            $details = static::generateDetails($json);
            if(!is_null($details)){ $insert['details'] = $details; }

            $usersCreditsModel->insert($insert);

            unset($insert);

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
        else
        {
            $details = static::generateDetails($json);

            if($isAlreadyStored->details != $details)
            {
                $usersCreditsModel->updateById(
                    $isAlreadyStored->id,
                    [
                        'details' => $details,
                    ]
                );
            }

            static::$return['msgnum']   = 101;
            static::$return['msg']      = 'Message already stored';
        }

        unset($usersCreditsModel, $isAlreadyStored);

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();
        $details['qty'] = $json['Count'];
        $currentShipId  = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }

        $commodityType = \Alias\Station\Commodity\Type::getFromFd($json['Name']);

        if(!is_null($commodityType))
        {
            $details['type']  = $commodityType;
        }

        $stationId = static::findStationId($json);

        if(!is_null($stationId))
        {
            $details['stationId'] = $stationId;
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}