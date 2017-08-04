<?php

abstract class UserConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorPeopleApplication');
  }

  protected function buildUserInformationDictionary(
    PhabricatorUser $user,
    $with_email = false,
    $with_availability = false) {

    $roles = array();
    if ($user->getIsDisabled()) {
      $roles[] = 'disabled';
    }
    if ($user->getIsSystemAgent()) {
      $roles[] = 'agent';
    }
    if ($user->getIsMailingList()) {
      $roles[] = 'list';
    }
    if ($user->getIsAdmin()) {
      $roles[] = 'admin';
    }

    $primary = $user->loadPrimaryEmail();
    if ($primary && $primary->getIsVerified()) {
      $email = $primary->getAddress();
      $roles[] = 'verified';
    } else {
      $email = null;
      $roles[] = 'unverified';
    }

    if ($user->getIsApproved()) {
      $roles[] = 'approved';
    }

    if ($user->isUserActivated()) {
      $roles[] = 'activated';
    }

    $return = array(
      'phid'         => $user->getPHID(),
      'userName'     => $user->getUserName(),
      'realName'     => $user->getRealName(),
      'image'        => $user->getProfileImageURI(),
      'uri'          => PhabricatorEnv::getURI('/p/'.$user->getUsername().'/'),
      'roles'        => $roles,
      'regAsDev'     => $user->getRequestAsDev(),
      'dev'          => false,
    );

    {
      $projectQuery = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withMemberPHIDs(array($user->getPHID()));

      $policy_exception = null;
      try {
        $projects = $projectQuery->execute();
        foreach ($projects as $project){
          if (strcmp($project->getName(), '开发人员') == 0
            || strcmp($project->getName(), 'Developer') == 0
            || strcmp($project->getName(), 'Developers') == 0
            | strcmp($project->getName(), 'Dev') == 0){
            $return['dev'] = true;
            break;
          }
        }
      } catch (PhabricatorPolicyException $ex) {
        $projects = null;
      }
    }

    if ($with_email) {
      $return['primaryEmail'] = $email;
    }

    if ($with_availability) {
      // TODO: Modernize this once we have a more long-term view of what the
      // data looks like.
      $until = $user->getAwayUntil();
      if ($until) {
        $return['currentStatus'] = 'away';
        $return['currentStatusUntil'] = $until;
      }
    }

    return $return;
  }

}
