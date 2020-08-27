<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CommitCrime extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Register commander crime.',
    ];



    public static function run($json)
    {
        // Check if CrimeType is known in EDSM
        $crimeId = \Alias\Commander\Crime\Type::getFromFd($json['CrimeType']);

        if(is_null($crimeId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log('Alias\Commander\Crime\Type: ' . $json['CrimeType']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Check if Victim is known in EDSM
        if(array_key_exists('Victim', $json) && substr($json['Victim'], 0, 1) == '$' && substr($json['Victim'], -1) == ';')
        {
            $victimId = \Alias\Commander\Crime\Victim::getFromFd($json['Victim']);

            if(is_null($victimId))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Alias::log('Alias\Commander\Crime\Victim: ' . $json['Victim']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }
        else
        {
            $victimId = null;
        }

        $systemId           = static::findSystemId($json);

        if(!is_null($systemId))
        {
            try
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refSystem']    = $systemId;
                $insert['crime']        = $crimeId;
                $insert['dateEvent']    = $json['timestamp'];

                if(array_key_exists('Victim', $json))
                {
                    if(is_null($victimId))
                    {
                        $insert['victimName'] = $json['Victim'];
                    }
                    else
                    {
                        $insert['refVictim'] = $victimId;
                    }
                }

                $shipId = static::findShipId($json);
                if(!is_null($shipId))
                {
                    $insert['refShip'] = $shipId;
                }

                if(array_key_exists('Fine', $json))
                {
                    $insert['amountType']   = 'Fine';
                    $insert['amount']       = (int) trim($json['Fine'], ',');
                }
                elseif(array_key_exists('Bounty', $json))
                {
                    $insert['amountType']   = 'Bounty';
                    $insert['amount']       = (int) trim($json['Bounty'], ',');
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

                if(array_key_exists('Faction', $json) && !empty($json['Faction']))
                {
                    $allegiance = \Alias\System\Allegiance::getFromFd($json['Faction']);

                    if(is_null($allegiance))
                    {
                        $factionsModel      = new \Models_Factions;
                        $currentFaction     = $factionsModel->getByName($json['Faction']);

                        if(!is_null($currentFaction))
                        {
                            $currentFactionId = $currentFaction['id'];
                        }
                        else
                        {
                            $currentFactionId = $factionsModel->insert(['name' => $json['Faction']]);
                        }

                        $insert['refFaction'] = $currentFactionId;

                        unset($factionsModel, $currentFaction, $currentFactionId);
                    }
                }

                $usersCrimesModel      = new \Models_Users_Crimes;
                $usersCrimesModel->insert($insert);

                unset($usersCrimesModel, $insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this journal entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';
                }
                else
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();

                    if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                    {
                        \Sentry\captureException($e);
                    }
                }
            }
        }

        return static::$return;
    }
}