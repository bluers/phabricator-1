<?php

final class PhabricatorPeopleMakeDevController
  extends PhabricatorPeopleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $done_uri = $this->getApplicationURI("manage/{$id}/");

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $done_uri);

    if ($user->getPHID() == $viewer->getPHID()) {
      return $this->newDialog()
        ->setTitle(pht('Your Way is Blocked'))
        ->appendParagraph(
          pht(
            'After a time, your efforts fail. You can not adjust your own '.
            'status as an developer.'))
        ->addCancelButton($done_uri, pht('Accept Fate'));
    }

    if ($request->isFormPost()) {
      id(new PhabricatorUserEditor())
        ->setActor($viewer)
        ->makeDevUser($user, !$user->getIsDev());

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($user->getIsDev()) {
      $title = pht('Remove as Developer?');
      $short = pht('Remove Developer');
      $body = pht(
        'Remove %s as a developer?',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Remove Developer');
    } else {
      $title = pht('Make Developer?');
      $short = pht('Make Developer');
      $body = pht(
        'Empower %s as a developer?',
        phutil_tag('strong', array(), $user->getUsername()));
      $submit = pht('Make Developer');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setShortTitle($short)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);
  }

}
