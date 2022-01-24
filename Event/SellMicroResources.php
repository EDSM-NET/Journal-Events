<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SellMicroResources extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Add micro resources sell price to commander credits.',
        'Remove micro resources from commander backpack.',
    ];



    public static function run($json)
    {
        if(array_key_exists('MicroResources', $json))
        {
            $aliasClass     = 'Alias\Commander\MicroResource\Type';
            
            foreach($json['MicroResources'] AS $microResource)
            {
                // Check if MicroResource is known in EDSM
                $currentItemId = $aliasClass::getFromFd($microResource['Name']);

                if(is_null($currentItemId))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $microResource['Name']);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }
            }
            
            $isNewEntry = static::handleCredits(
                'SellMicroResources',
                (int) $json['Price'],
                null,
                $json
            );

            if($isNewEntry === true)
            {
                $databaseModel  = new \Models_Users_MicroResources;        
                $currentItems   = $databaseModel->getByRefUser(static::$user->getId());
                
                foreach($json['MicroResources'] AS $microResource)
                {
                    if(array_key_exists('Name', $microResource) && array_key_exists('Count', $microResource))
                    {
                        $currentItemId = $aliasClass::getFromFd($microResource['Name']);
                        
                        // Find the current item ID
                        $currentItem   = null;
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
                                $update['total']        = max(0, $currentItem['total'] - $microResource['Count']);
                                $update['lastUpdate']   = $json['timestamp'];

                                $databaseModel->updateById(
                                    $currentItem['id'],
                                    $update
                                );

                                unset($update);
                            }
                            else
                            {
                                static::$return['msgnum']   = 102;
                                static::$return['msg']      = 'Message older than the stored one';
                            }
                        }
                    }
                }
            }
        }

        return static::$return;
    }
}