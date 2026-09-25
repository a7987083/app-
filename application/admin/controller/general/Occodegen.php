<?php

namespace app\admin\controller\general;

class Occodegen extends \app\admin\controller\DylibCodegen
{
    /**
     * 2407 compatibility route. The standalone UI was retired in 2408;
     * all generation actions are inherited from DylibCodegen and explicitly
     * require dylib_center/index permission.
     */
    public function index()
    {
        $this->requireDylibCenterRight();
        return redirect('dylib_center/index');
    }
}
