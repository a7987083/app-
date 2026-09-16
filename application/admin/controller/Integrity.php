<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\AuthorizationSchema;
use app\common\library\DataIntegrityAudit;

/**
 * Phase 15 read-only production data integrity audit.
 */
class Integrity extends Backend
{
    protected $noNeedRight = [];
    protected $layout = 'default';

    public function _initialize()
    {
        parent::_initialize();
        AuthorizationSchema::ensureAdmin();
    }

    public function index()
    {
        $this->view->assign('audit', DataIntegrityAudit::snapshot());
        return $this->view->fetch('authorization/integrity');
    }
}
