<?php
// +----------------------------------------------------------------------
// | 在线判题服务
// | 数据库队列 + CLI/Web 触发，逐用例编译运行并写回结果
// +----------------------------------------------------------------------

namespace app\service;

use think\facade\Db;
use think\facade\Env;

class JudgeService
{
    // 支持的语言
    public static $languages = [
        'c'      => ['name' => 'C', 'file' => 'main.c', 'monaco' => 'c'],
        'cpp'    => ['name' => 'C++', 'file' => 'main.cpp', 'monaco' => 'cpp'],
        'python' => ['name' => 'Python 3', 'file' => 'main.py', 'monaco' => 'python'],
        'js'     => ['name' => 'JavaScript', 'file' => 'main.js', 'monaco' => 'javascript'],
    ];

    // 判题状态
    public static $statusMap = [
        'pending' => '排队中',
        'judging' => '判题中',
        'ac'      => '答案正确',
        'ce'      => '编译错误',
        'wa'      => '答案错误',
        'tle'     => '运行超时',
        'mle'     => '内存超限',
        're'      => '运行错误',
        'se'      => '系统错误',
    ];

    // 难度
    public static $difficultyMap = [1 => '简单', 2 => '中等', 3 => '困难'];

    /**
     * 获取当前服务器可用的编译器/解释器
     */
    public static function detectBinaries()
    {
        return [
            'c'      => self::findBinary('gcc'),
            'cpp'    => self::findBinary('g++'),
            'python' => self::findBinary('python3') ?: self::findBinary('python'),
            'js'     => self::findBinary('node'),
        ];
    }

    /**
     * 语言是否可用
     */
    public static function languageAvailable($lang, $binaries = null)
    {
        if ($binaries === null) {
            $binaries = self::detectBinaries();
        }
        return !empty($binaries[$lang]);
    }

    /**
     * 消费队列：最多处理 limit 条待判题记录
     * 使用文件锁避免多个入口同时判题
     */
    public static function runPending($limit = 1)
    {
        @set_time_limit(180);
        $lockDir = runtime_path() . 'oj';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }
        $fp = @fopen($lockDir . DIRECTORY_SEPARATOR . 'queue.lock', 'c');
        if (!$fp) {
            return 0;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return 0;
        }

        $done = 0;
        try {
            for ($i = 0; $i < $limit; $i++) {
                $id = Db::name('oj_submissions')->where('status', 'pending')->order('id', 'asc')->value('id');
                if (!$id) {
                    break;
                }
                self::runOne(intval($id));
                $done++;
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        return $done;
    }

    /**
     * 启动后台判题进程（Web 提交后调用，不阻塞请求）
     */
    public static function spawnWorker()
    {
        $script = root_path() . 'bin' . DIRECTORY_SEPARATOR . 'judge.php';
        if (!is_file($script)) {
            return false;
        }
        $php = PHP_BINARY;
        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = 'start /B "" "' . $php . '" "' . $script . '" > NUL 2>&1';
            $fp = @popen($cmd, 'r');
            if ($fp) {
                @pclose($fp);
            }
        } else {
            @exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 &');
        }
        return true;
    }

    /**
     * 状态中文名
     */
    public static function statusText($status)
    {
        return isset(self::$statusMap[$status]) ? self::$statusMap[$status] : $status;
    }

