<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CommunityGoalReward extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Add community goal reward to commander credits.',
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
            
            // Reward when found
            if(!is_null($currentUserCG))
            {
                $update = array();
                
                if(!array_key_exists('reward', $currentUserCG) || is_null($currentUserCG['reward']))
                {
                    $update['reward']       = $json['Reward'];
                    $update['dateRewarded'] = $json['timestamp'];
                }
                
                if(count($update) > 0)
                {
                    $usersCommunityGoalsModel->updateById($currentUserCG['id'], $update);
                }
                
                unset($update);
                
                $usersCreditsModel = new \Models_Users_Credits;
        
                $isAlreadyStored   = $usersCreditsModel->fetchRow(
                    $usersCreditsModel->select()
                                      ->where('refUser = ?', static::$user->getId())
                                      ->where('reason = ?', 'CommunityGoalReward')
                                      ->where('balance = ?', (int) $json['Reward'])
                                      ->where('dateUpdated = ?', $json['timestamp'])
                );
                
                if(is_null($isAlreadyStored))
                {
                    $insert                 = array();
                    $insert['refUser']      = static::$user->getId();
                    $insert['reason']       = 'CommunityGoalReward';
                    $insert['balance']      = (int) $json['Reward'];
                    $insert['details']      = \Zend_Json::encode(array('cgId' => $cgId));
                    $insert['dateUpdated']  = $json['timestamp'];
                    
                    $usersCreditsModel->insert($insert);
                    
                    unset($insert);
                }
                else
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';
                }
                
                unset($usersCreditsModel, $isAlreadyStored);
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