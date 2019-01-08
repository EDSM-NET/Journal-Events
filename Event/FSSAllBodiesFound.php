<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSSAllBodiesFound extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert/Update body count in current system.',
        'Lock the final body count',
    ];



    public static function run($json)
    {
        $systemId = static::findSystemId($json);

        if(!is_null($systemId))
        {
            $systemsBodiesCountModel    = new \Models_Systems_Bodies_Count;
            $currentBodiesCount         = $systemsBodiesCountModel->getByRefSystem($systemId);

            if(is_null($currentBodiesCount))
            {
                try
                {
                    $insert                 = array();
                    $insert['refSystem']    = $systemId;
                    $insert['bodyCount']    = $json['Count'];
                    $insert['isLocked']     = 1;

                    $systemsBodiesCountModel->insert($insert);

                    unset($insert);
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
                if($currentBodiesCount['bodyCount'] != $json['Count'])
                {
                    if(!array_key_exists('isLocked', $currentBodiesCount) || (array_key_exists('isLocked', $currentBodiesCount) && $currentBodiesCount['isLocked'] == 0))
                    {
                        $update                 = array();
                        $update['bodyCount']    = $json['Count'];
                        $update['isLocked']     = 1;

                        $systemsBodiesCountModel->updateByRefSystem($systemId, $update);

                        unset($update);
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