    /**
     * 判定单条提交
     */
    public static function runOne($submissionId)
    {
        @set_time_limit(180);
        $sub = Db::name('oj_submissions')->where('id', $submissionId)->find();
        if (!$sub || $sub['status'] !== 'pending') {
            return false;
        }
        // 原子认领，避免重复判题
        $claimed = Db::name('oj_submissions')
            ->where('id', $submissionId)
            ->where('status', 'pending')
            ->update(['status' => 'judging']);
        if (!$claimed) {
            return false;
        }

        $problem = Db::name('oj_problems')->where('id', $sub['problem_id'])->find();
        if (!$problem) {
            self::finalize($submissionId, 'se', '题目不存在或已被删除', [], 0, 0, 0);
            return true;
        }

        $lang = (string) $sub['language'];
        if (!isset(self::$languages[$lang])) {
            self::finalize($submissionId, 'se', '不支持的程序语言：' . $lang, [], 0, 0, 0);
            return true;
        }

        $binaries = self::detectBinaries();
        $bin = isset($binaries[$lang]) ? $binaries[$lang] : null;
        if (!$bin) {
            $need = $lang === 'c' ? 'gcc' : ($lang === 'cpp' ? 'g++' : ($lang === 'python' ? 'python3' : 'node'));
            self::finalize($submissionId, 'se', '当前判题服务器未安装 ' . $need . '，暂时无法评测该语言。请联系管理员配置判题环境。', [], 0, 0, 0);
            return true;
        }

        $caseQuery = Db::name('oj_test_cases')
            ->where('problem_id', $problem['id'])
            ->where('enabled', 1);
        // 学生样例自测（is_test=2）只跑公开样例
        if (intval($sub['is_test']) === 2) {
            $caseQuery->where('type', 1);
        }
        $cases = $caseQuery
            ->order('type', 'asc')
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();
        $cases = is_array($cases) ? $cases : $cases->toArray();

        if (!$cases) {
            self::finalize($submissionId, 'se', '该题目尚未配置可用的测试用例，无法判题。', [], 0, 0, 0);
            return true;
        }

        $workDir = self::makeWorkDir($submissionId);
        if (!$workDir) {
            self::finalize($submissionId, 'se', '判题临时目录创建失败，请检查 runtime 目录写入权限。', [], 0, 0, 0);
            return true;
        }

        $srcFile = $workDir . DIRECTORY_SEPARATOR . self::$languages[$lang]['file'];
        file_put_contents($srcFile, (string) $sub['code']);

        $compile = self::compile($lang, $bin, $workDir);
        if (!$compile['ok']) {
            $log = trim((string) $compile['log']);
            if ($log === '') {
                $log = '编译失败（无输出信息）';
            }
            self::cleanup($workDir);
            self::finalize($submissionId, 'ce', mb_substr($log, 0, 8000, 'UTF-8'), [], 0, 0, 0);
            return true;
        }

        $caseCount = count($cases);
        $perScore = $caseCount > 0 ? floatval($problem['total_score']) / $caseCount : 0;
        $results = [];
        $maxTime = 0;
        $maxMemory = 0;
        $allAc = true;
        $firstFail = '';
        $hasSe = false;

        foreach ($cases as $idx => $case) {
            $res = self::runCase($compile['run'], $case, $workDir, $problem, $idx);
            $maxTime = max($maxTime, intval($res['time']));
            $maxMemory = max($maxMemory, intval($res['memory']));
            if ($res['status'] !== 'ac') {
                $allAc = false;
                if ($firstFail === '') {
                    $firstFail = $res['status'];
                }
                if ($res['status'] === 'se') {
                    $hasSe = true;
                }
            }
            $caseScore = floatval($case['score']) > 0 ? floatval($case['score']) : $perScore;
            $awarded = $res['status'] === 'ac' ? round($caseScore, 2) : 0;
            $showDetail = intval($case['type']) === 1
                || intval($sub['is_test']) > 0
                || intval($problem['show_detail']) === 1;

            $results[] = [
                'case_id'  => intval($case['id']),
                'name'     => $case['name'] !== '' ? $case['name'] : ('用例' . ($idx + 1)),
                'type'     => intval($case['type']),
                'status'   => $res['status'],
                'time'     => intval($res['time']),
                'memory'   => intval($res['memory']),
                'score'    => $awarded,
                'input'    => $showDetail ? mb_substr((string) $case['input'], 0, 4000, 'UTF-8') : '',
                'expected' => $showDetail ? mb_substr((string) $case['output'], 0, 4000, 'UTF-8') : '',
                'output'   => $showDetail ? mb_substr((string) $res['output'], 0, 4000, 'UTF-8') : '',
            ];
        }

        // 计分
        if ($problem['score_mode'] === 'all') {
            $score = $allAc ? floatval($problem['total_score']) : 0;
        } else {
            $score = 0;
            foreach ($results as $r) {
                $score += floatval($r['score']);
            }
            if ($score > floatval($problem['total_score'])) {
                $score = floatval($problem['total_score']);
            }
        }
        $score = round($score, 2);

        if ($allAc) {
            $status = 'ac';
        } elseif ($hasSe) {
            $status = 'se';
        } elseif ($firstFail !== '') {
            $status = $firstFail;
        } else {
            $status = 'wa';
        }

        self::cleanup($workDir);
        self::finalize($submissionId, $status, '', $results, $score, $maxTime, $maxMemory);
        return true;
    }

