<?php

namespace app\admin\controller;

use app\common\controller\Backend;

/**
 * Phase 20 IPA management center.
 *
 * Phase 20.0 intentionally contains UI/read-only skeletons only. Mutating
 * OpenList, parser and write-back actions are introduced in later phases.
 */
class IpaCenter extends Backend
{
    protected $noNeedRight = [];
    protected $layout = 'default';

    public function index()
    {
        return $this->view->fetch();
    }

    public function metadata()
    {
        return $this->view->fetch();
    }

    public function binding()
    {
        return $this->view->fetch();
    }

    public function governance()
    {
        return $this->view->fetch();
    }

    public function task()
    {
        return $this->view->fetch();
    }

    public function writeback()
    {
        return $this->view->fetch();
    }

    public function setting()
    {
        return $this->view->fetch();
    }
}
