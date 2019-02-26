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
                    $systemsModel->updateById(
                        $currentSystem->getId(),
                        ['id64' => $json['SystemAddress']]
                    );
                }
            }
            elseif(static::$user->isGalacticTeam())
            {
                $systemsModel->insert([
                    'id64' => $json['SystemAddress'],
                    'name' => $json['Name']
                ]);
            }
        }

        return static::$return;
    }
}