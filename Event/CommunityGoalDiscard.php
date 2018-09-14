<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CommunityGoalDiscard extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Discard commander from community goal.',
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
            
            // Discard when found
            if(!is_null($currentUserCG))
            {
                if(!array_key_exists('dateDiscarded', $currentUserCG) || is_null($currentUserCG['dateDiscarded']) || strtotime($currentUserCG['dateDiscarded']) < strtotime($json['timestamp']))
                {
                    $usersCommunityGoalsModel->updateById(
                        $currentUserCG['id'],
                        [
                            'dateDiscarded'   => $json['timestamp'],
                        ]
                    );
                }
            }
            else
            {
                // We do not have yet the CG for that user, wait for it
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';
                
                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);
                
                return static::$return;
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