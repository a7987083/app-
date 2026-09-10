<?php

namespace app\common\behavior;

use app\common\library\SiteConfigSync;
use think\Config;
use think\Lang;
use think\Loader;

class Common
{
    public function moduleInit(&$request)
    {
        // 更新包解压后补全 site.php 缺失字段（不覆盖站点已有配置）
        SiteConfigSync::runIfNeeded();

        // 设置mbstring字符编码
        mb_internal_encoding("UTF-8");

        // 如果修改了index.php入口地址，则需要手动修改cdnurl的值
        $url = preg_replace("/\/(\w+)\.php$/i", '', $request->root());
        // 如果未设置__CDN__则自动匹配得出
        if (!Config::get('view_replace_str.__CDN__')) {
            Config::set('view_replace_str.__CDN__', $url);
        }
        // 如果未设置__PUBLIC__则自动匹配得出
        if (!Config::get('view_replace_str.__PUBLIC__')) {
            Config::set('view_replace_str.__PUBLIC__', $url . '/');
        }
        // 如果未设置__ROOT__则自动匹配得出
        if (!Config::get('view_replace_str.__ROOT__')) {
            Config::set('view_replace_str.__ROOT__', preg_replace("/\/public\/$/", '', $url . '/'));
        }
        // 如果未设置cdnurl则自动匹配得出
        if (!Config::get('site.cdnurl')) {
            Config::set('site.cdnurl', $url);
        }
        // 如果未设置cdnurl则自动匹配得出
        if (!Config::get('upload.cdnurl')) {
            Config::set('upload.cdnurl', $url);
        }
        // 给 CSS/JS 追加版本号，避免浏览器一直加载缓存的旧脚本
        $assetVersion = (string)Config::get('site.version');
        $backendJsDir = ROOT_PATH . 'public' . DS . 'assets' . DS . 'js' . DS . 'backend';
        $jsMtime = 0;
        if (is_dir($backendJsDir)) {
            foreach (glob($backendJsDir . DS . '*.js') ?: [] as $jsFile) {
                $jsMtime = max($jsMtime, (int)filemtime($jsFile));
            }
        }
        if ($jsMtime) {
            $assetVersion .= '.' . $jsMtime;
        }
        if (Config::get('app_debug')) {
            // 调试模式用时间戳，改完刷新即可生效
            $assetVersion = (string)time();
            Config::set('exception_tmpl', THINK_PATH . 'tpl' . DS . 'think_exception.tpl');
        }
        Config::set('site.version', $assetVersion);
        // 如果是trace模式且Ajax的情况下关闭trace
        if (Config::get('app_trace') && $request->isAjax()) {
            Config::set('app_trace', false);
        }
        // 切换多语言
        if (Config::get('lang_switch_on') && $request->get('lang')) {
            \think\Cookie::set('think_var', $request->get('lang'));
        }
        // Form别名
        if (!class_exists('Form')) {
            class_alias('fast\\Form', 'Form');
        }
    }

    public function addonBegin(&$request)
    {
        // 加载插件语言包
        Lang::load([
            APP_PATH . 'common' . DS . 'lang' . DS . $request->langset() . DS . 'addon' . EXT,
        ]);
        $this->moduleInit($request);
    }
}
