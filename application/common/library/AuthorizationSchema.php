<?php

namespace app\common\library;

use think\Db;

/**
 * Idempotent Phase 11 schema/config bootstrap. It only runs on authorization
 * related paths (and admin shell) so normal source reads do not ALTER tables.
 */
class AuthorizationSchema
{
    protected static $coreReady = false;
    protected static $adminReady = false;

    public static function ensure()
    {
        if (self::$coreReady) {
            return;
        }

        $column = Db::query("SHOW COLUMNS FROM `fa_kami` LIKE 'transfer_count'");
        if (!$column) {
            Db::execute("ALTER TABLE `fa_kami` ADD COLUMN `transfer_count` int(10) unsigned NOT NULL DEFAULT '100' COMMENT '剩余换绑次数' AFTER `kmyp`");
        } else {
            $default = isset($column[0]['Default']) ? (int)$column[0]['Default'] : 0;
            if ($default === 0) {
                // Phase 11 中 transfer_count 表示“已用次数”。Phase 12.1 起改为
                // “剩余次数”，并把旧数据一次性转换：0->100、1->99...。
                Db::execute("UPDATE `fa_kami` SET `transfer_count`=GREATEST(0,100-`transfer_count`)");
                Db::execute("ALTER TABLE `fa_kami` MODIFY COLUMN `transfer_count` int(10) unsigned NOT NULL DEFAULT '100' COMMENT '剩余换绑次数'");
            }
        }

        Db::execute("CREATE TABLE IF NOT EXISTS `fa_card_transfer_log` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `kami_id` int(11) unsigned NOT NULL DEFAULT '0',
            `kami` varchar(128) NOT NULL DEFAULT '',
            `old_udid` varchar(128) NOT NULL DEFAULT '',
            `new_udid` varchar(128) NOT NULL DEFAULT '',
            `ip` varchar(64) NOT NULL DEFAULT '',
            `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
            `message` varchar(255) NOT NULL DEFAULT '',
            `endtime` int(11) NOT NULL DEFAULT '0',
            `transfer_count` int(10) unsigned NOT NULL DEFAULT '0',
            `addtime` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `idx_old_udid` (`old_udid`),
            KEY `idx_new_udid` (`new_udid`),
            KEY `idx_kami` (`kami`),
            KEY `idx_ip_addtime` (`ip`,`addtime`),
            KEY `idx_status_addtime` (`status`,`addtime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='设备换绑日志'");

        Db::execute("CREATE TABLE IF NOT EXISTS `fa_authorization_event` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `event` varchar(32) NOT NULL DEFAULT '',
            `kami_id` int(11) unsigned NOT NULL DEFAULT '0',
            `kami` varchar(128) NOT NULL DEFAULT '',
            `udid` varchar(128) NOT NULL DEFAULT '',
            `related_udid` varchar(128) NOT NULL DEFAULT '',
            `duration` int(11) NOT NULL DEFAULT '0',
            `endtime` int(11) NOT NULL DEFAULT '0',
            `ip` varchar(64) NOT NULL DEFAULT '',
            `detail` varchar(255) NOT NULL DEFAULT '',
            `addtime` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `idx_event_addtime` (`event`,`addtime`),
            KEY `idx_udid` (`udid`),
            KEY `idx_kami` (`kami`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='授权事件日志'");

        self::ensureConfig('unbind_max_count', '新卡默认换绑次数', '新生成卡密默认可换绑次数', 'number', '100');
        Db::table('fa_config')->where('name', 'unbind_max_count')->where('value', '3')->update([
            'title' => '新卡默认换绑次数',
            'tip' => '新生成卡密默认可换绑次数',
            'value' => '100',
        ]);
        self::ensureConfig('unbind_daily_limit', '每日换绑次数', '同一授权链每天最多成功换绑次数', 'number', '1');
        self::ensureConfig('unbind_cooldown_seconds', '换绑冷却秒数', '成功换绑后再次换绑的最短间隔', 'number', '3600');
        self::ensureConfig('unbind_ip_hour_limit', 'IP每小时尝试次数', '同一IP每小时最多提交换绑请求次数', 'number', '10');

        self::$coreReady = true;
    }

    public static function ensureAdmin()
    {
        self::ensure();
        if (self::$adminReady) {
            return;
        }

        $parent = Db::table('fa_auth_rule')->where('name', 'authorization')->find();
        if (!$parent) {
            $parentId = Db::table('fa_auth_rule')->insertGetId([
                'type' => 'file', 'pid' => 0, 'name' => 'authorization', 'title' => '授权中心',
                'icon' => 'fa fa-shield', 'condition' => '', 'remark' => '', 'ismenu' => 1,
                'createtime' => time(), 'updatetime' => time(), 'weigh' => 87, 'status' => 'normal',
            ]);
        } else {
            $parentId = (int)$parent['id'];
        }

        $children = [
            ['authorization/index', '授权总览', 'fa fa-dashboard', 4],
            ['authorization/transfers', '换绑记录', 'fa fa-exchange', 3],
            ['authorization/events', '授权事件', 'fa fa-list', 2],
            ['authorization/diagnostic', '系统诊断', 'fa fa-stethoscope', 1],
        ];
        foreach ($children as $item) {
            if (!Db::table('fa_auth_rule')->where('name', $item[0])->find()) {
                Db::table('fa_auth_rule')->insert([
                    'type' => 'file', 'pid' => $parentId, 'name' => $item[0], 'title' => $item[1],
                    'icon' => $item[2], 'condition' => '', 'remark' => '', 'ismenu' => 1,
                    'createtime' => time(), 'updatetime' => time(), 'weigh' => $item[3], 'status' => 'normal',
                ]);
            }
        }

        self::$adminReady = true;
    }

    protected static function ensureConfig($name, $title, $tip, $type, $value)
    {
        if (!Db::table('fa_config')->where('name', $name)->find()) {
            Db::table('fa_config')->insert([
                'name' => $name,
                'group' => 'basic',
                'title' => $title,
                'tip' => $tip,
                'type' => $type,
                'value' => $value,
                'content' => '',
                'rule' => '',
                'extend' => '',
            ]);
        }
    }
}
