<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSSDiscoveryScan extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert body count in current system.',
        'Insert non body count in current system.',
    ];



    public static function run($json)
    {
        $systemId = static::findSystemId($json);

        if(!is_null($systemId))
        {
            $systemsBodiesCountModel    = new \Models_Systems_Bodies_Count;
            $currentBodiesCount         = $systemsBodiesCountModel->getByRefSystem($systemId);

            // refSystem / bodyCount / nonBodyCount
            if(is_null($currentBodiesCount))
            {
                try
                {
                    $insert                 = array();
                    $insert['refSystem']    = $systemId;
                    $insert['bodyCount']    = $json['BodyCount'];

                    $systemsBodiesCountModel->insert($insert);
                }
                catch(\Zend_Db_Exception $e)
                {
                    // Based on unique index, this entry was already saved.
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        return;
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
            }
            else
            {
                if($currentBodiesCount['bodyCount'] != $json['BodyCount'])
                {
                    if($json['BodyCount'] > $currentBodiesCount['bodyCount'])
                    {
                        $update                 = array();
                        $update['bodyCount']    = $json['BodyCount'];

                        $systemsBodiesCountModel->updateByRefSystem($systemId, $update);
                    }
                    else
                    {
                        $registry = \Zend_Registry::getInstance();

                        if($registry->offsetExists('sentryClient'))
                        {
                            $sentryClient = $registry->offsetGet('sentryClient');
                            $sentryClient->captureMessage(
                                'Wrong bodyCount',
                                array('systemId' => $systemId,),
                                array('extra' => $json,)
                            );
                        }
                    }
                }
            }

            unset($systemsBodiesCountModel, $currentBodiesCount);
        }

        return static::$return;
    }
}