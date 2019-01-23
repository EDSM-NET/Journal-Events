<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class BuyTradeData extends Event
{
    use \Journal\Common\Credits;

    protected static $isOK          = true;
    protected static $description   = [
        'Remove trade data buy price from commander credits.',
    ];



    public static function run($json)
    {
        static::handleCredits(
            'BuyTradeData',
            - (int) $json['Cost'],
            static::generateDetails($json),
            $json
        );

        return static::$return;
    }

    static private function generateDetails($json)
    {
        $details        = array();

        if(array_key_exists('System', $json))
        {
            $systemsModel   = new \Models_Systems;
            $systemName     = $json['System'];
            $currentSystem  = $systemsModel->getByName($systemName);

            if(!is_null($currentSystem))
            {
                $currentSystem = \Component\System::getInstance($currentSystem['id']);

                // Follow merged systems
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
                        $details['system'] = $systemName;
                    }
                }

                if(!array_key_exists('system', $details))
                {
                    // Only grab name on duplicate because we do not have coordinates
                    $duplicates = $currentSystem->getDuplicates();
                    if(!is_null($duplicates) && is_array($duplicates) && count($duplicates) > 0)
                    {
                        $details['system'] = $systemName;
                    }
                    else
                    {
                        $details['system'] = $currentSystem->getId();
                    }
                }
            }
            else
            {
                $details['system'] = $systemName;
            }

            unset($systemsModel, $systemName, $currentSystem);
        }

        if(count($details) > 0)
        {
            ksort($details);
            return \Zend_Json::encode($details);
        }

        return null;
    }
}