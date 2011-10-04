<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opValidatorProfile
 *
 * @package    OpenPNE
 * @subpackage validator
 * @author     Shogo Kawahara <kawahara@tejimaya.net>
 */
class opValidatorProfile extends sfValidatorBase
{
  /**
   * Configures the current validator.
   *
   * @see sfValidatorBase
   */
  protected function configure($options = array(), $messages = array())
  {
    $this->addRequiredOption('validator');
    $this->addOption('is_edit_public_flag', false);
    $this->setOption('required', $options['validator']->getOption('required'));
  }

  /**
   * @see sfValidatorBase
   */
  protected function doClean($value)
  {
    $clean = array();
    $clean['value'] = $this->getOption('validator')->clean($value['value']);

    if ($this->getOption('is_edit_public_flag'))
    {
      if (!isset($value['public_flag']))
      {
        throw new sfValidatorError($this, 'invalid');
      }
      $validator = new sfValidatorChoice(array('choices' => array_keys($this->profile->getPublicFlags())));
      $clean['public_flag'] = $validator->clean($value['public_flag']);
    }

    if ($this->profile->getIsUnique())
    {
      $profileId = $this->profile->getId();
      $memberId = sfContext::getInstance()->getUser()->getMemberId();
      $profiles = Doctrine::getTable('MemberProfile')->retrieveByProfileIdAndValueExceptMemberId($profileId, $value['value'], $memberId);

      if (0 < count($profiles))
      {
        throw new sfValidatorError($this, 'Duplicate');
      }
    }

    return $clean;
  }
}
