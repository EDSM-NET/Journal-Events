<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class PowerplayLeave extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Unpledge the commander to the Power.',
    ];
    
    
    
    public static function run($json)
    {
        // Check user pledge
        $lastPowerUpdate    = static::$user->getPowerLastUpdate();
        
        // If newer or null, update the power
        if(is_null($lastPowerUpdate) || strtotime($lastPowerUpdate) < strtotime($json['timestamp']))
        {
            $update                     = array();
            $update['currentPower']     = new \Zend_Db_Expr('NULL');
            $update['powerDetails']     = new \Zend_Db_Expr('NULL');
            $update['lastPowerUpdate']  = $json['timestamp'];
            
            $usersModel = new \Models_Users;
            $usersModel->updateById(static::$user->getId(), $update);
            
            unset($usersModel, $update);
        }
        
        return static::$return;
    }
}