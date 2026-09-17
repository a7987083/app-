<?php

namespace app\index\controller;

/**
 * Compatibility tombstone for installations upgraded from the former public
 * AppStore V3 experiment.
 *
 * The supported public software-source endpoint is /appstore only. The custom
 * V3 routes have been removed. Keeping this tiny controller in the online
 * update package ensures an older SourceV3.php cannot remain directly callable
 * through ThinkPHP's default controller routing after an in-place upgrade.
 */
class SourceV3
{
    public function meta()
    {
        $this->retired();
    }

    public function apps()
    {
        $this->retired();
    }

    public function delta()
    {
        $this->retired();
    }

    public function _empty()
    {
        $this->retired();
    }

    protected function retired()
    {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(['code' => 0, 'msg' => 'Not Found']);
        exit;
    }
}
