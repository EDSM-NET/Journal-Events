<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class FSDTarget extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update a system ID64 if missing',
    ];
    protected static $exceptions    = [
        13889,
    ];

    public static function run($json)
    {
        // Only use recent events
        if(strtotime($json['timestamp']) >= (time() - (86400 * 7)) && array_key_exists('Name', $json) && array_key_exists('SystemAddress', $json))
        {
            $systemsModel   = new \Models_Systems;
            $systemId       = $systemsModel->getByName($json['Name']);

            if(!is_null($systemId))
            {
                $currentSystem = \Component\System::getInstance($systemId['id']);

                if(is_null($currentSystem->getId64()))
                {
                    try
                    {
                        $systemsModel->updateById(
                            $currentSystem->getId(),
                            ['id64' => $json['SystemAddress']]
                        );
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                        {
                            \Sentry\captureException($e);
                        }
                    }
                }
            }
            elseif(static::$user->isGalacticTeam() || in_array(static::$user->getId(), static::$exceptions))
            {
                $systemsModel->insert([
                    'id64' => $json['SystemAddress'],
                    'name' => $json['Name']
                ]);
                static::$return['systemCreated']    = true;
            }
        }

        return static::$return;
    }
}