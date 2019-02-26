<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Scan extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert body if using EDSM importer, if "Delay scanned celestial bodies until docked?" is Off or if scan is older than a month.',
        'Insert celestial body in user scan.',
        'Update celestial body first discovery.',
    ];


    static public $insertBody       = false;


    public static function run($json)
    {
        // Do not handle Belt Cluster
        if(stripos($json['BodyName'], 'Belt Cluster') !== false)
        {
            return static::$return; // @See EDDN\System\Body
        }

        $currentBody            = null;
        $systemsBodiesModel     = new \Models_Systems_Bodies;

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
                        // Complete name format or just body part
                        if(strtolower($currentSystemBody['name']) == strtolower($bodyName) || strtolower($currentSystemBody['name']) == strtolower($json['BodyName']))
                        {
                            $currentBody    = $currentSystemBody['id'];
                            break;
                        }
                    }
                }

                if(!is_null($currentBody))
                {
                    // The message is recent and EDDN already have the body, most likely we can untick the wait for EDDN option!
                    // This is done to prevent having too much bodies waiting in our temp table...
                    //$referenceTime = strtotime('30 MINUTE AGO');
                    //if(static::$user->waitScanBodyFromEDDN() === true && strtotime($json['timestamp']) > $referenceTime)
                    //{
                        // Get body and check againts last update date
                        //$usersModel = new \Models_Users;
                        //$usersModel->updateById(static::$user->getId(), ['waitScanBodyFromEDDN' => 0]);
                        //unset($usersModel);
                    //}
                }
                elseif(static::$softwareId == 1 || static::$user->waitScanBodyFromEDDN() === false || strtotime($json['timestamp']) < strtotime('1 MONTH AGO'))
                {
                    $return = null;

                    // Reimport EDDN message
                    try
                    {
                        $return = \EDDN\System\Body::handle($systemId, $json, false);

                        // If false, something went wrong on the scan name not belonging to it's system
                        if($return === false)
                        {
                            // Just delete it... Software should pay better attention to follow the right system!
                            return static::$return;
                        }
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        // Based on unique index, the body was already saved.
                        if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                        {

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

                    if(!is_null($return) && $return !== false)
                    {
                        $currentBody = $return;
                    }
                    else
                    {
                        $currentBody = $systemsBodiesModel->fetchRow(
                            $systemsBodiesModel->select()
                                               ->where('refSystem = ?', $systemId)
                                               ->where('name = ? OR name = "' . $bodyName . '"', $json['BodyName'])
                        );

                        if(!is_null($currentBody))
                        {
                            $currentBody = $currentBody->id;
                        }
                    }
                }
            }
            else
            {
                $systemId    = static::findSystemId($json);

                // If found, then the system was most likely renamed...
                if(!is_null($systemId))
                {
                    $currentSystem = \Component\System::getInstance($systemId);

                    if(array_key_exists('_systemName', $json) && !empty($json['_systemName']) && $currentSystem->getName() != $json['_systemName'])
                    {
                        // Just delete it... It will mostly have been merged on rename or scanned again
                        return static::$return;
                    }
                    // At some point if we don't have a proper system, just delete!
                    elseif(strtotime($json['timestamp']) < strtotime('3 MONTH AGO'))
                    {
                        return static::$return;
                    }
                }
                else
                {
                    // Some events only contains BodyName and StarType, avoid empty events by checking the distance which is mandatory
                    if(!array_key_exists('DistanceFromArrivalLS', $json) OR !array_key_exists('_systemName', $json) OR (array_key_exists('_systemName', $json) && empty($json['_systemName'])))
                    {
                        // Just delete it...
                        return static::$return;
                    }
                    // At some point if we don't have a proper system, just delete!
                    elseif(strtotime($json['timestamp']) < strtotime('1 MONTH AGO'))
                    {
                        return static::$return;
                    }
                }
            }
        }

        // Convert the json message to a smaller subset to save in case we did not process the body yet
        if(is_null($currentBody))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Insert user scan
        $systemsBodiesUsersModel = new \Models_Systems_Bodies_Users;

        try
        {
            $insert                 = array();
            $insert['refBody']      = $currentBody;
            $insert['refUser']      = static::$user->getId();
            $insert['dateScanned']  = $json['timestamp'];

            $systemsBodiesUsersModel->insert($insert);

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
        $usersExplorationValuesModel->deleteByRefUserAndRefDate(
            static::$user->getId(),
            date('Y-m-d', strtotime($json['timestamp']))
        );

        $usersScans                             = $systemsBodiesUsersModel->getByRefBody($currentBody);
        foreach($usersScans AS $userScan)
        {
            if(strtotime($userScan['dateScanned']) >= strtotime($json['timestamp']))
            {
                $usersExplorationValuesModel->deleteByRefUserAndRefDate(
                    $userScan['refUser'],
                    date('Y-m-d', strtotime($userScan['dateScanned']))
                );
            }
        }

        unset($usersExplorationValuesModel);

        //BADGES
        $firstScannedBy = $systemsBodiesUsersModel->getFirstScannedByRefBody($currentBody);
        if(!is_null($firstScannedBy) && $firstScannedBy['refUser'] == static::$user->getId())
        {
            $currentBodyData = $systemsBodiesModel->getById($currentBody);

            if(array_key_exists('group', $currentBodyData) && $currentBodyData['group'] == 1)
            {
                if(array_key_exists('type', $currentBodyData) && \Alias\Body\Star\Type::isScoopable($currentBodyData['type']) === true)
                {
                    static::$user->giveBadge(
                        5010,
                        ['bodyId' => $currentBody]
                    );
                }

                if(array_key_exists('type', $currentBodyData) && in_array($currentBodyData['type'], [21, 22, 23, 24, 25]))
                {
                    static::$user->giveBadge(
                        5020,
                        ['bodyId' => $currentBody]
                    );
                }

                if(array_key_exists('type', $currentBodyData) && $currentBodyData['type'] == 91)
                {
                    static::$user->giveBadge(
                        5060,
                        ['bodyId' => $currentBody]
                    );
                }

                if(array_key_exists('type', $currentBodyData) && $currentBodyData['type'] == 92)
                {
                    static::$user->giveBadge(
                        5050,
                        ['bodyId' => $currentBody]
                    );
                }
            }

            if(array_key_exists('group', $currentBodyData) && $currentBodyData['group'] == 2)
            {
                static::$user->giveBadge(
                    5000,
                    ['bodyId' => $currentBody]
                );

                if(array_key_exists('type', $currentBodyData) && $currentBodyData['type'] == 31)
                {
                    static::$user->giveBadge(
                        5100,
                        ['bodyId' => $currentBody]
                    );
                }
                if(array_key_exists('type', $currentBodyData) && $currentBodyData['type'] == 41)
                {
                    static::$user->giveBadge(
                        5110,
                        ['bodyId' => $currentBody]
                    );
                }
                if(array_key_exists('type', $currentBodyData) && $currentBodyData['type'] == 51)
                {
                    static::$user->giveBadge(
                        5120,
                        ['bodyId' => $currentBody]
                    );
                }
            }

            unset($currentBodyData);
        }

        unset($systemsBodiesModel, $systemsBodiesUsersModel);

        return static::$return;
    }
}