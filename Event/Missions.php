<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Missions extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update Missions status.',
        '<span class="text-warning">We are still looking of what to do exactly from that event ;)</span>',
    ];
    
    
    
    public static function run($json)
    {
        $count          = 0;
        $missionsModel  = new \Models_Users_Missions;
        
        if(array_key_exists('Active', $json))
        {
            // Do nothing on active missions, not enought information to insert
        }
        
        if(array_key_exists('Failed', $json))
        {
            if(count($json['Failed']) > 0)
            {
                foreach($json['Failed'] AS $mission)
                {
                    $currentMission = $missionsModel->getById($mission['MissionID']);
                    
                    if(!is_null($currentMission))
                    {
                        if($currentMission['status'] == 'Failed')
                        {
                            continue;
                        }
                        else
                        {
                            /*
                            if(defined('JOURNAL_DEBUG') && JOURNAL_DEBUG === true)
                            {
                                \Zend_Debug::dump($currentMission);
                            }
                            */
                        }
                    }
                    
                    // We don't have it yet, count to reparse
                    $count++;
                }
            }
        }
        
        if(array_key_exists('Complete', $json))
        {
            if(count($json['Complete']) > 0)
            {
                foreach($json['Complete'] AS $mission)
                {
                    $currentMission = $missionsModel->getById($mission['MissionID']);
                    
                    if(!is_null($currentMission))
                    {
                        if($currentMission['status'] == 'Completed')
                        {
                            continue;
                        }
                        else
                        {
                            /*
                            if(defined('JOURNAL_DEBUG') && JOURNAL_DEBUG === true)
                            {
                                \Zend_Debug::dump($currentMission);
                            }
                            */
                        }
                    }
                    
                    // We don't have it yet, count to reparse
                    $count++;
                }
            }
        }
        
        unset($missionsModel);
        
        if($count > 0)
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            $json['isError'] = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        return static::$return;
    }
}