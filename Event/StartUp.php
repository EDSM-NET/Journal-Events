<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class StartUp extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Return current system for ED Market Connector.',
    ];



    public static function run($json)
    {
        $currentSystem      = null;
        $systemName         = null;
        $systemCoordinates  = null;

        if(array_key_exists('_systemName', $json) && !is_null($json['_systemName']))
        {
            $systemName = $json['_systemName'];

            if(array_key_exists('_systemCoordinates', $json))
            {
                $systemCoordinates = $json['_systemCoordinates'];
            }
        }

        // Convert coordinates to EDSM format
        if(!is_null($systemCoordinates))
        {
            $systemCoordinates  = array(
                'x'  => round($systemCoordinates[0] * 32),
                'y'  => round($systemCoordinates[1] * 32),
                'z'  => round($systemCoordinates[2] * 32),
            );
        }

        if(!is_null($systemName))
        {
            $systemName     = trim($systemName);

            $systemsModel   = new \Models_Systems;
            $system         = $systemsModel->getByName($systemName);

            // System creation
            if(is_null($system))
            {
                $systemId               = null;
                $insertSystem           = array();
                $insertSystem['name']   = $systemName;

                if(!is_null($systemCoordinates))
                {
                   $insertSystem = array_merge($insertSystem, $systemCoordinates);
                }

                if(array_key_exists('SystemAddress', $json))
                {
                    $insert['id64'] = $json['SystemAddress'];
                }
                else
                {
                    // Generate the PG systemAdress (Old journal, Console users, cAPI)
                    $id64 = \Component\System::calculateId64FromName($systemName);
                    if(!is_null($id64))
                    {
                        $insert['id64'] = $id64;
                    }
                }

                try
                {
                    $systemId                           = $systemsModel->insert($insertSystem);
                    static::$return['systemCreated']    = true;
                }
                catch(\Zend_Db_Exception $e)
                {
                    $systemId       = null;
                    $system         = $systemsModel->getByName($systemName);

                    if(!is_null($system))
                    {
                        $systemId                           = $system['id'];
                        static::$return['systemCreated']    = false;
                    }
                    else
                    {
                        static::$return['msgnum']   = 500;
                        static::$return['msg']      = 'Exception: ' . $e->getMessage();

                        return static::$return;
                    }
                }

                unset($insertSystem);

                if(!is_null($systemId))
                {
                    $currentSystem = \Component\System::getInstance($systemId);
                }
            }
            // System already exists
            else
            {
                $currentSystem = \Component\System::getInstance($system['id']);

                // Check system renamed/merged to another
                if($currentSystem->isHidden() === true)
                {
                    $mergedTo = $currentSystem->getMergedTo();

                    if(!is_null($mergedTo))
                    {
                        // Switch systems when they have been renamed
                        $currentSystem = \Component\System::getInstance($mergedTo);
                    }
                    else
                    {
                        static::$return['msgnum']   = 451;
                        static::$return['msg']      = 'System probably non existant';

                        return static::$return;
                    }
                }

                // Check if system have duplicate
                $duplicates = $currentSystem->getDuplicates();
                if(!is_null($duplicates) && is_array($duplicates) && count($duplicates) > 0)
                {
                    if(is_null($systemCoordinates))
                    {
                        static::$return['msgnum']   = 451;
                        static::$return['msg']      = 'System probably non existant';

                        return static::$return;
                    }
                    else
                    {
                        if($systemCoordinates['x'] != $currentSystem->getX() || $systemCoordinates['y'] != $currentSystem->getY() || $systemCoordinates['z'] != $currentSystem->getZ())
                        {
                            foreach($duplicates AS $duplicate)
                            {
                                $currentSystemTest  = \Component\System::getInstance($duplicate);

                                // Try to follow hidden system
                                $mergedTo = $currentSystemTest->getMergedTo();
                                if($currentSystemTest->isHidden() === true && !is_null($mergedTo))
                                {
                                    $currentSystemTest = \Component\System::getInstance($mergedTo);
                                }

                                // If coordinates are the same, then swith to that duplicate!
                                if($systemCoordinates['x'] == $currentSystemTest->getX() && $systemCoordinates['y'] == $currentSystemTest->getY() && $systemCoordinates['z'] == $currentSystemTest->getZ())
                                {
                                    $currentSystem = $currentSystemTest;

                                    unset($currentSystemTest);
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            unset($systemsModel);
        }

        // We have found the right system
        if(!is_null($currentSystem))
        {
            static::$return['systemId'] = $currentSystem->getId();
        }

        return static::$return;
    }
}