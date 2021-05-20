<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Progress extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update the commander ranks progression.',
    ];



    public static function run($json)
    {
        $usersRanksModel    = new \Models_Users_Ranks;
        $currentRanks       = $usersRanksModel->getByRefUser(static::$user->getId());
        $lastProgressUpdate = strtotime('1WEEK AGO');

        if(!is_null($currentRanks) && array_key_exists('lastProgressUpdate', $currentRanks) && !is_null($currentRanks['lastProgressUpdate']))
        {
            $lastProgressUpdate = strtotime($currentRanks['lastProgressUpdate']);
        }

        if($lastProgressUpdate < strtotime($json['timestamp']))
        {
            $insert                         = array();

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['combatProgress'] != $json['Combat']))
            {
                $insert['combatProgress'] = (int) $json['Combat'];
            }

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['traderProgress'] != $json['Trade']))
            {
                $insert['traderProgress'] = (int) $json['Trade'];
            }

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['explorerProgress'] != $json['Explore']))
            {
                $insert['explorerProgress'] = (int) $json['Explore'];
            }

            if(array_key_exists('Soldier', $json))
            {
                if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['mercenaryProgress'] != $json['Soldier']))
                {
                    $insert['mercenaryProgress'] = (int) $json['Soldier'];
                }
            }

            if(array_key_exists('Exobiologist', $json))
            {
                if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['exobiologistProgress'] != $json['Exobiologist']))
                {
                    $insert['exobiologistProgress'] = (int) $json['Exobiologist'];
                }
            }

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['empireProgress'] != $json['Empire']))
            {
                $insert['empireProgress'] = (int) $json['Empire'];
            }

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['federationProgress'] != $json['Federation']))
            {
                $insert['federationProgress'] = (int) $json['Federation'];
            }

            if(is_null($currentRanks) || (!is_null($currentRanks) && $currentRanks['CQCProgress'] != $json['CQC']))
            {
                $insert['CQCProgress'] = (int) $json['CQC'];
            }

            // Only make updates when values are different
            if(count($insert) > 0)
            {
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