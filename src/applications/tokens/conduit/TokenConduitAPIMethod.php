<?php

abstract class TokenConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorTokensApplication');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function buildTokenDicts(array $tokens) {
    assert_instances_of($tokens, 'PhabricatorToken');

    $list = array();
    foreach ($tokens as $token) {
      $list[] = array(
        'id' => $token->getID(),
        'name' => $token->getName(),
        'phid' => $token->getPHID(),
      );
    }

    return $list;
  }

  public function buildTokenGivenDicts(array $tokens_given) {
    assert_instances_of($tokens_given, 'PhabricatorTokenGiven');

    $list = array();
    foreach ($tokens_given as $given) {
      $list[] = array(
        'authorPHID'  => $given->getAuthorPHID(),
        'objectPHID'  => $given->getObjectPHID(),
        'tokenPHID'   => $given->getTokenPHID(),
        'dateCreated' => $given->getDateCreated(),
      );
    }

    $tokensScoreAverage = 0;
    $scores = array('like-1' => 5, 'like-2' => 1, 'heart-1' => 5, 'heart-2' => 1, 'medal-1' => 2,
      'medal-2' => 3, 'medal-3' => 4, 'medal-4' => 0);


    foreach ($tokens_given as $token_given) {
      $token = $token_given;
      $tokensScoreAverage = $tokensScoreAverage + $scores[substr($token->getTokenPHID(), 10)];
    }
    if(count($tokens_given) > 0){
      $tokensScoreAverage = $tokensScoreAverage*1.0/count($tokens_given);
      $list["score"] = $tokensScoreAverage;
    }

    return $list;
  }

}
