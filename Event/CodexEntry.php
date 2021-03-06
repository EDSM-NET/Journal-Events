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
        // Skip before Q4 events...
        if(strtotime($json['timestamp']) < strtotime('2018-12-11 12:00:00'))
        {
            return static::$return;
        }

        $region         = null;
        $category       = null;
        $subCategory    = null;
        $type           = null;
        $traits         = array();

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
                static::$return['msgnum']   = 402;
                static::$return['msg']      = 'Item unknown';

                \EDSM_Api_Logger_Mission::log('Alias\Codex\Type: ' . $json['Name']);

                // Save in temp table for reparsing
                $json['isError']            = 1;
                \Journal\Event::run($json);

                return static::$return;
            }
        }

        if(array_key_exists('Traits', $json))
        {
            foreach($json['Traits'] AS $trait)
            {
                $currentTrait = \Alias\Codex\Traits::getFromFd($trait);

                if(is_null($currentTrait))
                {
                    static::$return['msgnum']   = 402;
                    static::$return['msg']      = 'Item unknown';

                    \EDSM_Api_Logger_Mission::log('Alias\Codex\Traits: ' . $trait);

                    // Save in temp table for reparsing
                    $json['isError']            = 1;
                    \Journal\Event::run($json);

                    return static::$return;
                }
                else
                {
                    $traits[] = $currentTrait;
                }
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
                $insert['traits']           = null;
                $insert['firstReportedBy']  = static::$user->getId();
                $insert['firstReportedOn']  = $json['timestamp'];

                if(count($traits) > 0)
                {
                    sort($traits);
                    $insert['traits']       = \Zend_Json::encode($traits);
                }

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

                        if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                        {
                            \Sentry\captureException($e);
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

                // Check for new traits!
                if(count($traits) > 0)
                {
                    $newTraits  = $traits;
                    $oldTraits  = array();

                    if(array_key_exists('traits', $codexEntry) && !is_null($codexEntry['traits']))
                    {
                        $oldTraits = \Zend_Json::decode($codexEntry['traits']);
                    }

                    $newTraits = array_unique(array_merge($newTraits, $oldTraits));

                    sort($oldTraits);
                    sort($newTraits);

                    if(count($newTraits) > 0 && $oldTraits != $newTraits)
                    {
                        $codexModel->updateById(
                            $codexEntry['id'],
                            array(
                                'traits'    => \Zend_Json::encode($newTraits),
                            )
                        );
                    }
                }

                // Add user traits!
                if(count($traits) > 0)
                {
                    $codexTraitsModel = new \Models_Codex_Traits;

                    foreach($traits AS $newTrait)
                    {
                        try
                        {
                            $codexTraitsModel->insert(array(
                                'refUser'       => static::$user->getId(),
                                'refType'       => $type,
                                'refTrait'      => $newTrait,
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

                                if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                                {
                                    \Sentry\captureException($e);
                                }
                            }
                        }
                    }
                }

                // Don't rely on SystemAddress when it's obviously bugged...
                if(array_key_exists('SystemAddress', $json) && $json['SystemAddress'] == 1)
                {
                    unset($json['SystemAddress']);
                }
                if(array_key_exists('System', $json) && array_key_exists('_systemName', $json) && is_null($json['_systemName']))
                {
                    $json['_systemName'] = $json['System'];
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

                            if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                            {
                                \Sentry\captureException($e);
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