<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CrewHire extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Remove the crew hiring cost from the commander credits.',
    ];



    public static function run($json)
    {
        if($json['Cost'] > 0)
        {
            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'CrewHire')
                                  ->where('balance = ?', - (int) $json['Cost'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'CrewHire';
                $insert['balance']      = - (int) $json['Cost'];
                $insert['dateUpdated']  = $json['timestamp'];

                $stationId = static::findStationId($json);

                if(!is_null($stationId))
                {
                    $insert['refStation']   = $stationId;
                }

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
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();
        $currentShipId  = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }

        if(array_key_exists('Name', $json))
        {
            $details['name']        = $json['Name'];
        }

        if(array_key_exists('CombatRank', $json))
        {
            $details['combatRank']  = $json['CombatRank'];
        }

        if(array_key_exists('Faction', $json))
        {
            $factionsModel  = new \Models_Factions;
            $factionId      = $factionsModel->getByName($json['Faction']);

            if(!is_null($factionId))
            {
                $details['refFaction'] = (int) $factionId['id'];
            }
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}