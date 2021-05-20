<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Promotion extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update the commander new promoted rank.',
        'Reset the promoted rank progression to 0.',
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

            $insert = array();

            if(array_key_exists('Combat', $json))
            {
                $insert['combat']               = (int) $json['Combat'];
                $insert['combatProgress']       = 0;
            }

            if(array_key_exists('Trade', $json))
            {
                $insert['trader']               = (int) $json['Trade'];
                $insert['traderProgress']       = 0;
            }

            if(array_key_exists('Explore', $json))
            {
                $insert['explorer']             = (int) $json['Explore'];
                $insert['explorerProgress']     = 0;
            }

            if(array_key_exists('Soldier', $json))
            {
                $insert['mercenary']            = (int) $json['Soldier'];
                $insert['mercenaryProgress']    = 0;
            }

            if(array_key_exists('Exobiologist', $json))
            {
                $insert['exobiologist']             = (int) $json['Exobiologist'];
                $insert['exobiologistProgress']     = 0;
            }

            if(array_key_exists('Empire', $json))
            {
                $insert['empire']               = (int) $json['Empire'];
                $insert['empireProgress']       = 0;
            }

            if(array_key_exists('Federation', $json))
            {
                $insert['federation']           = (int) $json['Federation'];
                $insert['federationProgress']   = 0;
            }

            if(array_key_exists('CQC', $json))
            {
                $insert['CQC']                  = (int) $json['CQC'];
                $insert['CQCProgress']          = 0;
            }

            if(count($insert) > 0)
            {
                $insert['lastRankUpdate']       = $json['timestamp'];
                $insert['lastProgressUpdate']   = $json['timestamp'];

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