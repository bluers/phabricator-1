<?php

final class PhabricatorTokensCurtainExtension
  extends PHUICurtainExtension {

  const EXTENSIONKEY = 'tokens.tokens';

  public function shouldEnableForObject($object) {
    return ($object instanceof PhabricatorTokenReceiverInterface);
  }

  public function getExtensionApplication() {
    return new PhabricatorTokensApplication();
  }

  public function buildCurtainPanel($object) {
    $viewer = $this->getViewer();

    $tokens_given = id(new PhabricatorTokenGivenQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->execute();
    if (!$tokens_given) {
      return null;
    }

    $author_phids = mpull($tokens_given, 'getAuthorPHID');
    $handles = $viewer->loadHandles($author_phids);

    Javelin::initBehavior('phabricator-tooltips');

    $tokensScoreAverage = 0;
    $scores = array('like-1' => 5, 'like-2' => 1, 'heart-1' => 5, 'heart-2' => 1, 'medal-1' => 2,
      'medal-2' => 3, 'medal-3' => 4, 'medal-4' => 0);
    $list = array();
    $list[] = array();
    foreach ($tokens_given as $token_given) {
      $token = $token_given->getToken();

      $tokensScoreAverage = $tokensScoreAverage + $scores[substr($token->getPHID(), 10)];

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht(
          '"%s" token, awarded by %s.',
          $token->getName(),
          $handles[$token_given->getAuthorPHID()]->getName()));

      $list[] = javelin_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'class' => 'token-icon',
          'meta' => array(
            'tip' => $handles[$token_given->getAuthorPHID()]->getName(),
          ),
        ),
        array(
          $aural,
          $token->renderIcon(),
        ));
    }

    $tokensScoreAverage = $tokensScoreAverage*1.0/count($tokens_given);
    $list[0] = javelin_tag(
      'span',
      array(
        'class' => 'phabricator-handle-tag-list-item',
        ), pht('Average Score: %s', sprintf("%.2f/5", $tokensScoreAverage)));

    return $this->newPanel()
      ->setHeaderText(pht('Tokens'))
      ->setOrder(30000);
     // ->appendChild($list); 只显示平均得分，不再显示列表
  }

}
