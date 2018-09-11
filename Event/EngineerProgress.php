<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class EngineerProgress extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update engineer rank',
        'Update engineer stage',
    ];
    
    
    
    public static function run($json)
    {
        if(!array_key_exists('Engineer', $json))
        {
            static::$return['msgnum']   = 500;
            static::$return['msg']      = 'Message needs to be checked';
            
            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        // Check if Engineer is known in EDSM
        $engineerId        = \Alias\Station\Engineer::getFromFd($json['Engineer']);
        
        if(is_null($engineerId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            \EDSM_Api_Logger_Alias::log(
                'Alias\Station\Engineer: ' . $json['Engineer'] . ' (Sofware#' . static::$softwareId . ')',
                [
                    'file'  => __FILE__,
                    'line'  => __LINE__,
                ]
            );
            
            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        $usersEngineersModel    = new \Models_Users_Engineers;
        $currentEngineers       = $usersEngineersModel->getByRefUser(static::$user->getId());
        $currentEngineer        = null;
        $lastEngineerUpdate     = strtotime('5YEAR AGO');
        
        // Try to find current engineer in the engineers array
        if(!is_null($currentEngineers) && count($currentEngineers) > 0)
        {
            foreach($currentEngineers AS $engineer)
            {
                if($engineer['refEngineer'] == $engineerId)
                {
                    $currentEngineer = $engineer;
                    break;
                }
            }
        }
        
        if(!is_null($currentEngineer) && array_key_exists('lastUpdate', $currentEngineer))
        {
            $lastEngineerUpdate = strtotime($currentEngineer['lastUpdate']);
        }
        
        if($lastEngineerUpdate < strtotime($json['timestamp']))
        {
            $insert                         = array();
            
            if(array_key_exists('Rank', $json))
            {
                if(is_null($currentEngineer) || (!is_null($currentEngineer) && $currentEngineer['rank'] < $json['Rank']))
                {
                    $insert['rank'] = (int) $json['Rank'];
                }
            }
            
            if(array_key_exists('Progress', $json))
            {
                if(is_null($currentEngineer) || (!is_null($currentEngineer) && $currentEngineer['stageProgress'] != $json['Progress']))
                {
                    $insert['stageProgress'] = $json['Progress'];
                }
            }
            else
            {
                $insert['stageProgress'] = new \Zend_Db_Expr('NULL');
            }
            
            if(count($insert) > 0)
            {
                $insert['lastUpdate']       = $json['timestamp'];
                
                try
                {
                    if(!is_null($currentEngineer))
                    {
                        $usersEngineersModel->updateById($currentEngineer['id'], $insert);
                    }
                    else
                    {
                        $insert['refUser']      = static::$user->getId();
                        $insert['refEngineer']  = $engineerId;
                        
                        $usersEngineersModel->insert($insert);
                    }
                }
                catch(\Zend_Db_Exception $e)
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();
                    $json['isError']            = 1;
                    
                    \Journal\Event::run($json);
                }
            }
            
            unset($insert);
        }
        else
        {
            static::$return['msgnum']   = 102;
            static::$return['msg']      = 'Message older than the stored one';
        }
        
        unset($usersEngineersModel, $currentEngineers);
        
        return static::$return;
    }
}