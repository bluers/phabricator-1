<?php

final class PhabricatorRepositorySearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Repositories');
  }

  public function getApplicationClassName() {
    return 'PhabricatorDiffusionApplication';
  }

  public function newQuery() {
    return id(new PhabricatorRepositoryQuery())
      ->needProjectPHIDs(true)
      ->needCommitCounts(true)
      ->needMostRecentCommits(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Callsigns'))
        ->setKey('callsigns'),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('name'),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions($this->getStatusOptions()),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Hosted'))
        ->setKey('hosted')
        ->setOptions($this->getHostedOptions()),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Types'))
        ->setKey('types')
        ->setOptions(PhabricatorRepositoryType::getAllRepositoryTypes()),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('URIs'))
        ->setKey('uris')
        ->setDescription(
          pht('Search for repositories by clone/checkout URI.')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['callsigns']) {
      $query->withCallsigns($map['callsigns']);
    }

    if ($map['status']) {
      $status = idx($this->getStatusValues(), $map['status']);
      if ($status) {
        $query->withStatus($status);
      }
    }

    if ($map['hosted']) {
      $hosted = idx($this->getHostedValues(), $map['hosted']);
      if ($hosted) {
        $query->withHosted($hosted);
      }
    }

    if ($map['types']) {
      $query->withTypes($map['types']);
    }

    if (strlen($map['name'])) {
      $query->withNameContains($map['name']);
    }

    if ($map['uris']) {
      $query->withURIs($map['uris']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/diffusion/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active Repositories'),
      'all' => pht('All Repositories'),
    );

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'active':
        return $query->setParameter('status', 'open');
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      '' => pht('Active and Inactive Repositories'),
      'open' => pht('Active Repositories'),
      'closed' => pht('Inactive Repositories'),
    );
  }

  private function getStatusValues() {
    return array(
      '' => PhabricatorRepositoryQuery::STATUS_ALL,
      'open' => PhabricatorRepositoryQuery::STATUS_OPEN,
      'closed' => PhabricatorRepositoryQuery::STATUS_CLOSED,
    );
  }

  private function getHostedOptions() {
    return array(
      '' => pht('Hosted and Remote Repositories'),
      'phabricator' => pht('Hosted Repositories'),
      'remote' => pht('Remote Repositories'),
    );
  }

  private function getHostedValues() {
    return array(
      '' => PhabricatorRepositoryQuery::HOSTED_ALL,
      'phabricator' => PhabricatorRepositoryQuery::HOSTED_PHABRICATOR,
      'remote' => PhabricatorRepositoryQuery::HOSTED_REMOTE,
    );
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $repositories,
    PhabricatorSavedQuery $query) {
    return array_mergev(mpull($repositories, 'getProjectPHIDs'));
  }

  protected function renderResultList(
    array $repositories,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($repositories, 'PhabricatorRepository');

    $viewer = $this->requireViewer();

    $uris = id(new PhabricatorRepositoryURIQuery())
      ->setViewer($viewer)
      ->withRepositories($repositories)
      ->execute();
    $uri_groups = mgroup($uris, 'getRepositoryPHID');
    foreach ($repositories as $repository) {
      $repository_uris = idx($uri_groups, $repository->getPHID(), array());
      $repository->attachURIs($repository_uris);
    }

    $list_group = new PHUIObjectItemListView();

    $repositories_groupby = array($viewer->getUsername() => array(),"" => array(), );

    foreach ($repositories as $repository){
      $owner = $repository->getEditPolicy();
      if($owner && $owner != 'admin'){
        $user = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $owner);

        if($user){
          $owner = $user->getUserName();
          if($owner == $viewer->getUsername()){
            $repositories_groupby[$owner][] = $repository;
          }
          else{
            $repositories_groupby[""][] = $repository;
          }
        }
        else{
            $policies = id(new PhabricatorPolicyQuery())
              ->setViewer($viewer)
              ->withPHIDs(array($owner))
              ->execute();

            if($policies) {
              $policy = head($policies);
              $rule_data = $policy->getRules();
              $users = array();
              $admin_user = null;
              foreach ($rule_data as $rule) {
                if ($rule["action"] == "allow" && $rule["rule"] == "PhabricatorUsersPolicyRule") {
                  foreach ($rule["value"] as $userPHID) {
                    $actor = id(new PhabricatorUser())->loadOneWhere(
                      'phid = %s',
                      $userPHID);

                    if ($actor->getUserName() == "admin") {
                      $admin_user = $actor->getUserName();
                    }
                    $users[] = $actor->getUserName();
                  }
                }
              }//foreach ($rule_data as $rule)
              if (count($users) > 0) {
                if (count($users) == 1) {
                  $users = array_diff($users, [$admin_user]);
                  $repositories_groupby[$users[0]][] = $repository;
                }
                else{
                  $repositories_groupby[""][] = $repository;
                }
              } else {
                $repositories_groupby[""][] = $repository;
              }
            }
            else $repositories_groupby[""][] = $repository;
        }
      }
    }

    foreach ($repositories_groupby as $key => $repositores_group){


      $list = new PHUIObjectItemListView();
      $this->renderRepositories($repositores_group, $viewer, $handles, $list);

      $groupby_header = new PHUIObjectBoxView();
      if($key == ""){
        $groupby_header->setHeaderText(pht("All")."(".pht("Total %s", count($repositores_group)).")");
      }
      else{
        $groupby_header->setHeaderText($key."(".pht("Total %s", count($repositores_group)).")");
      }


      $groupby_view = new PHUITwoColumnView();

      $groupby_view->setHeader($groupby_header);
      $groupby_view->setFooter($list);

      $list_group->addItem($groupby_view);
    }



    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list_group);
    $result->setNoDataString(pht('No repositories found for this query.'));

    return $result;
  }

  private function renderRepositories($repositories, $viewer, $handles, $list){
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIRepositoryObjectItemView())
        ->setUser($viewer)
        ->setObject($repository)
        ->setHeader($repository->getName())
        ->setObjectName($repository->getMonogram())
        ->setHref($repository->getURI());

      $item->setDateCreated($repository->getDateCreated());
      $item->setOwner($repository->getEditPolicy());

      $item->addIcon(
        'none',
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()));

      $tokens_given = id(new PhabricatorTokenGivenQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($repository->getPHID()))
        ->execute();
      if ($tokens_given) {
        $tokensScoreAverage = 0;
        $scores = array('like-1' => 5, 'like-2' => 1, 'heart-1' => 5, 'heart-2' => 1, 'medal-1' => 2,
          'medal-2' => 3, 'medal-3' => 4, 'medal-4' => 0);

        foreach ($tokens_given as $token_given) {
          $token = $token_given->getToken();

          $tokensScoreAverage = $tokensScoreAverage + $scores[substr($token->getPHID(), 10)];
        }
        $tokensScoreAverage = $tokensScoreAverage*1.0/count($tokens_given);

        $score =  pht('Tokens: %s', sprintf("%.2f/5", $tokensScoreAverage));
        $item->setScore($score);
      }

      $size = $repository->getCommitCount();
      if ($size) {
        /*$history_uri = $repository->generateURI(
          array(
            'action' => 'history',
          ));

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $history_uri,
            ),
            pht('%s Commit(s)', new PhutilNumber($size))));*/
        $item->setContributer($this->getCommitAuthors($repository));
      } else {
        //$item->addAttribute(pht('No Commits'));
      }

      $project_handles = array_select_keys(
        $handles,
        $repository->getProjectPHIDs());
      if ($project_handles) {
        $type_handles = array();
        $org_handles = array();

        foreach ($project_handles as $key => $value){
          if($value->getIcon() == "fa-building"){
            $org_handles[$key] = $value;
          }
          else{
            $type_handles[$key] = $value;
          }
        }

        if(count($org_handles) > 0){
          $item->addOrganizations(
            id(new PHUIHandleTagListView())
              ->setSlim(true)
              ->setHandles($org_handles));
        }


        if(count($type_handles) > 0){
          $item->addAttribute(
            id(new PHUIHandleTagListView())
              ->setSlim(true)
              ->setHandles($type_handles));
        }

      }

      $symbol_languages = $repository->getSymbolLanguages();
      $item->setSymbolLanguages(join($symbol_languages, ","));

      $commit = $repository->getMostRecentCommit();

      if ($commit){
        $item->setEpoch($commit->getEpoch());
      }

      if (!$repository->isTracked()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Inactive'));
      } else if ($repository->isImporting()) {
        $item->addIcon('fa-clock-o indigo', pht('Importing...'));
      }

      $property_table = $this->buildPropertiesTable($repository);
      $item->setCloneURL($property_table);
      $description_view = $this->buildDescriptionView($repository);
      $item->setDescriptionView($description_view);

      $view = id(new PHUITwoColumnView())
        ->setHeader($item)
        ->setMainColumn(array(
        ));
      $list->addItem($view);
    }
  }

  protected function willUseSavedQuery(PhabricatorSavedQuery $saved) {
    $project_phids = $saved->getParameter('projectPHIDs', array());

    $old = $saved->getParameter('projects', array());
    foreach ($old as $phid) {
      $project_phids[] = $phid;
    }

    $any = $saved->getParameter('anyProjectPHIDs', array());
    foreach ($any as $project) {
      $project_phids[] = 'any('.$project.')';
    }

    $saved->setParameter('projectPHIDs', $project_phids);
  }

  protected function getNewUserBody() {

    $new_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('New Repository'))
      ->setHref('/diffusion/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Import, create, or just browse repositories in Diffusion.'))
      ->addAction($new_button);

      return $view;
  }

  private function getCommitAuthors($repository){
    $viewer = $this->requireViewer();
    $params = array(
      'repository' => $repository->getID(),
      'user' => $viewer,
      'blob' => null,
      'commit' => null,
      'path' => null,
      'line' => null,
      'branch' => null,
      'lint' => null,
    );

    $drequest = DiffusionRequest::newFromDictionary($params);

    $user = $viewer;
    $method = 'diffusion.historyquery';
    $commit = $repository->getMostRecentCommit()->getCommitIdentifier();

    $history = DiffusionQuery::callConduitWithDiffusionRequest(
      $user,
      $drequest,
      $method,
      array(
        // On the home page, we want to find tags on any branch.
        'commit' => $commit,
        'limit' => 15 + 1,
      ), true)->resolve();

    $authors = array();

    foreach ($history["pathChanges"] as $history_item){
      $authors[] = $history_item["commitData"]["authorName"];
    }
    return join(array_map('self::renderName', array_unique($authors)), ", ");
  }

  private function buildTagsView($repository){
    if ($this->needTagFuture($repository)) {

      $viewer = $this->requireViewer();
      $params = array(
        'repository' => $repository->getID(),
        'user' => $viewer,
        'blob' => null,
        'commit' => null,
        'path' => null,
        'line' => null,
        'branch' => null,
        'lint' => null,
      );

      $drequest = DiffusionRequest::newFromDictionary($params);

      $user = $viewer;
      $method = 'diffusion.tagsquery';

      try{
        $tags = DiffusionQuery::callConduitWithDiffusionRequest(
          $user,
          $drequest,
          $method,
          array(
            // On the home page, we want to find tags on any branch.
            'commit' => null,
            'limit' => 15 + 1,
          ), true)->resolve();


        $tags = DiffusionRepositoryTag::newFromConduit($tags);
      }
      catch(Exception $exception){
        $tags = null;
      }

      if (!$tags) {
        return null;
      }

      $tag_limit = 15;
      $more_tags = (count($tags) > $tag_limit);
      $tags = array_slice($tags, 0, $tag_limit);

      $commits = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->withIdentifiers(mpull($tags, 'getCommitIdentifier'))
        ->withRepository($repository)
        ->needCommitData(true)
        ->execute();

      $view = id(new DiffusionTagListView())
        ->setUser($viewer)
        ->setDiffusionRequest($drequest)
        ->setTags($tags)
        ->setCommits($commits);

      $panel = new PHUIObjectBoxView();
      $header = new PHUIHeaderView();
      $header->setHeader(pht('Releases'));

      if ($more_tags) {
        $header->setSubheader(
          pht('Showing the %d most recent tags.', $tag_limit));
      }

      $button = new PHUIButtonView();
      $button->setText(pht('Show All Releases'));
      $button->setTag('a');
      $button->setIcon('fa-tag');
      $button->setHref($drequest->generateURI(
        array(
          'action' => 'tags',
        )));

      $header->addActionLink($button);

      $panel->setHeader($header);
      $panel->setTable($view);
      $panel->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

      return $panel;
    }

    return null;
  }
  private function needTagFuture($repository) {

    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        // No tags in SVN.
        return false;
    }

    return true;
  }

  private function buildDescriptionView(PhabricatorRepository $repository) {
    $viewer = $this->requireViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $description = $repository->getDetail('description');
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $view->addTextContent($description);
      return $view;
    }
    return null;
  }

  private function buildPropertiesTable(PhabricatorRepository $repository) {

    $uris_view = array();

    $display_never = PhabricatorRepositoryURI::DISPLAY_NEVER;

    $uris = $repository->getURIs();
    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }

      if ($uri->getEffectiveDisplayType() == $display_never) {
        continue;
      }

      if ($repository->isSVN()) {
        $label = pht('Checkout');
      } else {
        $label = pht('Clone');
      }

      $uris_view[] =
        $this->renderCloneURI($repository, $uri);
    }

    $view = $frame = phutil_tag(
      'div',
      array(
        'class' => 'phui-oi-frame',
      ),
      $uris_view);

    return $view;
    /*
    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);

    $info = null;
    $params = array(
      'repository' => $repository->getID(),
      'user' => $viewer,
      'blob' => null,
      'commit' => null,
      'path' => null,
      'line' => null,
      'branch' => null,
      'lint' => null,
    );

    $drequest = DiffusionRequest::newFromDictionary($params);

    // Try to load alternatives. This may fail for repositories which have not
    // cloned yet. If it does, just ignore it and continue.
    try {
      $alternatives = $drequest->getRefAlternatives();
    } catch (ConduitClientException $ex) {
      $alternatives = array();
    }

    if ($alternatives) {
      $message = array(
        pht(
          'The ref "%s" is ambiguous in this repository.',
          $drequest->getBranch()),
        ' ',
        phutil_tag(
          'a',
          array(
            'href' => $drequest->generateURI(
              array(
                'action' => 'refs',
              )),
          ),
          pht('View Alternatives')),
      );

      $messages = array($message);

      $info = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(array($message));

      $box->setInfoView($info);
    }


    return $box;*/
  }

  private function renderCloneURI(
    PhabricatorRepository $repository,
    PhabricatorRepositoryURI $uri) {

    if ($repository->isSVN()) {
      $display = csprintf(
        'svn checkout %R %R',
        (string)$uri->getDisplayURI(),
        $repository->getCloneName());
    } else {
      $display = (string)$uri->getDisplayURI();
    }

    $display = (string)$display;
    $viewer = $this->requireViewer();

    return id(new DiffusionCloneURIView())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->setRepositoryURI($uri)
      ->setDisplayURI($display);
  }

  final public static function renderName($name) {
    $email = new PhutilEmailAddress($name);
    if ($email->getDisplayName() && $email->getDomainName()) {
      Javelin::initBehavior('phabricator-tooltips', array());
      require_celerity_resource('aphront-tooltip-css');
      return javelin_tag(
        'span',
        array(
          'sigil' => 'has-tooltip',
          'meta'  => array(
            'tip'   => $email->getAddress(),
            'align' => 'E',
            'size'  => 'auto',
          ),
        ),
        $email->getDisplayName());
    }
    return hsprintf('%s', $name);
  }
}
