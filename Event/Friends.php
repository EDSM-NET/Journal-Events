<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal\Event;
use         Journal\Event;

class Friends extends Event
{
    protected static $isOK          = true;
    protected static $description   = [
        'Update friends status on EDSM',
    ];



    public static function run($json)
    {
        // User doesn't want to link, but if lost we need to remove it
        if(static::$user->addFriendsFromJournal() === false && !in_array(strtolower($json['Status']), array('lost')))
        {
            return static::$return;
        }

        if(array_key_exists('Status', $json) && array_key_exists('Name', $json))
        {
            if(in_array(strtolower($json['Status']), ['online', 'offline', 'added']))
            {
                // Check if friend is an EDSM user
                $usersModel     = new \Models_Users;
                $isFriendUser   = $usersModel->getByName($json['Name']);

                if(!is_null($isFriendUser))
                {
                    $isFriendUser = \Component\User::getInstance($isFriendUser['id']);

                    if($isFriendUser->addFriendsFromJournal() === false)
                    {
                        return static::$return;
                    }

                    $friendsModel   = new \Models_Users_Friends;
                    $areFriends     = $friendsModel->getStatusByRefUserAndRefFriend(static::$user->getId(), $isFriendUser->getId());

                    if(is_null($areFriends))
                    {
                        try
                        {
                            $insert                 = array();
                            $insert['refUser']      = static::$user->getId();
                            $insert['refFriend']    = $isFriendUser->getId();
                            $insert['status']       = 1;
                            $insert['dateRequest']  = $json['timestamp'];
                            $insert['dateAccepted'] = $json['timestamp'];

                            $friendsModel->insert($insert);

                            unset($insert);
                        }
                        catch(\Zend_Db_Exception $e)
                        {
                            // Based on unique index, this journal entry was already saved.
                            if(strpos($e->getMessage(), '1062 Duplicate') !== false)
                            {

                            }
                            else
                            {
                                static::$return['msgnum']   = 500;
                                static::$return['msg']      = 'Exception: ' . $e->getMessage();

                                if(defined('APPLICATION_SENTRY') && APPLICATION_SENTRY === true)
                                {
                                    \Sentry\captureException($e);
                                }
                            }
                        }
                    }
                    else
                    {
                        // Users are still friend over EDSM check if dates are earlier
                        if($areFriends['status'] == 1 && in_array(strtolower($json['Status']), ['online', 'offline']))
                        {
                            $update = array();

                            if(strtotime($areFriends['dateRequest']) < strtotime($json['timestamp']))
                            {
                                $update['dateRequest'] = $json['timestamp'];
                            }
                            if(strtotime($areFriends['dateAccepted']) < strtotime($json['timestamp']))
                            {
                                $update['dateAccepted'] = $json['timestamp'];
                            }

                            if(count($update) > 0)
                            {
                                $friendsModel->updateByRefUserAndRefFriend(
                                    $areFriends['refUser'],
                                    $areFriends['refFriend'],
                                    $update
                                );
                            }

                            unset($update);
                        }
                    }
                }

                unset($usersModel, $isFriendUser);
            }
            elseif(in_array(strtolower($json['Status']), ['lost']))
            {
                // Check if friend is an EDSM user
                $usersModel     = new \Models_Users;
                $isFriendUser   = $usersModel->getByName($json['Name']);

                if(!is_null($isFriendUser))
                {
                    $isFriendUser   = \Component\User::getInstance($isFriendUser['id']);
                    $friendsModel   = new \Models_Users_Friends;
                    $areFriends     = $friendsModel->getStatusByRefUserAndRefFriend(static::$user->getId(), $isFriendUser->getId());

                    if(!is_null($areFriends))
                    {
                        if(strtotime($areFriends['dateAccepted']) < strtotime($json['timestamp']))
                        {
                            $friendsModel->deleteByRefUserAndRefFriend(
                                $areFriends['refUser'],
                                $areFriends['refFriend']
                            );
                        }
                    }
                }

                unset($usersModel, $isFriendUser);
            }
            elseif(in_array(strtolower($json['Status']), array('requested', 'declined')))
            {
                // Do nothing
            }
            else
            {
                $json['isError'] = 1;
                \Journal\Event::run($json);
            }
        }

        return static::$return;
    }
}