    /**
     * 写回判题结果
     */
    protected static function finalize($id, $status, $compileLog, $results, $score, $maxTime, $maxMemory)
    {
        Db::name('oj_submissions')->where('id', $id)->update([
            'status'      => $status,
            'score'       => round(floatval($score), 2),
            'time_used'   => intval($maxTime),
            'memory_used' => intval($maxMemory),
            'compile_log' => (string) $compileLog,
            'result_json' => json_encode($results, JSON_UNESCAPED_UNICODE),
            'judge_time'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 编译（Python/JS 做语法检查）
     */
    protected static function compile($lang, $bin, $workDir)
    {
        $isWin = DIRECTORY_SEPARATOR === '\\';
        $src = $workDir . DIRECTORY_SEPARATOR . self::$languages[$lang]['file'];

        if ($lang === 'c' || $lang === 'cpp') {
            $exe = $workDir . DIRECTORY_SEPARATOR . ($isWin ? 'main.exe' : 'main');
            $std = $lang === 'c' ? 'c11' : 'c++17';
            $cmd = self::q($bin) . ' -O2 -std=' . $std . ' -o ' . self::q($exe) . ' ' . self::q($src) . ' -lm';
            $out = self::execCapture($cmd, $workDir, 30);
            if ($out['code'] !== 0) {
                return ['ok' => false, 'log' => $out['output'], 'run' => ''];
            }
            return ['ok' => true, 'log' => '', 'run' => self::q($exe)];
        }

        if ($lang === 'python') {
            $cmd = self::q($bin) . ' -m py_compile ' . self::q($src);
            $out = self::execCapture($cmd, $workDir, 20);
            if ($out['code'] !== 0) {
                return ['ok' => false, 'log' => $out['output'], 'run' => ''];
            }
            return ['ok' => true, 'log' => '', 'run' => self::q($bin) . ' ' . self::q($src)];
        }

        // js
        $cmd = self::q($bin) . ' --check ' . self::q($src);
        $out = self::execCapture($cmd, $workDir, 20);
        if ($out['code'] !== 0) {
            return ['ok' => false, 'log' => $out['output'], 'run' => ''];
        }
        return ['ok' => true, 'log' => '', 'run' => self::q($bin) . ' ' . self::q($src)];
    }

    /**
     * 执行一个测试用例
     */
    protected static function runCase($runBase, $case, $workDir, $problem, $idx)
    {
        $isWin = DIRECTORY_SEPARATOR === '\\';
        $inFile = $workDir . DIRECTORY_SEPARATOR . 'in_' . $idx . '.txt';
        $outFile = $workDir . DIRECTORY_SEPARATOR . 'out_' . $idx . '.txt';
        $errFile = $workDir . DIRECTORY_SEPARATOR . 'err_' . $idx . '.txt';
        file_put_contents($inFile, (string) $case['input']);
        @unlink($outFile);
        @unlink($errFile);

        $timeLimit = max(100, intval($problem['time_limit']));
        $memoryLimit = max(1024, intval($problem['memory_limit']));

        if ($isWin) {
            $cmd = $runBase . ' < ' . self::q($inFile) . ' > ' . self::q($outFile) . ' 2> ' . self::q($errFile);
        } else {
            $inner = 'ulimit -v ' . $memoryLimit . '; exec ' . $runBase
                . ' < ' . self::q($inFile) . ' > ' . self::q($outFile) . ' 2> ' . self::q($errFile);
            $cmd = 'bash -c ' . self::q($inner);
        }

        $null = $isWin ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $null, 'r'],
            1 => ['file', $null, 'w'],
            2 => ['file', $null, 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes, $workDir, self::childEnv());
        if (!is_resource($proc)) {
            return ['status' => 'se', 'time' => 0, 'memory' => 0, 'output' => ''];
        }

        $start = microtime(true);
        $timedOut = false;
        $memory = 0;
        $exitCode = null;

        while (true) {
            $st = proc_get_status($proc);
            $elapsed = (microtime(true) - $start) * 1000;

            if (!$isWin && !empty($st['pid'])) {
                $mem = self::readProcMemory($st['pid']);
                if ($mem > $memory) {
                    $memory = $mem;
                }
            }

            if (!$st['running']) {
                $exitCode = $st['exitcode'];
                break;
            }

            if ($elapsed > $timeLimit) {
                $timedOut = true;
                @proc_terminate($proc, 9);
                usleep(30000);
                $st2 = proc_get_status($proc);
                $exitCode = $st2['exitcode'];
                break;
            }
            usleep(4000);
        }

        $elapsedMs = intval(round((microtime(true) - $start) * 1000));
        @proc_close($proc);

        $output = is_file($outFile) ? (string) file_get_contents($outFile) : '';
        $error = is_file($errFile) ? (string) file_get_contents($errFile) : '';

        if ($timedOut) {
            return ['status' => 'tle', 'time' => $elapsedMs, 'memory' => $memory, 'output' => $output];
        }

        $memoryExceeded = false;
        if (!$isWin) {
            if ($memory >= $memoryLimit * 0.95) {
                $memoryExceeded = true;
            }
            if (preg_match('/(MemoryError|Cannot allocate memory|std::bad_alloc|OutOfMemory)/i', $error)) {
                $memoryExceeded = true;
            }
        }
        if ($memoryExceeded) {
            return ['status' => 'mle', 'time' => $elapsedMs, 'memory' => $memory, 'output' => $output];
        }

        if ($exitCode !== 0) {
            return ['status' => 're', 'time' => $elapsedMs, 'memory' => $memory, 'output' => $error !== '' ? $error : $output];
        }

        $ignore = intval($problem['ignore_trailing']) === 1;
        $actual = self::normalize($output, $ignore);
        $expected = self::normalize((string) $case['output'], $ignore);

        if ($actual !== $expected) {
            return ['status' => 'wa', 'time' => $elapsedMs, 'memory' => $memory, 'output' => $output];
        }

        return ['status' => 'ac', 'time' => $elapsedMs, 'memory' => $memory, 'output' => $output];
    }

    /**
     * 输出归一化
     */
    protected static function normalize($text, $ignoreTrailing)
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        if ($ignoreTrailing) {
            $lines = explode("\n", $text);
            foreach ($lines as $k => $line) {
                $lines[$k] = rtrim($line);
            }
            $text = rtrim(implode("\n", $lines), "\n");
        }
        return $text;
    }

    /**
     * 读取 Linux 进程峰值内存（KB）
     */
    protected static function readProcMemory($pid)
    {
        $file = '/proc/' . $pid . '/status';
        if (!is_file($file)) {
            return 0;
        }
        $content = @file_get_contents($file);
        if ($content && preg_match('/VmHWM:\s+(\d+)\s+kB/', $content, $m)) {
            return intval($m[1]);
        }
        return 0;
    }

    /**
     * 以文件方式捕获命令输出（避免管道阻塞）
     */
    protected static function execCapture($cmd, $cwd, $timeoutSec)
    {
        $outFile = $cwd . DIRECTORY_SEPARATOR . 'cmd_out.log';
        $errFile = $cwd . DIRECTORY_SEPARATOR . 'cmd_err.log';
        @unlink($outFile);
        @unlink($errFile);

        $isWin = DIRECTORY_SEPARATOR === '\\';
        $null = $isWin ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $null, 'r'],
            1 => ['file', $outFile, 'w'],
            2 => ['file', $errFile, 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes, $cwd, self::childEnv());
        if (!is_resource($proc)) {
            return ['code' => -1, 'output' => '无法启动编译进程'];
        }

        $start = microtime(true);
        $code = null;
        while (true) {
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $code = $st['exitcode'];
                break;
            }
            if ((microtime(true) - $start) > $timeoutSec) {
                @proc_terminate($proc, 9);
                usleep(30000);
                $code = -1;
                break;
            }
            usleep(10000);
        }
        @proc_close($proc);

