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
        'Update engineer(s) rank',
        'Update engineer(s) stage',
    ];



    public static function run($json)
    {
        // Convert to multiple engineers
        if(!array_key_exists('Engineers', $json) && array_key_exists('Engineer', $json))
        {
            $tempEngineer               = array();
            $tempEngineer['Engineer']   = $json['Engineer'];

            if(array_key_exists('EngineerID', $json))
            {
                $tempEngineer['EngineerID']           = $json['EngineerID'];
                unset($json['EngineerID']);
            }

            if(array_key_exists('Rank', $json))
            {
                $tempEngineer['Rank']           = $json['Rank'];
                unset($json['Rank']);
            }

            if(array_key_exists('Progress', $json))
            {
                $tempEngineer['Progress']       = $json['Progress'];
                unset($json['Progress']);
            }

            if(array_key_exists('RankProgress', $json))
            {
                $tempEngineer['RankProgress']   = $json['RankProgress'];
                unset($json['RankProgress']);
            }

            $json['Engineers'] = array($tempEngineer);

            unset($json['Engineer'], $json['EngineerID'], $tempEngineer);
        }

        if(!array_key_exists('Engineers', $json))
        {
            static::$return['msgnum']   = 500;
            static::$return['msg']      = 'Message needs to be checked';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        $usersEngineersModel    = new \Models_Users_Engineers;
        $currentEngineers       = $usersEngineersModel->getByRefUser(static::$user->getId());

        foreach($json['Engineers'] AS $jsonEngineer)
        {
            if(!array_key_exists('Engineer', $jsonEngineer))
            {
                continue;
            }

            // Check if Engineer is known in EDSM
            $engineerId        = \Alias\Station\Engineer::getFromFd($jsonEngineer['Engineer']);

            if(is_null($engineerId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Station\Engineer: ' . $jsonEngineer['Engineer']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

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

                if(array_key_exists('Rank', $jsonEngineer))
                {
                    if(is_null($currentEngineer) || (!is_null($currentEngineer) && $currentEngineer['rank'] < $jsonEngineer['Rank']))
                    {
                        $insert['rank'] = (int) $jsonEngineer['Rank'];
                    }
                }

                if(array_key_exists('Progress', $jsonEngineer))
                {
                    if(is_null($currentEngineer) || (!is_null($currentEngineer) && $currentEngineer['stageProgress'] != $jsonEngineer['Progress']))
                    {
                        $insert['stageProgress'] = $jsonEngineer['Progress'];
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
                        $insert['refUser']      = static::$user->getId(); // Used to clean the cache!

                        if(!is_null($currentEngineer))
                        {
                            $usersEngineersModel->updateById($currentEngineer['id'], $insert);
                        }
                        else
                        {
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
        }

        unset($usersEngineersModel, $currentEngineers);

        return static::$return;
    }
}