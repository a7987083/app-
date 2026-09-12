<?php

namespace app\common\library\update;

use think\Db;

class UpdateBackup
{
    protected $root;
    protected $backupDir;

    public function __construct($root, $backupDir)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $this->backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function create($programDir)
    {
        if (!is_dir($this->backupDir) && !@mkdir($this->backupDir, 0755, true)) {
            throw new \RuntimeException('无法创建更新备份目录');
        }
        $created = [];
        $this->backupProgramFiles($programDir, $created);
        $this->backupSpecialFile('ver.json', $created);
        $this->backupSpecialFile('public/update/ver.txt', $created);
        if (file_put_contents($this->backupDir . 'created.json', json_encode($created, JSON_UNESCAPED_SLASHES)) === false) {
            throw new \RuntimeException('无法写入更新备份清单');
        }
        $this->dumpDatabase($this->backupDir . 'database.sql');
        return $this->backupDir;
    }

    public function rollback()
    {
        $errors = [];
        $filesDir = $this->backupDir . 'files' . DIRECTORY_SEPARATOR;
        if (is_dir($filesDir)) {
            $errors = array_merge($errors, $this->restoreFiles($filesDir, $filesDir));
        }

        $createdRaw = @file_get_contents($this->backupDir . 'created.json');
        $created = $createdRaw !== false ? json_decode($createdRaw, true) : [];
        if (is_array($created)) {
            foreach ($created as $relative) {
                $target = $this->root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_file($target) && !@unlink($target)) {
                    $errors[] = '无法删除更新中新建的文件: ' . $relative;
                }
            }
        }

        $db = $this->backupDir . 'database.sql';
        if (is_file($db)) {
            try {
                (new UpdateSqlRunner())->runFile($db);
            } catch (\Exception $e) {
                $errors[] = '数据库恢复失败: ' . $e->getMessage();
            }
        }

        if ($errors) {
            throw new \RuntimeException('回滚未完全成功: ' . implode('; ', $errors));
        }
    }

    protected function backupSpecialFile($relative, array &$created)
    {
        $relative = str_replace('\\', '/', $relative);
        $target = $this->root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($target)) {
            $backup = $this->backupDir . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $dir = dirname($backup);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                throw new \RuntimeException('无法创建版本文件备份目录');
            }
            if (!@copy($target, $backup)) {
                throw new \RuntimeException('版本文件备份失败: ' . $relative);
            }
        } else {
            $created[] = $relative;
        }
    }

    protected function backupProgramFiles($programDir, array &$created)
    {
        $programDir = rtrim($programDir, '/\\') . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($programDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($programDir)));
            $target = $this->root . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($target)) {
                $backup = $this->backupDir . 'files' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $dir = dirname($backup);
                if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                    throw new \RuntimeException('无法创建文件备份目录');
                }
                if (!@copy($target, $backup)) {
                    throw new \RuntimeException('文件备份失败: ' . $relative);
                }
            } else {
                $created[] = $relative;
            }
        }
    }

    protected function restoreFiles($dir, $base)
    {
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($base));
            $displayRelative = ltrim(str_replace('\\', '/', $relative), '/');
            $target = $this->root . $relative;
            $parent = dirname($target);
            if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
                $errors[] = '无法创建回滚目录: ' . dirname($displayRelative);
                continue;
            }
            if (!@copy($file->getPathname(), $target)) {
                $errors[] = '文件恢复失败: ' . $displayRelative;
            }
        }
        return $errors;
    }

    protected function dumpDatabase($file)
    {
        $fp = @fopen($file, 'wb');
        if (!$fp) {
            throw new \RuntimeException('数据库备份文件创建失败');
        }
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n");
        $rows = Db::query('SHOW TABLES');
        foreach ($rows as $row) {
            $table = reset($row);
            if (!$table || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                continue;
            }
            $createRows = Db::query('SHOW CREATE TABLE `' . $table . '`');
            if (!$createRows) {
                fclose($fp);
                throw new \RuntimeException('数据库结构备份失败: ' . $table);
            }
            $createRow = $createRows[0];
            $createSql = isset($createRow['Create Table']) ? $createRow['Create Table'] : end($createRow);
            fwrite($fp, "DROP TABLE IF EXISTS `" . $table . "`;\n" . $createSql . ";\n");
            $offset = 0;
            $limit = 500;
            do {
                $dataRows = Db::query('SELECT * FROM `' . $table . '` LIMIT ' . intval($offset) . ',' . intval($limit));
                foreach ($dataRows as $dataRow) {
                    $columns = [];
                    $values = [];
                    foreach ($dataRow as $column => $value) {
                        $columns[] = '`' . str_replace('`', '``', $column) . '`';
                        $values[] = $this->quote($value);
                    }
                    fwrite($fp, 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                }
                $offset += $limit;
            } while (count($dataRows) === $limit);
        }
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);
    }

    protected function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        $value = (string)$value;
        $value = str_replace(["\\", "\0", "\n", "\r", "\x1a", "'"], ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'"], $value);
        return "'" . $value . "'";
    }
}
