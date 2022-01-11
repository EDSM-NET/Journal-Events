<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class USSDrop extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Link USS Drop to current system.',
    ];



    public static function run($json)
    {
        if(empty($json['USSType']))
        {
            return static::$return;
        }

        $aliasClass     = 'Alias\System\UssDrop';
        $currentItemId  = $aliasClass::getFromFd($json['USSType']);

        // Check if USS Type is known in EDSM
        if(is_null($currentItemId))
        {
            static::$return['msgnum']   = 402;
            static::$return['msg']      = 'Item unknown';

            \EDSM_Api_Logger_Alias::log($aliasClass . ': ' . $json['USSType']);

            // Save in temp table for reparsing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        $systemId = static::findSystemId($json);

        if(!is_null($systemId))
        {
            $databaseModel      = new \Models_Systems_UssDrop;

            $isAlreadyStored    = $databaseModel->fetchRow(
                $databaseModel->select()
                                  ->where('refUser = ?', static::$user->getId())
                                  ->where('refSystem = ?', $systemId)
                                  ->where('type = ?', $currentItemId)
                                  ->where('threat = ?', $json['USSThreat'])
                                  ->where('dateEvent = ?', $json['timestamp'])
            );

            if(is_null($isAlreadyStored))
            {
                $insert = array();
                $insert['refUser']      = static::$user->getId();
                $insert['refSystem']    = $systemId;
                $insert['type']         = $currentItemId;
                $insert['threat']       = $json['USSThreat'];
                $insert['dateEvent']    = $json['timestamp'];

                $databaseModel->insert($insert);

                unset($insert);
            }
            else
            {
                static::$return['msgnum']   = 101;
                static::$return['msg']      = 'Message already stored';
            }

            unset($databaseModel, $isAlreadyStored);
        }

        return static::$return;
    }
}