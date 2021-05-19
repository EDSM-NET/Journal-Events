<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Died extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Register commander death.',
    ];



    protected static $fighters      = array(
        'federation_fighter',
        'empire_fighter',
        'independent_fighter',
        'gdn_hybrid_fighter_v1',
        'gdn_hybrid_fighter_v2',
        'gdn_hybrid_fighter_v3',

        'federation_capitalship',
        'federation_capitalship_damaged',
        'empire_capitalship',
    );

    protected static $thargoids     = array(
        '$unknown;',
        'unknown',
        'scout',
        'scout_q',
    );



    public static function run($json)
    {
        $systemId           = static::findSystemId($json);

        if(!is_null($systemId))
        {
            // Convert single killer event to Killers
            if(array_key_exists('KillerName', $json))
            {
                $temp           = array();
                $temp['Name']   = $json['KillerName'];

                if(array_key_exists('KillerShip', $json))
                {
                    $temp['Ship'] = $json['KillerShip'];
                }
                if(array_key_exists('KillerRank', $json))
                {
                    $temp['Rank'] = $json['KillerRank'];
                }

                $json['Killers'] = array($temp);

                unset($json['KillerName'], $json['KillerShip'], $json['KillerRank']);
            }

            if(array_key_exists('Killers', $json))
            {
                foreach($json['Killers'] AS $killer)
                {
                    // If available, make sure the NPC alias is known to EDSM
                    if(array_key_exists('Name', $killer))
                    {
                        if(stripos(strtolower($killer['Name']), '$shipname') !== false)
                        {
                            $killerNpc = \Alias\Ship\NPC\Type::getFromFd($killer['Name']);

                            if(is_null($killerNpc))
                            {
                                static::$return['msgnum']   = 402;
                                static::$return['msg']      = 'Item unknown';

                                \EDSM_Api_Logger_Alias::log('Alias\Ship\NPC\Type : ' . $killer['Name']);

                                $json['isError']            = 1;
                                \Journal\Event::run($json);

                                return static::$return;
                            }
                        }
                    }

                    // Make sure the ship is known to EDSM
                    if(array_key_exists('Ship', $killer))
                    {
                        if(in_array($killer['Ship'], static::$fighters) || in_array($killer['Ship'], static::$thargoids) || (array_key_exists('Name', $killer) && in_array(strtolower($killer['Name']), static::$thargoids)))
                        {
                            // Handled in details
                        }
                        elseif(!empty(trim($killer['Ship'])))
                        {
                            $killerShip         = \Alias\Ship\Type::getFromFd($killer['Ship']);

                            if(is_null($killerShip))
                            {
                                $killerSuit     = \Alias\Commander\Suit\Type::getFromFd($killer['Ship']);

                                if(is_null($killerSuit))
                                {
                                    static::$return['msgnum']   = 402;
                                    static::$return['msg']      = 'Item unknown';

                                    \EDSM_Api_Logger_Alias::log('Alias\Ship|Suit\Type : ' . $killer['Ship']);

                                    $json['isError']            = 1;
                                    \Journal\Event::run($json);

                                    return static::$return;
                                }
                            }
                        }
                    }
                }
            }

            $usersDeathsModel = new \Models_Users_Deaths;

            try
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refSystem']    = $systemId;
                $insert['reason']       = 'Died';
                $insert['dateEvent']    = $json['timestamp'];

                $shipId = static::findShipId($json);
                if(!is_null($shipId))
                {
                    $insert['refShip'] = $shipId;
                }

                // Generate killers
                $details = static::generateDetails($json);
                if(!is_null($details)){ $insert['killers'] = $details; }

                $usersDeathsModel->insert($insert);

                unset($insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this journal entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    $alreadyKnown = $usersDeathsModel->fetchRow(
                        $usersDeathsModel->select()
                                      ->where('refUser = ?', static::$user->getId())
                                      ->where('reason = ?', 'Died')
                                      ->where('dateEvent = ?', $json['timestamp'])
                    );

                    if(!is_null($alreadyKnown))
                    {
                        if(!is_null($details) && $details != $alreadyKnown->killers)
                        {
                            $usersDeathsModel->updateById(
                                $alreadyKnown->id,
                                array('killers' => $details)
                            );
                        }
                    }

                    static::$return['msgnum']   = 101;
                    static::$return['msg']      = 'Message already stored';

                    unset($alreadyKnown);
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

            unset($usersDeathsModel);
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details = array();

        if(array_key_exists('Killers', $json))
        {
            foreach($json['Killers'] AS $killer)
            {
                $temp = array();

                if(array_key_exists('Name', $killer))
                {
                    if(stripos(strtolower($killer['Name']), '$shipname') !== false)
                    {
                        $killerNpc          = \Alias\Ship\NPC\Type::getFromFd($killer['Name']);
                        $temp['NPC']       = $killerNpc;
                    }
                    else
                    {
                        $temp['name']       = $killer['Name'];
                    }
                }

                if(array_key_exists('Ship', $killer))
                {
                    if(in_array($killer['Ship'], static::$fighters) || (array_key_exists('Name', $killer) && in_array(strtolower($killer['Name']), static::$thargoids)))
                    {
                        $temp['ship']       = $killer['Ship'];
                    }
                    else
                    {
                        $killerShip         = \Alias\Ship\Type::getFromFd($killer['Ship']);

                        if(is_null($killerShip))
                        {
                            $killerSuit     = \Alias\Commander\Suit\Type::getFromFd($killer['Ship']);

                            if(!is_null($killerSuit))
                            {
                                $temp['suit']   = $killerSuit;
                            }
                        }
                        else
                        {
                            $temp['ship']       = $killerShip;
                        }
                    }
                }

                if(array_key_exists('Rank', $killer))
                {
                    $temp['rank']       = $killer['Rank'];
                }

                if(count($temp) > 0)
                {
                    $details[] = $temp;
                }
            }
        }

        if(count($details) > 0)
        {
            return \Zend_Json::encode($details);
        }

        return null;
    }
}