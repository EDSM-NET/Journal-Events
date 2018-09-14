<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CommunityGoalJoin extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Join commander to community goal.',
    ];
    
    
    
    public static function run($json)
    {
        $cgId = static::findCommunityGoalId($json);
        
        if(!is_null($cgId))
        {
            $usersCommunityGoalsModel   = new \Models_Users_CommunityGoals;
            $currentUserCommunityGoals  = $usersCommunityGoalsModel->getByRefUser(static::$user->getId());
            $currentUserCG              = null;
            
            if(!is_null($currentUserCommunityGoals))
            {
                foreach($currentUserCommunityGoals AS $temp)
                {
                    if($temp['refCG'] == $cgId)
                    {
                        $currentUserCG = $temp;
                        break;
                    }
                }
            }
            
            // Re-Join when found
            if(!is_null($currentUserCG))
            {
                $update = array();
                
                if(!array_key_exists('dateJoined', $currentUserCG) || is_null($currentUserCG['dateJoined']) || strtotime($currentUserCG['dateJoined']) > strtotime($json['timestamp']))
                {
                    $update['dateJoined'] = $json['timestamp'];
                }
                
                if(array_key_exists('dateDiscarded', $currentUserCG) && (!is_null($currentUserCG['dateDiscarded']) || strtotime($currentUserCG['dateDiscarded']) < strtotime($json['timestamp'])))
                {
                    $update['dateDiscarded'] = null;
                }
                
                if(count($update) > 0)
                {
                    $usersCommunityGoalsModel->updateById($currentUserCG['id'], $update);
                }
                
                unset($update);
            }
            else
            {
                try
                {
                    $insert                     = array();
                    $insert['refCG']            = $cgId;
                    $insert['refUser']          = static::$user->getId();
                    $insert['dateJoined']       = $json['timestamp'];
                    $insert['dateLastUpdated']  = $json['timestamp'];
                    
                    $usersCommunityGoalsModel->insert($insert);
                    
                    unset($insert);
                }
                catch(\Zend_Db_Exception $e)
                {
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        static::$return['msgnum']   = 101;
                        static::$return['msg']      = 'Message already stored';
                    }
                }
            }
            
            unset($usersCommunityGoalsModel, $currentUserCommunityGoals);
        }
        else
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';
            
            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);
            
            return static::$return;
        }
        
        return static::$return;
    }
    
    
    
    private static function findCommunityGoalId($json)
    {
        if(array_key_exists('CGID', $json))
        {
            return $json['CGID'];
        }
        else
        {
            $communityGoalsModel    = new \Models_CommunityGoals;
            $json['Name']           = trim($json['Name']);
            
            $currentCG = $communityGoalsModel->fetchRow(
                $communityGoalsModel->select()
                                    ->where('title = ?', $json['Name'])
                                    ->orWhere('alternateTitle LIKE ?', '%' . $json['Name'] . '%')
            );
            
            unset($communityGoalsModel);
            
            if(!is_null($currentCG))
            {
                return $currentCG->id;
            }
        }
        
        return null;
    }
}