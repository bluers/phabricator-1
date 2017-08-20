<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $basePath;
  private $paths;
  private $handles = array();

  public function setBasePath($p){
    $this->basePath = $p;
    return $this;
  }

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    if(isset($this->basePath) && !($this->basePath==null)){
      $base_path = $this->basePath;
    }
    else{
      $base_path = trim($request->getPath(), '/');
      if ($base_path) {
        $base_path = $base_path.'/';
      }
    }


    $need_pull = array();
    $rows = array();
    $show_edit = false;

    $contains_trunk = false;
    $contains_tags = false;
    $contains_branches = false;
    $contains_docs = false;

    foreach ($this->paths as $path){
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $path_name = $path->getPath();
        if($path_name == "trunk"){
          $contains_trunk = true;
        }
        if($path_name == "docs"){
          $contains_docs = true;
        }
        if($path_name == "tags"){
          $contains_tags = true;
        }
        if($path_name == "branches" || $path_name == "branchs"){
          $contains_branches = true;
        }
      }
    }

    $show_svn_hint = $contains_branches || $contains_tags || $contains_docs || $contains_trunk;

    foreach ($this->paths as $path) {
      $full_path = $base_path.$path->getPath();

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          $full_path.$dir_slash,
          array(
            'type' => $file_type,
            'name' => $browse_text,
          )));

        $history_path = $full_path.'/';
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          null,
          array(
            'type' => $file_type,
            'name' => $browse_text,
            'hash' => $path->getHash(),
            'external' => $path->getExternalURI(),
          )));

        $history_path = $full_path.'/';
      } else {
        $browse_text = $path->getPath();
        $browse_link = $this->linkBrowse(
          $full_path,
          array(
            'type' => $file_type,
            'name' => $browse_text,
          ));

        $history_path = $full_path;
      }

      $history_link = $this->linkHistory($history_path);

      $dict = array(
        'lint'      => celerity_generate_unique_node_id(),
        'commit'    => celerity_generate_unique_node_id(),
        'date'      => celerity_generate_unique_node_id(),
        'author'    => celerity_generate_unique_node_id(),
        'details'   => celerity_generate_unique_node_id(),
      );

      $need_pull[$full_path.$dir_slash] = $dict;
      foreach ($dict as $k => $uniq) {
        $dict[$k] = phutil_tag('span', array('id' => $uniq), '');
      }

      if($show_svn_hint){
        $svn_hint = "";
        if($path->getPath() == "branches" || $path->getPath() == "branchs"){
          $svn_hint = pht("该目录的子目录对应了SVN分支");
        }
        if($path->getPath() == "docs"){
          $svn_hint = pht("该目录保存了项目文档");
        }
        if($path->getPath() == "tags"){
          $svn_hint = pht("该目录的子目录为发布的版本");
        }
        if($path->getPath() == "trunk"){
          $svn_hint = pht("该目录对应SVN主分支");
        }
        $rows[] = array(
          $history_link,
          $browse_link,
          idx($dict, 'lint'),
          $dict['commit'],
          $dict['details'],
          $svn_hint,
          $dict['date'],
        );
      }
      else{
        $rows[] = array(
          $history_link,
          $browse_link,
          idx($dict, 'lint'),
          $dict['commit'],
          $dict['details'],
          $dict['date'],
        );
      }

    }

    if ($need_pull) {
      Javelin::initBehavior(
        'diffusion-pull-lastmodified',
        array(
          'uri'   => (string)$request->generateURI(
            array(
              'action' => 'lastmodified',
              'stable' => true,
            )),
          'map' => $need_pull,
        ));
    }

    $branch = $this->getDiffusionRequest()->loadBranch();
    $show_lint = ($branch && $branch->getLintCommit());
    $lint = $request->getLint();

    $view = new AphrontTableView($rows);
    if($show_svn_hint){
      $view->setHeaders(
        array(
          null,
          pht('Path'),
          ($lint ? $lint : pht('Lint')),
          pht('Modified'),
          pht('Commit Details'),
          pht('Details'),
          pht('Committed'),
        ));
      $view->setColumnClasses(
        array(
          'nudgeright',
          '',
          '',
          '',
          '',
          'wide',
          'right',
        ));
      $view->setColumnVisibility(
        array(
          true,
          true,
          $show_lint,
          true,
          true,
          true,
          true,
        ));
    }
    else{
      $view->setHeaders(
        array(
          null,
          pht('Path'),
          ($lint ? $lint : pht('Lint')),
          pht('Modified'),
          pht('Commit Details'),
          pht('Committed'),
        ));
      $view->setColumnClasses(
        array(
          'nudgeright',
          '',
          '',
          '',
          'wide',
          'right',
        ));
      $view->setColumnVisibility(
        array(
          true,
          true,
          $show_lint,
          true,
          true,
          true,
        ));
    }


    $view->setDeviceVisibility(
      array(
        true,
        true,
        false,
        false,
        true,
        false,
      ));


    return $view->render();
  }

  public function renderJSON() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    if(isset($this->basePath) && !($this->basePath==null)){
      $base_path = $this->basePath;
    }
    else{
      $base_path = trim($request->getPath(), '/');
      if ($base_path) {
        $base_path = $base_path.'/';
      }
    }


    $need_pull = array();
    $rows = array();
    $show_edit = false;
    foreach ($this->paths as $path) {
      $full_path = $base_path.$path->getPath();

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        continue;
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
         continue;
      } else {
        $browse_text = $path->getPath();
      }

      $href = $request->generateURI(array(
        'action' => 'browse',
        'path' => $full_path,
      ));

      $rows[] = array(
        "fullpath" => $full_path,
        "name" => $browse_text,
        "uri" => $href->getPath(),
      );

      $dir_slash = null;
    }



    return $rows;
  }

}
