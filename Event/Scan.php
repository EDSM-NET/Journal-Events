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
        'Insert body if using EDSM importer or if "Delay scanned celestial bodies until docked?" is Off',
        'Update celestial body first discovery.',
    ];
    
    
    
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
                $currentBody = $systemsBodiesModel->fetchRow(
                    $systemsBodiesModel->select()
                                       ->where('refSystem = ?', $systemId)
                                       ->where('name = ?', $json['BodyName'])
                );
                
                if(!is_null($currentBody))
                {
                    $currentBody = $currentBody->id;
                }
                elseif(static::$softwareId == 1 || static::$user->waitScanBodyFromEDDN() === false || strtotime($json['timestamp']) < strtotime('1 MONTH AGO'))
                {
                    // Reimport EDDN message
                    try
                    {
                        $return = \EDDN\System\Body::handle($systemId, $json, false);
                        
                        // If false, something went wrong on the scan name not belonging to it's system
                        if($return === false)
                        {
                            static::$return['msgnum']   = 402;
                            static::$return['msg']      = 'Item unknown';
                        
                            // Save in temp table for reparsing
                            $json['isError']            = 1;
                            \Journal\Event::run($json);
                            
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
                    
                    $currentBody = $systemsBodiesModel->fetchRow(
                        $systemsBodiesModel->select()
                                           ->where('refSystem = ?', $systemId)
                                           ->where('name = ?', $json['BodyName'])
                    );
                    
                    if(!is_null($currentBody))
                    {
                        $currentBody = $currentBody->id;
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
        
        $currentBodyData    = $systemsBodiesModel->getById($currentBody);
        $firstDiscoverUser  = $currentBodyData['refUser'];
        $update             = array();
        
        if(is_null($currentBodyData['refUser']))
        {
            $update['refUser']          = static::$user->getId();
            $update['dateDiscovery']    = $json['timestamp'];
            
            $firstDiscoverUser          = static::$user->getId();
        }
        else
        {
            if(is_null($currentBodyData['dateDiscovery']) && $currentBodyData['refUser'] == static::$user->getId())
            {
                $update['dateDiscovery']    = $json['timestamp'];
            }
            elseif(!is_null($currentBodyData['dateDiscovery']) && strtotime($json['timestamp']) < strtotime($currentBodyData['dateDiscovery']))
            {
                $update['refUser']          = static::$user->getId();
                $update['dateDiscovery']    = $json['timestamp'];
            
                $firstDiscoverUser          = static::$user->getId();
            }
        }
        
        if(count($update) > 0)
        {
            $systemsBodiesModel->updateById($currentBody, $update);
        }
        
        unset($systemsBodiesModel, $update);
        
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
        
        unset($systemsBodiesUsersModel);
        
        //BADGES
        if($firstDiscoverUser == static::$user->getId())
        {
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
        }
        
        return static::$return;
    }
}