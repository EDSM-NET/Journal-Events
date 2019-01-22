<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class MissionFailed extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Set mission status to "Failed".',
        'Remove fine from the commander credits.',
    ];



    public static function run($json)
    {
        $usersMissionsModel = new \Models_Users_Missions;
        $currentMission     = $usersMissionsModel->getById($json['MissionID']);

        if(is_null($currentMission))
        {
            $missionType        = \Alias\Station\Mission\Type::getFromFd($json['Name']);

            if(is_null($missionType))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }

            $insert                 = array();
            $insert['id']           = $json['MissionID'];
            $insert['refUser']      = static::$user->getId();
            $insert['type']         = $missionType;
            $insert['status']       = 'Failed';
            $insert['dateFailed']   = $json['timestamp'];

            if(array_key_exists('Fine', $json) && $json['Fine'] > 0)
            {
                $insert['details'] = \Zend_Json::encode(array('fine' => $json['Fine']));
            }

            try
            {
                $usersMissionsModel->insert($insert);
            }
            catch(\Zend_Db_Exception $e)
            {
                // Based on unique index, this ship entry was already saved.
                if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                {
                    // CONTINUE...
                }
                else
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();

                    $registry = \Zend_Registry::getInstance();

                    if($registry->offsetExists('sentryClient'))
                    {
                        $sentryClient = $registry->offsetGet('sentryClient');
                        $sentryClient->captureException($e);
                    }
                }
            }

            unset($insert);
        }
        else
        {
            $update = array();

            if($currentMission['status'] != 'Failed')
            {
                $update['status'] = 'Failed';
            }

            if($currentMission['dateFailed'] != $json['timestamp'])
            {
                $update['dateFailed'] = $json['timestamp'];
            }

            if(array_key_exists('Fine', $json) && $json['Fine'] > 0)
            {
                if(!is_null($currentMission['details']))
                {
                    $details = \Zend_Json::decode($currentMission['details']);

                    $details['fine'] = $json['Fine'];

                    ksort($details);
                    $update['details'] = \Zend_Json::encode($details);
                }
                else
                {
                    $update['details'] = \Zend_Json::encode(array('fine' => $json['Fine']));
                }
            }

            if(count($update) > 0)
            {
                $usersMissionsModel->updateById($json['MissionID'], $update);
            }

            unset($update);
        }

        unset($usersMissionsModel);

        // Remove fine from the commander
        if(array_key_exists('Fine', $json) && $json['Fine'] > 0)
        {
            $usersCreditsModel = new \Models_Users_Credits;

            $isAlreadyStored   = $usersCreditsModel->fetchRow(
                $usersCreditsModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('reason = ?', 'MissionFailed')
                                  ->where('balance = ?', (int) $json['Fine'])
                                  ->where('dateUpdated = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert                 = array();
                $insert['refUser']      = static::$user->getId();
                $insert['reason']       = 'MissionFailed';
                $insert['balance']      = (int) $json['Fine'];
                $insert['dateUpdated']  = $json['timestamp'];

                $stationId = static::findStationId($json);

                if(!is_null($stationId))
                {
                    $insert['refStation']   = $stationId;
                }

                // Generate details
                $details = static::generateFineDetails($json);
                if(!is_null($details)){ $insert['details'] = $details; }

                $usersCreditsModel->insert($insert);

                unset($insert);
            }
            else
            {
                $details = static::generateFineDetails($json);

                if($isAlreadyStored->details != $details)
                {
                    $usersCreditsModel->updateById(
                        $isAlreadyStored->id,
                        [
                            'details' => $details,
                        ]
                    );
                }
            }

            unset($usersCreditsModel, $isAlreadyStored);
        }

        return static::$return;
    }

    static private function generateFineDetails($json)
    {
        $details        = array();
        $currentShipId  = static::findShipId($json);

        if(!is_null($currentShipId))
        {
            $details['shipId'] = $currentShipId;
        }

        $details['missionId']   = $json['MissionID'];
        $details['missionType'] = \Alias\Station\Mission\Type::getFromFd($json['Name']);

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}