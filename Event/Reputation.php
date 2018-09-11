<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Reputation extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update the commander reputation with superpowers.',
    ];
    
    
    
    public static function run($json)
    {
        $usersReputationsModel      = new \Models_Users_Reputations;
        $currentReputations         = $usersReputationsModel->getByRefUser(static::$user->getId());
        $lastReputationUpdate       = strtotime('1WEEK AGO');
        
        if(!is_null($currentReputations) && array_key_exists('lastReputationUpdate', $currentReputations))
        {
            $lastReputationUpdate = strtotime($currentReputations['lastReputationUpdate']);
        }
        
        if($lastReputationUpdate < strtotime($json['timestamp']))
        {
            $insert = array();
            
            if(array_key_exists('', $json))
            {
                if(is_null($currentReputations) || (!is_null($currentReputations) && $currentReputations['empire'] != $json['Empire']))
                {
                    $insert['empire'] = (int) $json['Empire'];
                }
            }
            
            if(array_key_exists('Federation', $json))
            {
                if(is_null($currentReputations) || (!is_null($currentReputations) && $currentReputations['federation'] != $json['Federation']))
                {
                    $insert['federation'] = (int) $json['Federation'];
                }
            }
            
            if(array_key_exists('Independent', $json))
            {
                if(is_null($currentReputations) || (!is_null($currentReputations) && $currentReputations['independent'] != $json['Independent']))
                {
                    $insert['independent'] = (int) $json['Independent'];
                }
            }
            
            if(array_key_exists('Alliance', $json))
            {
                if(is_null($currentReputations) || (!is_null($currentReputations) && $currentReputations['alliance'] != $json['Alliance']))
                {
                    $insert['alliance'] = (int) $json['Alliance'];
                }
            }
            
            // Only make updates when values are different
            if(count($insert) > 0)
            {
                $insert['lastReputationUpdate'] = $json['timestamp'];
                
                try
                {
                    if(!is_null($currentReputations))
                    {
                        $usersReputationsModel->updateByRefUser(static::$user->getId(), $insert);
                    }
                    else
                    {
                        $insert['refUser'] = static::$user->getId();
                        $usersReputationsModel->insert($insert);
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
        
        unset($usersReputationsModel, $currentReputations);
        
        return static::$return;
    }
}