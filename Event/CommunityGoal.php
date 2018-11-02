<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class CommunityGoal extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Insert/Update community goal details.',
        'Insert/Update commander status on community goal.',
    ];



    public static function run($json)
    {
        if(array_key_exists('CurrentGoals', $json))
        {
            $communityGoalsModel        = new \Models_CommunityGoals;
            $usersCommunityGoalsModel   = new \Models_Users_CommunityGoals;
            $currentUserCommunityGoals  = $usersCommunityGoalsModel->getByRefUser(static::$user->getId());


            foreach($json['CurrentGoals'] AS $communityGoal)
            {
                $currentCG              = $communityGoalsModel->getById($communityGoal['CGID']);
                $communityGoal['Title'] = trim($communityGoal['Title']);

                // CG is not known, so insert it
                if(is_null($currentCG))
                {
                    $insert = array();
                    $insert['id']                   = $communityGoal['CGID'];
                    $insert['title']                = $communityGoal['Title'];
                    $insert['refSystem']            = static::getCommunityGoalSystem($communityGoal);
                    $insert['refStation']           = static::getCommunityGoalStation($communityGoal);
                    $insert['countContributor']     = $communityGoal['NumContributors'];
                    $insert['currentTotal']         = $communityGoal['CurrentTotal'];
                    $insert['topRankSize']          = ( (array_key_exists('TopRankSize', $communityGoal)) ? $communityGoal['TopRankSize'] : null );
                    $insert['tierReached']          = static::getTierReached($communityGoal, array());
                    $insert['isComplete']           = ( ($communityGoal['IsComplete'] === true) ? 1 : 0 );
                    $insert['dateExpiration']       = str_replace(['T', 'Z'], [' ', ''], $communityGoal['Expiry']);
                    $insert['dateLastUpdated']      = $json['timestamp'];

                    $communityGoalsModel->insert($insert);

                    unset($insert);
                }
                elseif(strtotime($currentCG['dateLastUpdated']) < strtotime($json['timestamp']))
                {
                    // Store the alternate title to use when other events doesn't have the CGID
                    if($currentCG['title'] != $communityGoal['Title'])
                    {
                        $currentCG['alternateTitle'] = \Zend_Json::decode($currentCG['alternateTitle']);

                        if(!in_array($communityGoal['Title'], $currentCG['alternateTitle']))
                        {
                            $currentCG['alternateTitle'][] = $communityGoal['Title'];
                        }

                        $currentCG['alternateTitle'] = \Zend_Json::encode($currentCG['alternateTitle']);
                    }

                    $update                         = array();
                    $update['alternateTitle']       = $currentCG['alternateTitle'];
                    $update['countContributor']     = $communityGoal['NumContributors'];
                    $update['currentTotal']         = $communityGoal['CurrentTotal'];
                    $update['topRankSize']          = ( (array_key_exists('TopRankSize', $communityGoal)) ? $communityGoal['TopRankSize'] : null );
                    $update['tierReached']          = static::getTierReached($communityGoal, $currentCG);
                    $update['isComplete']           = ( ($communityGoal['IsComplete'] === true) ? 1 : 0 );
                    $update['dateExpiration']       = str_replace(array('T', 'Z'), array(' ', ''), $communityGoal['Expiry']);
                    $update['dateLastUpdated']      = $json['timestamp'];

                    $communityGoalsModel->updateById($currentCG['id'], $update);

                    unset($update);
                }
                elseif(strtotime($currentCG['dateLastUpdated']) >= strtotime($json['timestamp']))
                {
                    $tierReached    = static::getTierReached($communityGoal, $currentCG);
                    $alternateTitle = $currentCG['alternateTitle'];

                    // Store the alternate title to use when other events doesn't have the CGID
                    if($currentCG['title'] != $communityGoal['Title'])
                    {
                        $alternateTitle = \Zend_Json::decode($alternateTitle);

                        if(!in_array($communityGoal['Title'], $alternateTitle))
                        {
                            $alternateTitle[] = $communityGoal['Title'];
                        }

                        $alternateTitle = \Zend_Json::encode($alternateTitle);
                    }

                    if($tierReached != $currentCG['tierReached'] || $alternateTitle != $currentCG['alternateTitle'])
                    {
                        $update                     = array();
                        $update['alternateTitle']   = $alternateTitle;
                        $update['tierReached']      = $tierReached;

                        $communityGoalsModel->updateById($currentCG['id'], $update);

                        unset($update);
                    }
                }

                // Player informations (PlayerContribution/PlayerPercentileBand/PlayerInTopRank)
                $currentUserCG              = null;

                if(!is_null($currentUserCommunityGoals))
                {
                    foreach($currentUserCommunityGoals AS $temp)
                    {
                        if($temp['refCG'] == $communityGoal['CGID'])
                        {
                            $currentUserCG = $temp;
                            break;
                        }
                    }
                }

                $update                     = array();
                $update['contribution']     = $communityGoal['PlayerContribution'];
                $update['percentileBand']   = $communityGoal['PlayerPercentileBand'];
                $update['inTopRank']        = ( (array_key_exists('PlayerInTopRank', $communityGoal) && $communityGoal['PlayerInTopRank'] === true) ? 1 : 0 );
                $update['dateLastUpdated']  = $json['timestamp'];

                // Insert if not found
                if(is_null($currentUserCG))
                {
                    $update['refCG']        = $communityGoal['CGID'];
                    $update['refUser']      = static::$user->getId();
                    $update['dateJoined']   = $json['timestamp'];

                    $usersCommunityGoalsModel->insert($update);
                }
                elseif(!array_key_exists('dateLastUpdated', $currentUserCG) || strtotime($currentUserCG['dateLastUpdated']) < strtotime($json['timestamp']))
                {
                    $usersCommunityGoalsModel->updateById($currentUserCG['id'], $update);
                }

                unset($update);
            }

            unset($communityGoalsModel, $usersCommunityGoalsModel, $currentUserCommunityGoals);
        }

        return static::$return;
    }

    private static function getCommunityGoalSystem($communityGoal)
    {
        // Special case as system have duplicates
        if($communityGoal['CGID'] == 423)
        {
            return 766;
        }

        $systemsModel   = new \Models_Systems;
        $system         = $systemsModel->getByName($communityGoal['SystemName']);

        if(!is_null($system))
        {
            $currentSystem = \Component\System::getInstance($system['id']);

            // Check system renamed/merged to another
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
                    return null;
                }
            }

            if(!is_null($currentSystem))
            {
                $duplicates = $currentSystem->getDuplicates();

                // Only unique system can be checked without the coordinates
                if(is_null($duplicates))
                {
                    return $currentSystem->getId();
                }
            }
        }

        return null;
    }

    private static function getCommunityGoalStation($communityGoal)
    {
        $refSystem = static::getCommunityGoalSystem($communityGoal);

        // Find station from the current system
        if(!is_null($refSystem))
        {
            $currentSystem = \Component\System::getInstance($refSystem);
            $stations      = $currentSystem->getStations();

            if(!is_null($stations))
            {
                foreach($stations AS $station)
                {
                    $currentStation = \EDSM_System_Station::getInstance($station['id']);

                    if($currentStation->getName() == $communityGoal['MarketName'])
                    {
                        return $currentStation->getId();
                    }
                }
            }
        }
        // Try to find a unique station
        else
        {
            // Find if it's a unique station
            $stationsModel = new \Models_Stations;
            $stations      = $stationsModel->fetchAll(
                $stationsModel->select()->where('name = ?', $communityGoal['MarketName'])
            );

            if(!is_null($stations) && count($stations) == 1)
            {
                $stations = $stations->toArray();
                return $stations[0]['id'];
            }
        }

        return null;
    }

    private static function getTierReached($communityGoal, $currentCG)
    {
        $tierReached = array();

        // Read initial value
        if(array_key_exists('tierReached', $currentCG) && !is_null($currentCG['tierReached']))
        {
            $tierReached = \Zend_Json::decode($currentCG['tierReached']);
        }

        // Insert new tier reach along with the bonus
        if(array_key_exists('TierReached', $communityGoal) && array_key_exists('Bonus', $communityGoal))
        {
            $tier = null;

            if(stripos($communityGoal['TierReached'], 'Tier ') !== false)
            {
                $tier = (int) str_replace('Tier ', '', $communityGoal['TierReached']);
            }
            elseif(stripos($communityGoal['TierReached'], 'Stufe ') !== false)
            {
                $tier = (int) str_replace('Stufe ', '', $communityGoal['TierReached']);
            }
            elseif(stripos($communityGoal['TierReached'], 'Niveau ') !== false)
            {
                $tier = (int) str_replace('Niveau ', '', $communityGoal['TierReached']);
            }

            if(!is_null($tier) && $tier > 0)
            {
                if(!array_key_exists($tier, $tierReached) || $tierReached[$tier] != $communityGoal['Bonus'])
                {
                    $tierReached[$tier] = $communityGoal['Bonus'];
                }
            }
        }

        ksort($tierReached);

        return \Zend_Json::encode($tierReached);
    }
}