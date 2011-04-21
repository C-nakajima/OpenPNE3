<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * MemberProfilePeer
 *
 * @package    OpenPNE
 * @subpackage model
 * @author     Kousuke Ebihara <ebihara@tejimaya.com>
 */
class MemberProfilePeer extends BaseMemberProfileNestedSetPeer
{
  public static function getProfileListByMemberId($memberId)
  {
    $profiles = array();

    $c = new Criteria();

    parent::addSelectColumns($c);

    $c->addSelectColumn(ProfilePeer::NAME);
    $c->addSelectColumn(ProfileI18nPeer::CAPTION);

    $c->add(self::MEMBER_ID, $memberId);
    $c->add(self::LFT_KEY, 1);
    $c->add(ProfileI18nPeer::CULTURE, sfPropel::getDefaultCulture());
    $c->addJoin(ProfilePeer::ID, ProfileI18nPeer::ID);
    $c->addJoin(ProfilePeer::ID, MemberProfilePeer::PROFILE_ID);

    $c->addAscendingOrderByColumn(ProfilePeer::SORT_ORDER);

    $stmt = self::doSelectStmt($c);
    while ($row = $stmt->fetch(PDO::FETCH_NUM))
    {
      $obj = new MemberProfile();
      $obj->hydrateProfiles($row);
      $profiles[] = $obj;
    }

    return $profiles;
  }

  public static function getViewableProfileListByMemberId($memberId, $myMemberId = null)
  {
    if(is_null($myMemberId))
    {
      $myMemberId = sfContext::getInstance()->getUser()->getMemberId();
    }

    $profiles = self::getProfileListByMemberId($memberId);
    foreach ($profiles as $key => $profile)
    {
      if (!$profile->isViewable($myMemberId))
      {
        unset($profiles[$key]);
      }
    }

    return $profiles;
  }

  public static function retrieveByMemberIdAndProfileId($memberId, $profileId)
  {
    $c = new Criteria();
    $c->add(self::MEMBER_ID, $memberId);
    $c->add(self::PROFILE_ID, $profileId);

    $result = self::doSelectOne($c);
    return $result;
  }

  public static function retrieveByMemberIdAndProfileName($memberId, $profileName)
  {
    $c = new Criteria();
    $c->add(ProfilePeer::NAME, $profileName);
    $c->add(MemberProfilePeer::MEMBER_ID, $memberId);
    $c->addJoin(MemberProfilePeer::PROFILE_ID, ProfilePeer::ID);
    return MemberProfilePeer::doSelectOne($c);
  }

  public static function searchMemberIds($profile = array(), $ids = array(), $isCheckPublicFlag = true)
  {
    $publicFlag = ($isCheckPublicFlag) ? 1 : null;

    if (!is_array($profile)) return $ids;
    foreach ($profile as $key => $value)
    {
      $item = ProfilePeer::retrieveByName($key);
      $_result = array();
      $column = self::VALUE;
      if ($item->getFormType() === 'date')
      {
        $options = $item->getProfileOptions();
        foreach ($value as $k => $v)
        {
          $option = array_shift($options);
          if ($v)
          {
            $ids = self::filterMemberIdByProfileOption($ids, $column, $v, $option, array(), $publicFlag);
          }
        }
        continue;
      }
      elseif ($item->isMultipleSelect() || $item->isSingleSelect())
      {
        $column = self::PROFILE_OPTION_ID;
      }

      $ids = self::filterMemberIdByProfile($ids, $column, $value, $item, array(), $publicFlag);
    }

    return $ids;
  }

  public static function filterMemberIdByProfile($ids, $column, $value, Profile $item, $choices, $publicFlag = 1)
  {
    $_result = array();

    $c = opFormItemGenerator::filterSearchCriteria(null, $column, $value, $item->toArray(), array());
    $c->clearSelectColumns()->addSelectColumn(self::MEMBER_ID);
    $c->setIgnoreCase(false);
    $c->add(self::PROFILE_ID, $item->getId());

    if (is_integer($publicFlag))
    {
      if ($item->isMultipleSelect())
      {
        $c->addAlias('pm', self::TABLE_NAME);
        $c->addJoin(self::TREE_KEY, self::alias('pm', self::ID));
        $c->add(self::alias('pm', self::PUBLIC_FLAG), $publicFlag);
      }
      else
      {
        $c->add(self::PUBLIC_FLAG, $publicFlag);
      }
    }

    $stmt = self::doSelectStmt($c);
    while ($raw = $stmt->fetch(PDO::FETCH_NUM))
    {
      $_result[] = $raw[0];
    }

    if (is_array($ids))
    {
      $ids = array_values(array_intersect($ids, $_result));
    }
    else
    {
      $ids = array_values($_result);
    }

    return $ids;
  }

  public static function filterMemberIdByProfileOption($ids, $column, $value, ProfileOption $item, $choices, $publicFlag = 1)
  {
    $_result = array();
    $c = new Criteria();
    $c->add($column, $value);
    $c->clearSelectColumns()->addSelectColumn(self::MEMBER_ID);
    $c->setIgnoreCase(false);
    $c->add(self::PROFILE_OPTION_ID, $item->getId());

    if (is_integer($publicFlag))
    {
      $c->addAlias('pm', self::TABLE_NAME);
      $c->addJoin(self::TREE_KEY, self::alias('pm', self::ID));
      $c->add(self::alias('pm', self::PUBLIC_FLAG), $publicFlag);
    }

    $stmt = self::doSelectStmt($c);
    while ($raw = $stmt->fetch(PDO::FETCH_NUM))
    {
      $_result[] = $raw[0];
    }

    if (is_array($ids))
    {
      $ids = array_values(array_intersect($ids, $_result));
    }
    else
    {
      $ids = array_values($_result);
    }

    return $ids;
  }

  public static function makeRoot($memberId, $profileId)
  {
    $root = new MemberProfile();
    $root->makeRoot();
    $root->setMemberId($memberId);
    $root->setProfileId($profileId);
    $root->save();

    $root->setScopeIdValue($root->getId());
    $root->save();

    return $root;
  }

  public static function createChild(MemberProfile $parent, $memberId, $profileId, $optionIds, $values = array())
  {
    foreach ($optionIds as $i => $optionId)
    {
      $childProfile = new MemberProfile();
      $childProfile->setMemberId($memberId);
      $childProfile->setProfileId($profileId);
      $childProfile->setProfileOptionId($optionId);
      $childProfile->insertAsLastChildOf($parent);
      if (isset($values[$i]))
      {
        $childProfile->setValue($values[$i]);
      }
      $childProfile->save();
    }
  }
}
