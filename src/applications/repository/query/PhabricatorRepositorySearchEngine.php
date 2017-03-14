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

    $list = new PHUIObjectItemListView();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $item = id(new PHUIObjectItemView())
        ->setUser($viewer)
        ->setObject($repository)
        ->setHeader($repository->getName())
        ->setObjectName($repository->getMonogram())
        ->setHref($repository->getURI());*/

      $content = $this->buildTagsView($repository);

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
        $item->addIcon($score, $score);
      }

      $size = $repository->getCommitCount();
      if ($size) {
        $history_uri = $repository->generateURI(
          array(
            'action' => 'history',
          ));

        $item->addAttribute(
          phutil_tag(
            'a',
            array(
              'href' => $history_uri,
            ),
            pht('%s Commit(s)', new PhutilNumber($size))));
      } else {
        $item->addAttribute(pht('No Commits'));
      }

      $project_handles = array_select_keys(
        $handles,
        $repository->getProjectPHIDs());
      if ($project_handles) {
        $item->addAttribute(
          id(new PHUIHandleTagListView())
            ->setSlim(true)
            ->setHandles($project_handles));
      }

      $symbol_languages = $repository->getSymbolLanguages();

      foreach ($symbol_languages as $symbol_language){
        $item->addAttribute(
          phutil_tag(
            'span',
            array(
              'class' => "phabricator-handle-tag-list-item",
            ),
            pht("$symbol_language")));
      }

      if (!$repository->isTracked()) {
        $item->setDisabled(true);
        $item->addIcon('disable-grey', pht('Inactive'));
      } else if ($repository->isImporting()) {
        $item->addIcon('fa-clock-o indigo', pht('Importing...'));
      }

      $view = id(new PHUITwoColumnView())
        ->setMainColumn(array(
          $item
        ))
        ->setFooter($content);
      $list->addItem($view);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No repositories found for this query.'));

    return $result;
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

  private function loadGitTagList($repository) {

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withRefTypes(
        array(
          PhabricatorRepositoryRefCursor::TYPE_TAG,
        ))
      ->execute();

    $tags = array();
    foreach ($refs as $ref) {
      $fields = $ref->getRawFields();
      $tag = id(new DiffusionRepositoryTag())
        ->setAuthor($fields['author'])
        ->setEpoch($fields['epoch'])
        ->setCommitIdentifier($ref->getCommitIdentifier())
        ->setName($ref->getShortName())
        ->setDescription($fields['subject'])
        ->setType('git/'.$fields['objecttype']);

      $tags[] = $tag;
    }

    return $tags;
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
      if (!$tags) {
        return null;
      }

      $tag_limit = $this->getTagLimit();
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

      return $view;
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
}
