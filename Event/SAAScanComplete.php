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

    // { "timestamp":"2018-10-31T13:30:12Z", "event":"SAAScanComplete", "BodyName":"HIP 63835 CD 8", "BodyID":16, "Discoverers":[ "Dirk Gently" ], "Mappers":[ "Askon Voidborn" ], "ProbesUsed":28, "EfficiencyTarget":21 }

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
                    // Update efficiencyTarget if needed
                    if($currentBody->efficiencyTarget != $json['EfficiencyTarget'])
                    {
                        $systemsBodiesModel->updateById(
                            $currentBody->id,
                            array(
                                'efficiencyTarget'  => $json['EfficiencyTarget'],
                            )
                        );
                    }

                    $currentBody = $currentBody->id;
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

        unset($systemsBodiesUsersSAAModel);

        return static::$return;
    }
}