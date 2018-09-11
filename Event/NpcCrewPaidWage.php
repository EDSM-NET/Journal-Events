<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class NpcCrewPaidWage extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the crew wages from the commander credits.',
    ];
    
    
    
    public static function run($json)
    {
        $usersCreditsModel = new \Models_Users_Credits;
        
        $isAlreadyStored   = $usersCreditsModel->fetchRow(
            $usersCreditsModel->select()
                              ->where('refUser = ?', static::$user->getId())
                              ->where('reason = ?', 'NpcCrewPaidWage')
                              ->where('balance = ?', - (int) $json['Amount'])
                              ->where('dateUpdated = ?', $json['timestamp'])
        );
        
        if(is_null($isAlreadyStored))
        {
            $insert                 = array();
            $insert['refUser']      = static::$user->getId();
            $insert['reason']       = 'NpcCrewPaidWage';
            $insert['balance']      = - (int) $json['Amount'];
            $insert['dateUpdated']  = $json['timestamp'];
            
            // Generate details
            $details = static::generateDetails($json);
            if(!is_null($details)){ $insert['details'] = $details; }
            
            $usersCreditsModel->insert($insert);
            
            unset($insert);
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
        $details                = array();
        $details['npcCrewId']   = $json['NpcCrewId'];
            
        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }
        
        return null;
    }
}