<?php

final class PhabricatorRepositoryAjaxSearchEngine
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
     $query) {
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

    $repositories_groupby = array();
    $groupby = array();
    foreach ($repositories as $repository) {
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
            $groupby[$value->getName()] = $value;
          }
        }



        if(count($type_handles) > 0){
          //代码库有分类
          foreach ($type_handles as $key => $handle){
            $repos = $repositories_groupby[$handle->getName()];
            if(!isset($repos) || $repos == null){
              $repos = array();
              $repos[] = $repository;
              $repositories_groupby[$handle->getName()] = $repos;
            }
            else{
              $repos[] = $repository;
              $repositories_groupby[$handle->getName()] = $repos;
            }
          }

        }
        else{
          //代码库属于默认分类
          $repos = $repositories_groupby[""];
          if(!isset($repos) || $repos == null){
            $repos = array();
            $repos[] = $repository;
            $repositories_groupby[""] = $repos;
          }
          else{
            $repos[] = $repository;
            $repositories_groupby[""] = $repos;
          }
        }
      }
      else{
        $repos = $repositories_groupby[""];
        if(!isset($repos) || $repos == null){
          $repos = array();
          $repos[] = $repository;
          $repositories_groupby[""] = $repos;
        }
        else{
          $repos[] = $repository;
          $repositories_groupby[""] = $repos;
        }
      }
    }

    $list_group = new PHUIObjectItemListView();

    foreach ($repositories_groupby as $key => $repositores_group){


      $list = new PHUIObjectItemListView();
      $this->renderRepositories($repositores_group, $viewer, $handles, $list);

      $groupby_header = new PHUIObjectBoxView();
      if($key == ""){
        $groupby_header->setHeaderText(pht("Default")."(".pht("Total %s", count($repositores_group)).")");
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
      $content = $this->buildTagsView($repository);

      /*
      $commit = $repository->getMostRecentCommit();
      if ($commit) {
        $commit_link = phutil_tag(
          'a',
          array(
            'href' => $commit->getURI(),
          ),
          pht(
            '%s: %s',
            $commit->getLocalName(),
            $commit->getSummary()));

        $item->setSubhead($commit_link);
        $item->setEpoch($commit->getEpoch());
      }
      */

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
        ))
        ->setFooter(array($content));
      $list->addItem($view);
    }
  }

  private function renderRepositoriesJSON($repositories, $viewer, $handles){
    $items = array();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = array("name" => $repository->getName(), "status" => $repository->getStatus(), "id" => $id,
        "phid" => $repository->getPHID(), "dateCreated" => $dateCreated = date('Y-m-d', $repository->getDateCreated()));

      $owner = $repository->getEditPolicy();
      if($owner && $owner != 'admin'){
        $user = id(new PhabricatorUser())->loadOneWhere(
          'phid = %s',
          $owner);

        if($user){
          $owner = $user->getUserName();
        }
        else{
          $policies = id(new PhabricatorPolicyQuery())
            ->setViewer($viewer)
            ->withPHIDs(array($owner))
            ->execute();

          if($policies){
            $policy = head($policies);
            $rule_data = $policy->getRules();
            $users = array();
            $admin_user = null;
            foreach ($rule_data as $rule){
              if($rule["action"] == "allow" && $rule["rule"] == "PhabricatorUsersPolicyRule"){
                foreach ($rule["value"] as $userPHID){
                  $actor = id(new PhabricatorUser())->loadOneWhere(
                    'phid = %s',
                    $userPHID);

                  if($actor->getUserName() == "admin"){
                    $admin_user = $actor->getUserName()."(".$actor->getRealName().")";
                  }
                  $users[] = $actor->getUserName()."(".$actor->getRealName().")";
                }
              }
            }//foreach ($rule_data as $rule)
            if(count($users) > 0){
              if(count($users) > 1){
                $users = array_diff($users, [$admin_user]);
              }
              $owner = join($users, ", ");
            }
            else{
              $owner = "多人";
            }
          }

        }
      }

      $item["owner"] = $owner;
      $item["vcs"] = $repository->getVersionControlSystem();
      $content = $this->buildTagsView($repository);

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
        $item["contributors"] = $this->getCommitAuthorsJSON($repository);
      } else {
        //$item->addAttribute(pht('No Commits'));
      }

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

        $item["score"] = $tokensScoreAverage;
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
        //$item->setContributer($this->getCommitAuthors($repository));
        //TODO $item["contributors"] = ...
      } else {
        //$item->addAttribute(pht('No Commits'));
      }

      $item["organizations"] = array();
      $item["categories"] = array();

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
          //TODO: $item["organizations"][] =
          foreach ($org_handles as $phid => $object){
            $item["organizations"][] = array("name" => $object->getName(),
              "image" => $object->getImageURI());
          }
        }


        if(count($type_handles) > 0){
          //TODO: $item["organizations"][] = ;
          foreach ($type_handles as $phid => $object){
            $item["categories"][] = array("name" => $object->getName(),
              "image" => $object->getImageURI());
          }
        }

      }

      $symbol_languages = $repository->getSymbolLanguages();
      $item["languages"] = join($symbol_languages, ",");
      $item["uris"] = $this->renderCloneURIsJSON($repository);

      $versions = $this->buildTagsJSON($repository);
      $item["versions"] = $versions;

