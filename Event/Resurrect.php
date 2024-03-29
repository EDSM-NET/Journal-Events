<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Resurrect extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove resurrect cost from commander credits',
        'Mark current ship as sold if free rebuy',
    ];



    public static function run($json)
    {
        if($json['Cost'] > 0)
        {
            static::handleCredits(
                'Resurrect',
                - (int) $json['Cost'],
                static::generateDetails($json),
                $json
            );
        }
        // If option if free, then mark the shipId as Sold
        elseif(array_key_exists('Option', $json) && $json['Option'] == 'free')
        {
            $shipId = static::findShipId($json);

            if(!is_null($shipId))
            {
                $usersShipsModel    = new \Models_Users_Ships;

                $currentShipId      = $usersShipsModel->fetchRow(
                    $usersShipsModel->select()
                                    ->from($usersShipsModel, ['id'])
                                    ->where('refUser = ?', static::$user->getId())
                                    ->where('refShip = ?', $shipId)
                                    ->where('sell = ?', 0)
                                    ->where('dateUpdated < ?', $json['timestamp'])
                );

                // Update ship
                if(!is_null($currentShipId))
                {
                    $currentShipId  = $currentShipId->id;

                    $currentShip    = $usersShipsModel->getById($currentShipId);
                    $update         = array();

                    if(!array_key_exists('dateUpdated', $currentShip) || is_null($currentShip['dateUpdated']) || strtotime($currentShip['dateUpdated']) < strtotime($json['timestamp']))
                    {
                        $update['sell']             = 1;
                        $update['dateUpdated']      = $json['timestamp'];
                    }

                    if(count($update) > 0)
                    {
                        $usersShipsModel->updateById($currentShipId, $update);
                    }

                    unset($currentShip, $update);
                }

                unset($usersShipsModel, $currentShipId);
            }
        }

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details    = array();

        $systemId   = static::findSystemId($json);
        if(!is_null($systemId))
        {
            $details['systemId'] = $systemId;
        }

        $details['bankrupt'] = $json['Bankrupt'];

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}