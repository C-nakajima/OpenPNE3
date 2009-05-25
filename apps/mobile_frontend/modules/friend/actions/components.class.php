<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

class friendComponents extends sfOpenPNECommunityComponents
{
  public function executeFriendListBox($request)
  {
    $memberId = $this->getUser()->getMemberId();
    if ($request->hasParameter('id'))
    {
      $memberId = $request->getParameter('id');
    }
    $this->member = MemberPeer::retrieveByPk($memberId);
    $c = new Criteria();
    $c->addAscendingOrderByColumn(Propel::getDB()->random(time()));
    $this->row = $this->gadget->getConfig('row');
    $this->friends = $this->member->getFriends($this->row, $c);
  }
}