        $out = is_file($outFile) ? (string) file_get_contents($outFile) : '';
        $err = is_file($errFile) ? (string) file_get_contents($errFile) : '';
        $log = trim($out . ($err !== '' ? "\n" . $err : ''));
        return ['code' => intval($code), 'output' => $log];
    }

    /**
     * 子进程环境变量（保证中文输出为 UTF-8）
     */
    protected static function childEnv()
    {
        $env = getenv();
        if (!is_array($env)) {
            $env = [];
        }
        $env['PYTHONIOENCODING'] = 'utf-8';
        $env['PYTHONUTF8'] = '1';
        return $env;
    }

    /**
     * 命令参数转义
     */
    protected static function q($value)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return '"' . str_replace('"', '\\"', (string) $value) . '"';
        }
        return escapeshellarg((string) $value);
    }

    /**
     * 查找可执行文件
     */
    protected static function findBinary($name)
    {
        static $cache = [];
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        $bin = self::configuredBinary($name);
        if (!$bin) {
            if (DIRECTORY_SEPARATOR === '\\') {
                $out = @shell_exec('where ' . $name . ' 2>NUL');
                $lines = preg_split('/\r?\n/', trim((string) $out));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '' && stripos($line, 'INFO:') === false && is_file($line)) {
                        $bin = $line;
                        break;
                    }
                }
            } else {
                $out = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
                $line = trim((string) $out);
                if ($line !== '') {
                    $bin = $line;
                }
            }
        }
        if (!$bin) {
            $bin = self::searchCommonDirs($name);
        }
        $cache[$name] = $bin;
        return $bin;
    }

    /**
     * 读取环境变量中指定的可执行文件路径（如 OJ_GCC=C:\mingw64\bin\gcc.exe）
     */
    protected static function configuredBinary($name)
    {
        $map = [
            'gcc'     => 'OJ_GCC',
            'g++'     => 'OJ_GXX',
            'python3' => 'OJ_PYTHON',
            'python'  => 'OJ_PYTHON',
            'node'    => 'OJ_NODE',
        ];
        if (!isset($map[$name])) {
            return null;
        }
        $path = Env::get($map[$name]);
        if (!$path) {
            $path = getenv($map[$name]);
        }
        $path = trim((string) $path);
        return $path !== '' && is_file($path) ? $path : null;
    }

    /**
     * 在 PATH 之外探测常见安装目录（主要用于 Windows 下的 MinGW）
     */
    protected static function searchCommonDirs($name)
    {
        $exe = DIRECTORY_SEPARATOR === '\\' ? $name . '.exe' : $name;
        $dirs = [];
        if (DIRECTORY_SEPARATOR === '\\') {
            $dirs = [
                'C:\mingw64\bin',
                'C:\msys64\mingw64\bin',
                'C:\msys64\ucrt64\bin',
                'C:\MinGW\bin',
                'C:\w64devkit\bin',
            ];
            foreach (['C:\Program Files', 'C:\Program Files (x86)'] as $pf) {
                foreach ((array) glob($pf . '\mingw-w64\*\mingw64\bin') as $dir) {
                    $dirs[] = $dir;
                }
                foreach ((array) glob($pf . '\w64devkit*\bin') as $dir) {
                    $dirs[] = $dir;
                }
                $dirs[] = $pf . '\mingw64\bin';
            }
        } else {
            $dirs = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin'];
        }
        foreach ($dirs as $dir) {
            $file = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $exe;
            if (is_file($file)) {
                return $file;
            }
        }
        return null;
    }

    protected static function makeWorkDir($submissionId)
    {
        $base = runtime_path() . 'oj' . DIRECTORY_SEPARATOR . 'work';
        if (!is_dir($base)) {
            @mkdir($base, 0777, true);
        }
        if (!is_dir($base)) {
            return null;
        }
        $dir = $base . DIRECTORY_SEPARATOR . 'sub_' . intval($submissionId) . '_' . substr(md5(uniqid('', true)), 0, 8);
        if (!@mkdir($dir, 0777, true)) {
            return null;
        }
        return $dir;
    }

    protected static function cleanup($dir)
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    self::cleanup($path);
                } else {
                    @unlink($path);
                }
            }
        }
        @rmdir($dir);
    }
}
