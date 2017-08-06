<?php

final class UserJoinDevConduitAPIMethod extends UserConduitAPIMethod {

  public function getAPIMethodName() {
    return 'user.joindev';
  }

  public function getMethodDescription() {
    return pht('The logged-in user want to be a developer.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function getRequiredScope() {
    return self::SCOPE_ALWAYS;
  }

  protected function execute(ConduitAPIRequest $request) {
    $person = id(new PhabricatorPeopleQuery())
      ->setViewer($request->getUser())
      ->needProfileImage(true)
      ->withPHIDs(array($request->getUser()->getPHID()))
      ->executeOne();

    $user = $person;
    $user->setRequestAsDev(true);

    //添加用户到特定的project中，如果该project不存在，那么手动创建该project
    $query = id(new PhabricatorProjectQuery())
      ->needImages(false);
    $tokens = PhabricatorTypeaheadDatasource::tokenizeString('开发人员待批准');
    $query->withNameTokens($tokens);//->setParameter('status', 'archived');
    $query->setViewer(PhabricatorUser::getOmnipotentUser());
    $projects = $query->execute();

    if(count($projects) > 0){

    }
    else{
      //创建project
      $viewer = id(new PhabricatorUser())->loadOneWhere(
        'username = %s',
        'admin');

      $project = PhabricatorProject::initializeNewProject($viewer);

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_CREATE);

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_NAME)
        ->setNewValue('开发人员待批准');


      $editor = id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSource(PhabricatorContentSource::newForSource(PhabricatorWebContentSource::SOURCECONST))
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      $projects = $query->execute();

    }
    $projects = array_values($projects);

    $project = $projects[0];


    $user->openTransaction();

    $editor = id(new PhabricatorUserEditor())
    ->setActor($user);

    $editor->updateUser($user);

    $user->saveTransaction();

    $edge_action = '+';

    $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

    $member_spec = array(
      $edge_action => array($user->getPHID() => $user->getPHID()),
    );

    $xactions = array();
    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $type_member)
      ->setNewValue($member_spec);


    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($user)
      ->setContentSource(PhabricatorContentSource::newForSource(PhabricatorWebContentSource::SOURCECONST))
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($project, $xactions);

    return $this->buildUserInformationDictionary(
      $person,
      $with_email = true,
      $with_availability = false);
  }

}
