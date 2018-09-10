<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Rank extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update the commander ranks.',
    ];
    
    
    
    public static function run($json)
    {
        $usersRanksModel    = new \Models_Users_Ranks;
        $currentRanks       = $usersRanksModel->getByRefUser(static::$user->getId());
        $lastRankUpdate     = strtotime('1WEEK AGO');
        
        if(!is_null($currentRanks) && array_key_exists('lastRankUpdate', $currentRanks) && !is_null($currentRanks['lastRankUpdate']))
        {
            $lastRankUpdate = strtotime($currentRanks['lastRankUpdate']);
        }
        
        if($lastRankUpdate < strtotime($json['timestamp']))
        {
            $insert                         = array();
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['combat'] != $json['Combat']))
            {
                $insert['combat'] = (int) $json['Combat'];
            }
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['trader'] != $json['Trade']))
            {
                $insert['trader'] = (int) $json['Trade'];
            }
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['explorer'] != $json['Explore']))
            {
                $insert['explorer'] = (int) $json['Explore'];
            }
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['empire'] != $json['Empire']))
            {
                $insert['empire'] = (int) $json['Empire'];
            }
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['federation'] != $json['Federation']))
            {
                $insert['federation'] = (int) $json['Federation'];
            }
            
            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['CQC'] != $json['CQC']))
            {
                $insert['CQC'] = (int) $json['CQC'];
            }
            
            if(count($insert) > 0)
            {
                $insert['lastRankUpdate']       = $json['timestamp'];
                
                try
                {
                    if(!is_null($currentRanks))
                    {
                        $usersRanksModel->updateByRefUser(
                            static::$user->getId(),
                            $insert
                        );
                    }
                    else
                    {
                        $insert['refUser'] = static::$user->getId();
                        $usersRanksModel->insert($insert);
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
        
        unset($usersRanksModel, $currentRanks);
        
        return static::$return;
    }
}