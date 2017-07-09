<?php

final class TokenGiveConduitAPIMethod extends TokenConduitAPIMethod {

  public function getAPIMethodName() {
    return 'token.give';
  }

  public function getMethodDescription() {
    return pht('Give or change a token.');
  }

  protected function defineParamTypes() {
    return array(
      'tokenPHID'   => 'phid|null',
      'objectPHID'  => 'phid',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function execute(ConduitAPIRequest $request) {
    $content_source = $request->newContentSource();

    $editor = id(new PhabricatorTokenGivenEditor())
      ->setActor($request->getUser())
      ->setContentSource($content_source);

    if ($request->getValue('tokenPHID')) {

      $tokenPHID = $request->getValue('tokenPHID');

      if($tokenPHID >= 0 && $tokenPHID <= 5 ){

        $viewer = $request->getUser();

        $tokens = id(new PhabricatorTokenQuery())
          ->setViewer($viewer)
          ->execute();
        if ($tokens) {
          /**
          array('heart-2', pht('Heartbreak')),//1
          array('medal-1', pht('Orange Medal')),//2
          array('medal-2', pht('Grey Medal')),//3
          array('medal-3', pht('Yellow Medal')),//4
          array('heart-1', pht('Love')),//5
           *
           */
          $scores = array(5 => 'heart-1', 1 => 'heart-2', 2 => 'medal-1',
            3 => 'medal-2', 4 => 'medal-3', 0 => 'medal-4');
          $phid_suffix = $scores[intval($tokenPHID)];
          foreach ($tokens as $token_given) {
            $token = $token_given;
            if(strcmp(substr($token->getPHID(), 10), $phid_suffix) == 0){
              $tokenPHID = $token->getPHID();
              break;
            }
          }
        }
      }

      $editor->addToken(
        $request->getValue('objectPHID'),
        $tokenPHID);
    } else {
      $editor->deleteToken($request->getValue('objectPHID'));
    }
  }

}
