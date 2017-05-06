<?php

final class PhabricatorOrganizationSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Organizations');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function newQuery() {
    return id(new PhabricatorProjectQuery())
      ->needImages(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name'))
        ->setKey('name'),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Members'))
        ->setKey('memberPHIDs')
        ->setConduitKey('members')
        ->setAliases(array('member', 'members')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Watchers'))
        ->setKey('watcherPHIDs')
        ->setConduitKey('watchers')
        ->setAliases(array('watcher', 'watchers')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions($this->getStatusOptions()),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Milestones'))
        ->setKey('isMilestone')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Milestones'),
          pht('Hide Milestones'))
        ->setDescription(
          pht(
            'Pass true to find only milestones, or false to omit '.
            'milestones.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Icons'))
        ->setKey('icons')
        ->setOptions($this->getIconOptions()),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Colors'))
        ->setKey('colors')
        ->setOptions($this->getColorOptions()),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Parent Projects'))
        ->setKey('parentPHIDs')
        ->setConduitKey('parents')
        ->setAliases(array('parent', 'parents', 'parentPHID'))
        ->setDescription(pht('Find direct subprojects of specified parents.')),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Ancestor Projects'))
        ->setKey('ancestorPHIDs')
        ->setConduitKey('ancestors')
        ->setAliases(array('ancestor', 'ancestors', 'ancestorPHID'))
        ->setDescription(
          pht('Find all subprojects beneath specified ancestors.')),
    );
  }


  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if (strlen($map['name'])) {
      $tokens = PhabricatorTypeaheadDatasource::tokenizeString($map['name']);
      $query->withNameTokens($tokens);
    }

    if ($map['memberPHIDs']) {
      $query->withMemberPHIDs($map['memberPHIDs']);
    }

    if ($map['watcherPHIDs']) {
      $query->withWatcherPHIDs($map['watcherPHIDs']);
    }

    if ($map['status']) {
      $status = idx($this->getStatusValues(), $map['status']);
      if ($status) {
        $query->withStatus($status);
      }
    }

    if ($map['icons']) {
      $query->withIcons($map['icons']);
    }

    if ($map['colors']) {
      $query->withColors($map['colors']);
    }

    if ($map['isMilestone'] !== null) {
      $query->withIsMilestone($map['isMilestone']);
    }

    if ($map['parentPHIDs']) {
      $query->withParentProjectPHIDs($map['parentPHIDs']);
    }

    if ($map['ancestorPHIDs']) {
      $query->withAncestorProjectPHIDs($map['ancestorPHIDs']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/project/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['joined'] = pht('Joined');
    }

    $names['active'] = pht('Active');
    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    // By default, do not show milestones in the list view.
    $query->setParameter('isMilestone', false);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query
          ->setParameter('status', 'active');
      case 'joined':
        return $query
          ->setParameter('memberPHIDs', array($viewer_phid))
          ->setParameter('status', 'active');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      'active'   => pht('Show Only Active Projects'),
      'archived' => pht('Show Only Archived Projects'),
      'all'      => pht('Show All Projects'),
    );
  }

  private function getStatusValues() {
    return array(
      'active'   => PhabricatorProjectQuery::STATUS_ACTIVE,
      'archived' => PhabricatorProjectQuery::STATUS_ARCHIVED,
      'all'      => PhabricatorProjectQuery::STATUS_ANY,
    );
  }

  private function getIconOptions() {
    $options = array();

    $set = new PhabricatorProjectIconSet();
    foreach ($set->getIcons() as $icon) {
      if ($icon->getIsDisabled()) {
        continue;
      }

      $options[$icon->getKey()] = array(
        id(new PHUIIconView())
          ->setIcon($icon->getIcon()),
        ' ',
        $icon->getLabel(),
      );
    }

    return $options;
  }

  private function getColorOptions() {
    $options = array();

    foreach (PhabricatorProjectIconSet::getColorMap() as $color => $name) {
      $options[$color] = array(
        id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setShade($color)
          ->setName($name),
      );
    }

    return $options;
  }

  protected function renderResultList(
    array $projects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->requireViewer();

    $list = id(new PhabricatorProjectListView())
      ->setUser($viewer)
      ->setProjects($projects)
      ->renderList();

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No projects found.'));
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Project'))
      ->setHref('/project/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Projects are flexible storage containers used as '.
            'tags, teams, projects, or anything you need to group.'))
      ->addAction($create_button);

      return $view;
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

    $constraints["icons"] = array("organization");
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

      $phids = array();
      foreach ($objects as $object) {
        $phids[] = $object->getPHID();
      }

      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->witHPHIDs($phids)
        ->execute();

      $handles_map = array();
      foreach($handles as $handle){
        $handles_map[$handle->getPHID()] = $handle;
      }

      foreach ($objects as $object) {
        $field_map = $this->getObjectWireFieldsForConduit(
          $object,
          $field_extensions,
          $extension_data);

        $attachment_map = array();
        foreach ($attachments as $key => $attachment) {
          $attachment_map[$key] = $attachment->getAttachmentForObject(
            $object,
            $attachment_data[$key],
            $attachment_specs[$key]);
        }

        // If this is empty, we still want to emit a JSON object, not a
        // JSON list.
        if (!$attachment_map) {
          $attachment_map = (object)$attachment_map;
        }

        $handle = $handles_map[$object->getPHID()];

        $id = (int)$object->getID();
        $phid = $object->getPHID();


        $data[] = array(
          'id' => $id,
          'type' => phid_get_type($phid),
          'phid' => $phid,
          'name' => $object->getName(),
          'imageUrl' => $handle->getImageURI()
        );
      }
    }

    return array(
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
