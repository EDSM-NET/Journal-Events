<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Statistics extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update the commander statistics.',
    ];
    
    private static $statistics      = [
        'Bank_Account'                  => [
            'Current_Wealth',
            'Spent_On_Ships',
            'Spent_On_Outfitting',
            'Spent_On_Repairs',
            'Spent_On_Fuel',
            'Spent_On_Ammo_Consumables',
            'Insurance_Claims',
            'Spent_On_Insurance',
        ],
        'Combat'                        => [
            'Bounties_Claimed',
            'Bounty_Hunting_Profit',
            'Combat_Bonds',
            'Combat_Bond_Profits',
            'Assassinations',
            'Assassination_Profits',
            'Highest_Single_Reward',
            'Skimmers_Killed',
        ],
        'Crime'                         => [
            'Notoriety',
            'Fines',
            'Total_Fines',
            'Bounties_Received',
            'Total_Bounties',
            'Highest_Bounty',
        ],
        'Smuggling'                     => [
            'Black_Markets_Traded_With',
            'Black_Markets_Profits',
            'Resources_Smuggled',
            'Average_Profit',
            'Highest_Single_Transaction',
        ],
        'Trading'                       => [
            'Markets_Traded_With',
            'Market_Profits',
            'Resources_Traded',
            'Average_Profit',
            'Highest_Single_Transaction',
        ],
        'Mining'                        => [
            'Mining_Profits',
            'Quantity_Mined',
            'Materials_Collected',
        ],
        'Exploration'                   => [
            'Systems_Visited',
            'Fuel_Scooped',
            'Fuel_Purchased',
            'Exploration_Profits',
            'Planets_Scanned_To_Level_2',
            'Planets_Scanned_To_Level_3',
            'Highest_Payout',
            'Total_Hyperspace_Distance',
            'Total_Hyperspace_Jumps',
            'Greatest_Distance_From_Start',
            'Time_Played',
        ],
        'Passengers'                    => [
            'Passengers_Missions_Bulk',
            'Passengers_Missions_VIP',
            'Passengers_Missions_Delivered',
            'Passengers_Missions_Ejected',
        ],
        'Search_And_Rescue'             => [
            'SearchRescue_Traded',
            'SearchRescue_Profit',
            'SearchRescue_Count',
        ],
        'Crafting'                      => [
            'Spent_On_Crafting',
            'Count_Of_Used_Engineers',
            'Recipes_Generated',
            'Recipes_Generated_Rank_1',
            'Recipes_Generated_Rank_2',
            'Recipes_Generated_Rank_3',
            'Recipes_Generated_Rank_4',
            'Recipes_Generated_Rank_5',
            'Recipes_Applied',
            'Recipes_Applied_Rank_1',
            'Recipes_Applied_Rank_2',
            'Recipes_Applied_Rank_3',
            'Recipes_Applied_Rank_4',
            'Recipes_Applied_Rank_5',
            'Recipes_Applied_On_Previously_Modified_Modules',
        ],
        'Crew'                          => [
            'NpcCrew_TotalWages',
            'NpcCrew_Hired',
            'NpcCrew_Fired',
            'NpcCrew_Died',
        ],
        'Multicrew'                     => [
            'Multicrew_Time_Total',
            'Multicrew_Gunner_Time_Total',
            'Multicrew_Fighter_Time_Total',
            'Multicrew_Credits_Total',
            'Multicrew_Fines_Total',
        ],
        'CQC'                           => [
            'CQC_Time_Played',
            'CQC_KD',
            'CQC_Kills',
            'CQC_WL',
        ],
    ];
    
    
    
    public static function run($json)
    {
        $usersStatisticsModel       = new \Models_Users_Statistics;
        $currentStatistics          = $usersStatisticsModel->getByRefUser(static::$user->getId());
        $lastStatisticsUpdate       = strtotime('1WEEK AGO');
        
        if(!is_null($currentStatistics) && array_key_exists('lastStatisticsUpdate', $currentStatistics))
        {
            $lastStatisticsUpdate = strtotime($currentStatistics['lastStatisticsUpdate']);
        }
        
        if($lastStatisticsUpdate < strtotime($json['timestamp']))
        {
            $insert = array();
            
            foreach(static::$statistics AS $category => $values)
            {
                foreach($values AS $value)
                {
                    if(array_key_exists($category, $json) && array_key_exists($value, $json[$category]))
                    {
                        $dbKey = str_replace('_', '', lcfirst($category)) . str_replace('_', '', $value);
                        
                        if(is_null($currentStatistics) || (!is_null($currentStatistics) && $currentStatistics[$dbKey] != $json[$category][$value]))
                        {
                            $insert[$dbKey] = $json[$category][$value];
                        }
                        
                        // BADGES
                        if($dbKey == 'explorationTotalHyperspaceDistance')
                        {
                            if($json[$category][$value] >= 100000) { static::$user->giveBadge(100); }
                            if($json[$category][$value] >= 200000) { static::$user->giveBadge(110); }
                            if($json[$category][$value] >= 500000) { static::$user->giveBadge(120); }
                            if($json[$category][$value] >= 1000000) { static::$user->giveBadge(140); }
                            if($json[$category][$value] >= 2500000) { static::$user->giveBadge(145); }
                        }
                        
                        if($dbKey == 'explorationTimePlayed')
                        {
                            if($json[$category][$value] >= 3600) { static::$user->giveBadge(7500); }
                            if($json[$category][$value] >= 86400) { static::$user->giveBadge(7510); }
                            if($json[$category][$value] >= 3600000) { static::$user->giveBadge(7520); }
                        }
                        
                        if($dbKey == 'explorationGreatestDistanceFromStart')
                        {
                            if($json[$category][$value] >= 1000) { static::$user->giveBadge(200); }
                            if($json[$category][$value] >= 25000) { static::$user->giveBadge(210); }
                        }
                    }
                }
            }
            
            // Only make updates when values are different
            if(count($insert) > 0)
            {
                $insert['lastStatisticsUpdate'] = $json['timestamp'];
                
                try
                {
                    if(!is_null($currentStatistics))
                    {
                        $usersStatisticsModel->updateByRefUser(
                            static::$user->getId(),
                            $insert
                        );
                    }
                    else
                    {
                        $insert['refUser'] = static::$user->getId();
                        $usersStatisticsModel->insert($insert);
                    }
                }
                catch(\Zend_Db_Exception $e)
                {
                    static::$return['msgnum']   = 500;
                    static::$return['msg']      = 'Exception: ' . $e->getMessage();
                    $json['isError']            = 1;
                    
                    \Journal\Event::run($json);
                }
            }
            
            unset($insert);
        }
        else
        {
            static::$return['msgnum']   = 102;
            static::$return['msg']      = 'Message older than the stored one';
        }
        
        unset($usersStatisticsModel, $currentStatistics);
        
        return static::$return;
    }
}