<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CodexEntry extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Check for Codex Entry.',
    ];



    public static function run($json)
    {
        // Skip them for now...
        if(array_key_exists('NewTraitsDiscovered', $json) || array_key_exists('Traits', $json) || array_key_exists('VoucherAmount', $json))
        {
            // Save until further processing
            $json['isError']            = 1;
            \Journal\Event::run($json);

            return static::$return;
        }

        // Skip before Q4 events...
        if(strtotime($json['timestamp']) < strtotime('2018-12-11 12:00:00'))
        {
            return static::$return;
        }

        $region         = null;
        $category       = null;
        $subCategory    = null;
        $type           = null;

        if(array_key_exists('Region', $json))
        {
            $region = \Alias\Codex\Region::getFromFd($json['Region']);

            if(is_null($region))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Mission::log('Alias\Codex\Region: ' . $json['Region']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('Category', $json))
        {
            $category = \Alias\Codex\Category::getFromFd($json['Category']);

            if(is_null($category))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Mission::log('Alias\Codex\Category: ' . $json['Category']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('SubCategory', $json))
        {
            $subCategory = \Alias\Codex\SubCategory::getFromFd($json['SubCategory']);

            if(is_null($subCategory))
            {
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Mission::log('Alias\Codex\SubCategory: ' . $json['SubCategory']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('Name', $json))
        {
            $type = \Alias\Codex\Type::getFromFd($json['Name']);

            if(is_null($type))
            {
                // Don't report for now ;)
                /*
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Mission::log('Alias\Codex\Type: ' . $json['Name']);
                */

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(!is_null($region) && !is_null($category) && !is_null($subCategory) && !is_null($type))
        {
            $codexModel = new \Models_Codex;
            $codexEntry = $codexModel->getByRefRegionAndRefType($region, $type);

            if(is_null($codexEntry))
            {
                $insert                     = array();
                $insert['refRegion']        = $region;
                $insert['refType']          = $type;
                $insert['firstReportedBy']  = static::$user->getId();
                $insert['firstReportedOn']  = $json['timestamp'];

                try
                {
                    $codexId    = $codexModel->insert($insert);
                    $codexEntry = array('id' => $codexId);
                }
                catch(\Zend_Db_Exception $e)
                {
                    // Based on unique index, this codex entry was already saved.
                    if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                    {
                        // All good, just grab it for further processing!
                        $codexEntry = $codexModel->getByRefRegionAndRefType($region, $type);
                    }
                    else
                    {
                        $codexEntry                 = null;
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

                unset($insert);
            }

            if(!is_null($codexEntry))
            {
                $codexEntry = $codexModel->getById($codexEntry['id']);

                // Was it before our previous first reporting?
                if(strtotime($json['timestamp']) < strtotime($codexEntry['firstReportedOn']))
                {
                    $codexModel->updateById(
                        $codexEntry['id'],
                        array(
                            'firstReportedBy'   => static::$user->getId(),
                            'firstReportedOn'   => $json['timestamp'],
                        )
                    );
                }

                // Don't rely on SystemAddress when it's obvisouly bugged...
                if(array_key_exists('SystemAddress', $json) && $json['SystemAddress'] == 1)
                {
                    unset($json['SystemAddress']);
                }

                $systemId = static::findSystemId($json);

                if(!is_null($systemId))
                {
                    // Add user report for system
                    $codexReportsModel = new \Models_Codex_Reports;

                    try
                    {
                        $codexReportsModel->insert(array(
                            'refCodex'      => $codexEntry['id'],
                            'refUser'       => static::$user->getId(),
                            'refSystem'     => $systemId,
                            'reportedOn'    => $json['timestamp'],
                        ));
                    }
                    catch(\Zend_Db_Exception $e)
                    {
                        // Based on unique index, this codex entry was already saved.
                        if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                        {
                            // All good
                        }
                        else
                        {
                            $codexEntry                 = null;
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

                    return static::$return;
                }
            }
        }

        // Save until further processing
        $json['isError']            = 1;
        \Journal\Event::run($json);

        return static::$return;
    }
}