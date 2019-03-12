<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class SAAScanComplete extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert celestial body in user map.',
        'Update celestial body first mapping.',
    ];

    // { "timestamp":"2018-10-31T13:30:12Z", "event":"SAAScanComplete", "BodyName":"HIP 63835 CD 8", "BodyID":16, "ProbesUsed":28, "EfficiencyTarget":21 }

    public static function run($json)
    {
        // Do not handle Belt Cluster
        if(stripos($json['BodyName'], 'Belt Cluster') !== false)
        {
            return static::$return; // @See EDDN\System\Body
        }

        $currentBody        = null;
        $systemsBodiesModel = new \Models_Systems_Bodies;

        // Try to find body by name/refSystem
        if(is_null($currentBody))
        {
            $systemId    = static::findSystemId($json, true);

            if(!is_null($systemId))
            {
                // Is it an aliased body name or can we remove the system name from it?
                $bodyName   = $json['BodyName'];
                $isAliased  = \Alias\Body\Name::isAliased($systemId, $bodyName);

                if($isAliased === false)
                {
                    $currentSystem  = \Component\System::getInstance($systemId);
                    $systemName     = $currentSystem->getName();

                    if(substr(strtolower($bodyName), 0, strlen($systemName)) == strtolower($systemName))
                    {
                        $bodyName = trim(str_ireplace($systemName, '', $bodyName));
                    }
                }

                // Use cache to fetch all bodies in the current system
                $systemBodies = $systemsBodiesModel->getByRefSystem($systemId);

                if(!is_null($systemBodies) && count($systemBodies) > 0)
                {
                    foreach($systemBodies AS $currentSystemBody)
                    {
                        //if($currentSystemBody['name'] == $json['BodyName'])
                        // Complete name format or just body part
                        if(strtolower($currentSystemBody['name']) == strtolower($bodyName) || strtolower($currentSystemBody['name']) == strtolower($json['BodyName']))
                        {
                            $currentBody = $currentSystemBody;
                            break;
                        }
                    }
                }

                if(!is_null($currentBody))
                {
                    // Update efficiencyTarget if needed
                    if(!array_key_exists('efficiencyTarget', $currentBody) || (array_key_exists('efficiencyTarget', $currentBody) && $currentBody['efficiencyTarget'] != $json['EfficiencyTarget']))
                    {
                        $systemsBodiesModel->updateById(
                            $currentBody['id'],
                            array(
                                'efficiencyTarget'  => $json['EfficiencyTarget'],
                            )
                        );
                    }

                    $currentBody = $currentBody['id'];
                }
            }
        }

        unset($systemsBodiesModel);

        // Save until body is known
        if(is_null($currentBody))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Insert user mapping
        $systemsBodiesUsersSAAModel = new \Models_Systems_Bodies_UsersSAA;

        try
        {
            $insert                         = array();
            $insert['refBody']              = $currentBody;
            $insert['refUser']              = static::$user->getId();
            $insert['probesUsed']           = $json['ProbesUsed'];
            $insert['dateMapped']           = $json['timestamp'];

            $systemsBodiesUsersSAAModel->insert($insert);

            unset($insert);
        }
        catch(\Zend_Db_Exception $e)
        {
            // Based on unique index, the body was already saved.
            if(strpos($e->getMessage(), '1062 Duplicate') !== false)
            {

            }
            else
            {
                $registry = \Zend_Registry::getInstance();

                if($registry->offsetExists('sentryClient'))
                {
                    $sentryClient = $registry->offsetGet('sentryClient');
                    $sentryClient->captureException($e);
                }
            }
        }

        // Reset date scan stats for each users
        $usersExplorationValuesModel            = new \Models_Users_Exploration_Values;
        $systemsBodiesUsersModel                = new \Models_Systems_Bodies_Users;

        $usersExplorationValuesModel->deleteByRefUserAndRefDate(
            static::$user->getId(),
            date('Y-m-d', strtotime($json['timestamp']))
        );

        $usersScans                             = $systemsBodiesUsersModel->getByRefBody($currentBody);
        foreach($usersScans AS $key => $userScan)
        {
            if($key >= 2)
            {
                break;
            }

            if(strtotime($userScan['dateScanned']) >= strtotime($json['timestamp']))
            {
                $usersExplorationValuesModel->deleteByRefUserAndRefDate(
                    $userScan['refUser'],
                    date('Y-m-d', strtotime($userScan['dateScanned']))
                );
            }
        }

        unset($systemsBodiesUsersSAAModel, $usersExplorationValuesModel, $systemsBodiesUsersModel);

        return static::$return;
    }
}