/*
      $property_table = $this->buildPropertiesTable($repository);
      $item->setCloneURL($property_table);
      $description_view = $this->buildDescriptionView($repository);
      $item->setDescriptionView($description_view);

      $view = id(new PHUITwoColumnView())
        ->setHeader($item)
        ->setMainColumn(array(
        ))
        ->setFooter(array($content));
      $list->addItem($view);*/
      $items[] = $item;
    }
    return $items;
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

  private function getCommitAuthorsJSON($repository){
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
    $authorPHIDs = array();
    foreach ($history["pathChanges"] as $history_item){
      $commitDetails = json_decode($history_item["commitData"]["commitDetails"], true);

      if($commitDetails["authorPHID"]){
        $authorPHIDs[] = $commitDetails["authorPHID"];
      }
    }

    $authorHandles = array();
    if(count($authorPHIDs) > 0){
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($authorPHIDs)
        ->execute();

      foreach ($handles as $handle){
        $authorHandles[$handle->getPHID()] = $handle;
      }
    }

    foreach ($history["pathChanges"] as $history_item){
      $commitDetails = json_decode($history_item["commitData"]["commitDetails"], true);
      $author = array("authorName" => $history_item["commitData"]["authorName"]);

      if($commitDetails["authorEmail"])$author["authorEmail"] = $commitDetails["authorEmail"];

      if($commitDetails["authorPHID"]){
        $author["authorPHID"] = $commitDetails["authorPHID"];
        $author["authorName"] = $commitDetails["authorName"];

        $authorObject = $authorHandles[$author["authorPHID"]];
        if(isset($authorObject) && $authorObject != null){
          $author["fullName"] = $authorObject->getFullName();
          $author["imageURI"] = $authorObject->getImageURI();
          $author["uri"] = $authorObject->getUri();
        }
      }

      $authors[] = $author;
    }
    return array_unique($authors);
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

  private function buildTagsJSON($repository){

    $tags = array();
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

      $tags = $view->renderJSON();
    }

    return $tags;
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

  private function renderCloneURIsJSON(PhabricatorRepository $repository){

    $cloneURIs = array();

    $display_never = PhabricatorRepositoryURI::DISPLAY_NEVER;

    $uris = $repository->getURIs();
    foreach ($uris as $uri) {
      if ($uri->getIsDisabled()) {
        continue;
      }

      if ($uri->getEffectiveDisplayType() == $display_never) {
        continue;
      }

      $cloneURIs[] = (string)$uri->getDisplayURI();

    }

    return $cloneURIs;
  }

  private function buildPropertiesTable(PhabricatorRepository $repository) {
    $viewer = $this->requireViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

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

      $view->addProperty(
        $label,
        $this->renderCloneURI($repository, $uri));
    }

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


  private function getEngineExtensions() {
    $extensions = PhabricatorSearchEngineExtension::getAllEnabledExtensions();

    foreach ($extensions as $key => $extension) {
      $extension
        ->setViewer($this->requireViewer())
        ->setSearchEngine($this);
    }

    $object = $this->newResultObject();
    foreach ($extensions as $key => $extension) {
      if (!$extension->supportsObject($object)) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

  private function getConduitFieldExtensions() {
    $extensions = $this->getEngineExtensions();
    $object = $this->newResultObject();

    foreach ($extensions as $key => $extension) {
      if (!$extension->getFieldSpecificationsForConduit($object)) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

  private function setQueryOrderForConduit($query, ConduitAPIRequest $request) {
    $order = $request->getValue('order');
    if ($order === null) {
      return;
    }

    if (is_scalar($order)) {
      $query->setOrder($order);
    } else {
      $query->setOrderVector($order);
    }
  }

  private function setPagerLimitForConduit($pager, ConduitAPIRequest $request) {
    $limit = $request->getValue('limit');

    // If there's no limit specified and the query uses a weird huge page
    // size, just leave it at the default gigantic page size. Otherwise,
    // make sure it's between 1 and 100, inclusive.

    if ($limit === null) {
      if ($pager->getPageSize() >= 0xFFFF) {
        return;
      } else {
        $limit = 100;
      }
    }

    if ($limit > 655360) {
      throw new Exception(
        pht(
          'Maximum page size for Conduit API method calls is 100, but '.
          'this call specified %s.',
          $limit));
    }

    if ($limit < 1) {
      throw new Exception(
        pht(
          'Minimum page size for API searches is 1, but this call '.
          'specified %s.',
          $limit));
    }

    $pager->setPageSize($limit);
  }

  private function setPagerOffsetsForConduit(
    $pager,
    ConduitAPIRequest $request) {
    $before_id = $request->getValue('before');
    if ($before_id !== null) {
      $pager->setBeforeID($before_id);
    }

    $after_id = $request->getValue('after');
    if ($after_id !== null) {
      $pager->setAfterID($after_id);
    }
  }

  public function buildConduitResponse(
    ConduitAPIRequest $request,
    ConduitAPIMethod $method) {
    $viewer = $this->requireViewer();

    $groupby_key = "";

    $query_key = $request->getValue('queryKey');
    if (!strlen($query_key)) {
      $saved_query = new PhabricatorSavedQuery();
    } else if ($this->isBuiltinQuery($query_key)) {
      $saved_query = $this->buildSavedQueryFromBuiltin($query_key);
    } else {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();
      if (!$saved_query) {
        throw new Exception(
          pht(
            'Query key "%s" does not correspond to a valid query.',
            $query_key));
      }
    }

    $constraints = $request->getValue('constraints', array());

    $fields = $this->getSearchFieldsForConduit();

    foreach ($fields as $key => $field) {
      if (!$field->getConduitParameterType()) {
        unset($fields[$key]);
      }
    }

    $valid_constraints = array();
    foreach ($fields as $field) {
      foreach ($field->getValidConstraintKeys() as $key) {
        $valid_constraints[$key] = true;
      }
    }

    foreach ($constraints as $key => $constraint) {

      if($key == "groupby"){
        if($constraint == "organization"){
          $groupby_key = $constraint;
        }
        else if($constraint == "category"){
          $groupby_key = $constraint;
        }
        else{
          $groupby_key = "";
        }
        continue;
      }

      if (empty($valid_constraints[$key])) {
        throw new Exception(
          pht(
            'Constraint "%s" is not a valid constraint for this query.',
            $key));
      }
    }

    foreach ($fields as $field) {
      if (!$field->getValueExistsInConduitRequest($constraints)) {
        continue;
      }

      $value = $field->readValueFromConduitRequest(
        $constraints,
        $request->getIsStrictlyTyped());
      $saved_query->setParameter($field->getKey(), $value);
    }

    // NOTE: Currently, when running an ad-hoc query we never persist it into
    // a saved query. We might want to add an option to do this in the future
    // (for example, to enable a CLI-to-Web workflow where user can view more
    // details about results by following a link), but have no use cases for
    // it today. If we do identify a use case, we could save the query here.

    $query = $this->buildQueryFromSavedQuery($saved_query);
    $pager = $this->newPagerForSavedQuery($saved_query);

    $attachments = $this->getConduitSearchAttachments();

    // TODO: Validate this better.
    $attachment_specs = $request->getValue('attachments', array());
    $attachments = array_select_keys(
      $attachments,
      array_keys($attachment_specs));

    foreach ($attachments as $key => $attachment) {
      $attachment->setViewer($viewer);
    }

    foreach ($attachments as $key => $attachment) {
      $attachment->willLoadAttachmentData($query, $attachment_specs[$key]);
    }

    $this->setQueryOrderForConduit($query, $request);
    $this->setPagerLimitForConduit($pager, $request);
    $this->setPagerOffsetsForConduit($pager, $request);

    $objects = $this->executeQuery($query, $pager);

    $data = array();
    if ($objects) {
      $field_extensions = $this->getConduitFieldExtensions();

      $extension_data = array();
      foreach ($field_extensions as $key => $extension) {
        $extension_data[$key] = $extension->loadExtensionConduitData($objects);
      }

      $attachment_data = array();
      foreach ($attachments as $key => $attachment) {
        $attachment_data[$key] = $attachment->loadAttachmentData(
          $objects,
          $attachment_specs[$key]);
      }


      $phids = $this->getRequiredHandlePHIDsForResultList($objects, $query);

      if ($phids) {
        $handles = id(new PhabricatorHandleQuery())
          ->setViewer($this->requireViewer())
          ->witHPHIDs($phids)
          ->execute();
      } else {
        $handles = array();
      }

      $repositories = $objects;

      $uris = id(new PhabricatorRepositoryURIQuery())
        ->setViewer($viewer)
        ->withRepositories($repositories)
        ->execute();
      $uri_groups = mgroup($uris, 'getRepositoryPHID');

      $repositories_groupby = array();
      $groupby = array();

      foreach ($repositories as $repository) {
        $repository_uris = idx($uri_groups, $repository->getPHID(), array());
        $repository->attachURIs($repository_uris);

        $project_handles = array_select_keys(
          $handles,
          $repository->getProjectPHIDs());
        if ($project_handles) {
          $type_handles = array();
          $org_handles = array();

          foreach ($project_handles as $key => $value){
            if($groupby_key == "organization"){
              if($value->getIcon() != "fa-building"){
                $org_handles[$key] = $value;
              }
              else{
                $type_handles[$key] = $value;
                $groupby[$value->getName()] = $value;
              }
            }
            else{
              if($value->getIcon() == "fa-building"){
                $org_handles[$key] = $value;
              }
              else{
                $type_handles[$key] = $value;
                $groupby[$value->getName()] = $value;
              }
            }

          }



          if(count($type_handles) > 0){
            //代码库有分类
            foreach ($type_handles as $key => $handle){
              $repos = $repositories_groupby[$handle->getName()];
              if(!isset($repos) || $repos == null){
                $repos = array();
                $repos[] = $repository;
                $repositories_groupby[$handle->getName()] = $repos;
              }
              else{
                $repos[] = $repository;
                $repositories_groupby[$handle->getName()] = $repos;
              }
            }

          }
          else{
            //代码库属于默认分类
            $repos = $repositories_groupby[""];
            if(!isset($repos) || $repos == null){
              $repos = array();
              $repos[] = $repository;
              $repositories_groupby[""] = $repos;
            }
            else{
              $repos[] = $repository;
              $repositories_groupby[""] = $repos;
            }
          }
        }
        else{
          $repos = $repositories_groupby[""];
          if(!isset($repos) || $repos == null){
            $repos = array();
            $repos[] = $repository;
            $repositories_groupby[""] = $repos;
          }
          else{
            $repos[] = $repository;
            $repositories_groupby[""] = $repos;
          }
        }
      }

      if($groupby_key == ""){

          $items = $this->renderRepositoriesJSON($repositories, $viewer, $handles);
          $data = array("subtotal" => count($items), "items" => $items);

      }
      else{
        foreach ($repositories_groupby as $key => $repositores_group){

          $items = $this->renderRepositoriesJSON($repositores_group, $viewer, $handles);
          $data[$key] = array("subtotal" => count($repositores_group), "items" => $items);

        }
      }


    }

    return array(
      "total" => count($repositories),
      'data' => $data,
      'maps' => $method->getQueryMaps($query),
      'query' => array(
        // This may be `null` if we have not saved the query.
        'queryKey' => $saved_query->getQueryKey(),
      ),
      'cursor' => array(
        'limit' => $pager->getPageSize(),
        'after' => $pager->getNextPageID(),
        'before' => $pager->getPrevPageID(),
        'order' => $request->getValue('order'),
      ),
    );
  }
}
