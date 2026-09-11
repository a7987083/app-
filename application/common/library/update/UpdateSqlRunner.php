<?php

namespace app\common\library\update;

use think\Db;

class UpdateSqlRunner
{
    public function runDirectory($dir)
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.sql');
        if (!$files) {
            return 0;
        }
        sort($files, SORT_STRING);
        $count = 0;
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException('SQL 文件读取失败: ' . basename($file));
            }
            foreach (self::splitStatements($sql) as $statement) {
                Db::execute($statement);
                $count++;
            }
        }
        return $count;
    }

    public function runFile($file)
    {
        $sql = @file_get_contents($file);
        if ($sql === false) {
            throw new \RuntimeException('SQL 备份读取失败');
        }
        $count = 0;
        foreach (self::splitStatements($sql) as $statement) {
            Db::execute($statement);
            $count++;
        }
        return $count;
    }

    public static function splitStatements($sql)
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = '';
        $lineComment = false;
        $blockComment = false;
        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            if ($lineComment) {
                if ($ch === "\n") {
                    $lineComment = false;
                    $buffer .= $ch;
                }
                continue;
            }
            if ($blockComment) {
                if ($ch === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }
            if ($quote === '') {
                if (($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $ch === '#') {
                    $lineComment = true;
                    if ($ch === '-') {
                        $i++;
                    }
                    continue;
                }
                if ($ch === '/' && $next === '*') {
                    $blockComment = true;
                    $i++;
                    continue;
                }
                if ($ch === "'" || $ch === '"' || $ch === '`') {
                    $quote = $ch;
                    $buffer .= $ch;
                    continue;
                }
                if ($ch === ';') {
                    $statement = trim($buffer);
                    if ($statement !== '') {
                        $statements[] = $statement;
                    }
                    $buffer = '';
                    continue;
                }
                $buffer .= $ch;
                continue;
            }
            $buffer .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }
            if ($ch === $quote) {
                if ($i + 1 < $length && $sql[$i + 1] === $quote && $quote !== '`') {
                    $buffer .= $sql[++$i];
                    continue;
                }
                $quote = '';
            }
        }
        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }
}
