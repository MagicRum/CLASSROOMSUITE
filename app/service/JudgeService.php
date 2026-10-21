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
    // 单个用例输出/错误流的最大读取量，防止死循环打印撑爆磁盘
    const MAX_OUTPUT_BYTES = 16777216;

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
        'cheat'   => '疑似作弊',
    ];

    // 各语言读取输入的特征（用于识别直接输出固定答案的提交）
    protected static $inputPatterns = [
        'c'      => '/\b(scanf|fscanf|sscanf|gets|fgets|getchar|getc|fread|read)\s*\(/i',
        'cpp'    => '/\b(scanf|fscanf|sscanf|gets|fgets|getchar|cin|getline|fread|read)\b/i',
        'python' => '/\b(input|raw_input)\s*\(|sys\.stdin|open\s*\(\s*0|readline/i',
        'js'     => '/readFileSync|process\.stdin|readline|prompt\s*\(/i',
    ];

    // 各语言输出结果的特征（用于区分「直接输出固定答案」和「空程序」）
    protected static $outputPatterns = [
        'c'      => '/\b(printf|puts|putchar|fputs|fwrite|putc)\s*\(/i',
        'cpp'    => '/\b(printf|puts|putchar|cout|cerr|fputs|fwrite)\b/i',
        'python' => '/\bprint\s*\(|\bsys\.stdout/i',
        'js'     => '/console\.log|process\.stdout\.write|\bprint\s*\(/i',
    ];

    // 难度
    public static $difficultyMap = [1 => '简单', 2 => '中等', 3 => '困难'];

    /**
     * 获取当前服务器可用的编译器/解释器
     */
    public static function detectBinaries()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [
            'c'      => self::findBinary('gcc'),
            'cpp'    => self::findBinary('g++'),
            'python' => self::findBinary('python3') ?: self::findBinary('python'),
            'js'     => self::findBinary('node'),
        ];
        return $cache;
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
        $php = self::cliPhpBinary();
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
     * 定位 PHP CLI 可执行文件
     * php-fpm 环境下 PHP_BINARY 指向 php-fpm，不能用来跑脚本，需另行探测
     */
    protected static function cliPhpBinary()
    {
        static $bin = null;
        if ($bin !== null) {
            return $bin;
        }
        $candidates = [];
        $env = getenv('JUDGE_PHP_BIN');
        if ($env) {
            $candidates[] = $env;
        }
        if (strpos(PHP_BINARY, 'php-fpm') === false) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = '/usr/bin/php';
        $candidates[] = '/usr/local/bin/php';
        $glob = glob('/www/server/php/*/bin/php');
        if (is_array($glob) && $glob) {
            rsort($glob);
            foreach ($glob as $item) {
                $candidates[] = $item;
            }
        }
        foreach ($candidates as $item) {
            if ($item && @is_file($item) && @is_executable($item)) {
                return $bin = $item;
            }
        }
        // 兜底交给 shell 的 PATH 解析
        return $bin = 'php';
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

        // 未配置测试用例时，用题目公开样例兜底判题
        if (!$cases) {
            $sampleInput = (string) ($problem['sample_input'] ?? '');
            $sampleOutput = (string) ($problem['sample_output'] ?? '');
            if (trim($sampleInput) !== '' || trim($sampleOutput) !== '') {
                $cases = [[
                    'id'     => 0,
                    'name'   => '样例',
                    'type'   => 1,
                    'score'  => 0,
                    'input'  => $sampleInput,
                    'output' => $sampleOutput,
                ]];
            }
        }

        if (!$cases) {
            self::finalize($submissionId, 'se', '该题目尚未配置可用的测试用例，无法判题。', [], 0, 0, 0);
            return true;
        }

        $needsInput = false;
        foreach ($cases as $case) {
            if (trim((string) $case['input']) !== '') {
                $needsInput = true;
                break;
            }
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

        // 题目需要读入数据，代码既没有读取输入又固定输出内容，判定为疑似硬编码答案
        // 空实现（不读输入也不输出）属于正常错误提交，走常规判题给出 WA
        if (intval($sub['is_test']) === 0 && $needsInput
            && !self::readsInput($lang, (string) $sub['code'])
            && self::writesOutput($lang, (string) $sub['code'])) {
            self::cleanup($workDir);
            self::finalize($submissionId, 'cheat', '代码没有读取任何输入，疑似直接输出固定答案。请读取输入后计算输出。', [], 0, 0, 0);
            return true;
        }

        // 正式提交时按配置追加随机对拍用例
        $randomNote = '';
        if (intval($sub['is_test']) === 0 && intval($problem['random_cases'] ?? 0) > 0) {
            list($randomCases, $randomNote) = self::buildRandomCases($problem, $submissionId);
            if ($randomCases) {
                $cases = array_merge($cases, $randomCases);
            }
        }

        $caseCount = count($cases);
        $perScore = $caseCount > 0 ? floatval($problem['total_score']) / $caseCount : 0;
        $results = [];
        $maxTime = 0;
        $maxMemory = 0;
        $allAc = true;
        $firstFail = '';
        $hasSe = false;
        $actualOutputs = [];
        $expectedOutputs = [];
        $comparable = true;

        foreach ($cases as $idx => $case) {
            $res = self::runCase($compile['run'], $case, $workDir, $problem, $idx, (string) $sub['language']);
            if (!in_array($res['status'], ['ac', 'wa'], true)) {
                $comparable = false;
            }
            $actualOutputs[] = trim(self::normalize((string) $res['output'], false));
            $expectedOutputs[] = trim(self::normalize((string) $case['output'], false));
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

        $judgeLog = $randomNote;
        // 多组不同输入却输出完全一致，说明结果与输入无关，疑似直接打印固定答案
        if (intval($sub['is_test']) === 0 && !$allAc && $status !== 'se' && $comparable
            && count($actualOutputs) >= 2
            && count(array_unique($actualOutputs)) === 1
            && count(array_unique($expectedOutputs)) > 1
            && $actualOutputs[0] !== ''
        ) {
            $status = 'cheat';
            $score = 0;
            $judgeLog = trim($judgeLog . ' 多组不同输入得到完全相同的输出，疑似直接输出固定答案。请根据输入计算结果。');
        }

        self::cleanup($workDir);
        self::finalize($submissionId, $status, $judgeLog, $results, $score, $maxTime, $maxMemory);
        return true;
    }

    /**
     * 代码中是否存在读取输入的操作
     */
    protected static function readsInput($lang, $code)
    {
        if (!isset(self::$inputPatterns[$lang])) {
            return true;
        }
        return (bool) preg_match(self::$inputPatterns[$lang], self::stripComments($code));
    }

    /**
     * 代码中是否存在输出结果的操作
     */
    protected static function writesOutput($lang, $code)
    {
        if (!isset(self::$outputPatterns[$lang])) {
            return false;
        }
        return (bool) preg_match(self::$outputPatterns[$lang], self::stripComments($code));
    }

    /**
     * 去掉注释，避免只靠注释里的 scanf/printf 字样绕过检测
     */
    protected static function stripComments($code)
    {
        $code = (string) $code;
        $code = preg_replace('#/\*.*?\*/#s', '', $code);
        $code = preg_replace('#//[^\n]*#', '', $code);
        return preg_replace('/#[^\n]*/', '', $code);
    }

    /**
     * 用参考程序 + 随机数据生成器构造随机用例
     * 生成器输出一组输入，参考程序读取该输入并输出标准答案
     */
    protected static function buildRandomCases($problem, $submissionId)
    {
        $total = intval($problem['random_cases'] ?? 0);
        if ($total <= 0) {
            return [[], ''];
        }
        $refLang = (string) ($problem['ref_language'] ?? '');
        $genLang = (string) ($problem['gen_language'] ?? '');
        if (!isset(self::$languages[$refLang]) || !isset(self::$languages[$genLang])
            || trim((string) ($problem['ref_code'] ?? '')) === ''
            || trim((string) ($problem['gen_code'] ?? '')) === ''
        ) {
            return [[], ''];
        }

        $binaries = self::detectBinaries();
        $refBin = $binaries[$refLang] ?? null;
        $genBin = $binaries[$genLang] ?? null;
        if (!$refBin || !$genBin) {
            return [[], '随机对拍未启用：服务器缺少参考程序或生成器所需的运行环境。'];
        }

        // 参考程序与生成器按「题目 + 代码哈希」缓存编译产物，避免每次提交重复编译
        $refKey = 'ref_' . intval($problem['id']) . '_' . substr(md5($refLang . '|' . (string) $problem['ref_code']), 0, 10);
        $genKey = 'gen_' . intval($problem['id']) . '_' . substr(md5($genLang . '|' . (string) $problem['gen_code']), 0, 10);
        $refCompile = self::prepareCached($refLang, $refBin, (string) $problem['ref_code'], $refKey);
        $genCompile = self::prepareCached($genLang, $genBin, (string) $problem['gen_code'], $genKey);
        if (!$refCompile['ok'] || !$genCompile['ok']) {
            $why = !$refCompile['ok'] ? '参考程序编译失败' : '数据生成器编译失败';
            return [[], '随机对拍未启用：' . $why . '。'];
        }
        $refDir = $refCompile['dir'];
        $genDir = $genCompile['dir'];

        $cases = [];
        $probe = ['input' => '', 'output' => ''];
        for ($i = 0; $i < $total; $i++) {
            $seed = random_int(1, 999999);
            $gen = self::runCase($genCompile['run'] . ' ' . $seed, $probe, $genDir, $problem, 'g' . $i, $genLang);
            if (!in_array($gen['status'], ['ac', 'wa'], true)) {
                continue;
            }
            $input = (string) $gen['output'];
            if (trim($input) === '' || strlen($input) > 100000) {
                continue;
            }
            $ref = self::runCase($refCompile['run'], ['input' => $input, 'output' => ''], $refDir, $problem, 'r' . $i, $refLang);
            if (!in_array($ref['status'], ['ac', 'wa'], true)) {
                continue;
            }
            $cases[] = [
                'id'     => 0,
                'name'   => '随机数据 ' . ($i + 1),
                'type'   => 3,
                'score'  => 0,
                'input'  => $input,
                'output' => (string) $ref['output'],
            ];
        }

        if (!$cases) {
            return [[], '随机对拍未生成有效数据，请检查生成器与参考程序。'];
        }
        return [$cases, ''];
    }

    /**
     * 准备参考程序/生成器：编译产物按 key 缓存到 runtime/oj/cache，命中则直接复用
     */
    protected static function prepareCached($lang, $bin, $code, $key)
    {
        $isCompiled = in_array($lang, ['c', 'cpp'], true);
        $dir = runtime_path() . 'oj' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $key;
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return ['ok' => false, 'log' => '缓存目录创建失败', 'run' => '', 'dir' => ''];
        }
        $src = $dir . DIRECTORY_SEPARATOR . self::$languages[$lang]['file'];
        $target = $dir . DIRECTORY_SEPARATOR . (DIRECTORY_SEPARATOR === '\\' ? 'main.exe' : 'main');
        if ($isCompiled) {
            if (is_file($target)) {
                self::prependPath($bin);
                return ['ok' => true, 'log' => '', 'run' => self::q($target), 'dir' => $dir];
            }
        } elseif (is_file($src)) {
            return ['ok' => true, 'log' => '', 'run' => self::q($bin) . ' ' . self::q($src), 'dir' => $dir];
        }

        file_put_contents($src, (string) $code);
        $res = self::compile($lang, $bin, $dir);
        if (!$res['ok']) {
            return ['ok' => false, 'log' => (string) $res['log'], 'run' => '', 'dir' => $dir];
        }
        return ['ok' => true, 'log' => '', 'run' => $res['run'], 'dir' => $dir];
    }

    /**
     * 语法检查：只编译不运行，供编辑器「语法检查」使用
     */
    public static function syntaxCheck($lang, $code)
    {
        if (!isset(self::$languages[$lang])) {
            return ['ok' => false, 'log' => '不支持的程序语言'];
        }
        $binaries = self::detectBinaries();
        $bin = isset($binaries[$lang]) ? $binaries[$lang] : null;
        if (!$bin) {
            return ['ok' => false, 'log' => '当前判题服务器未安装该语言的编译器/解释器'];
        }
        $code = (string) $code;
        if (trim($code) === '') {
            return ['ok' => false, 'log' => '代码不能为空'];
        }
        if (strlen($code) > 65535) {
            return ['ok' => false, 'log' => '代码过长，请精简后检查'];
        }

        $workDir = self::makeWorkDir('check');
        if (!$workDir) {
            return ['ok' => false, 'log' => '检查目录创建失败，请检查 runtime 目录写入权限'];
        }
        file_put_contents($workDir . DIRECTORY_SEPARATOR . self::$languages[$lang]['file'], $code);
        $res = self::compile($lang, $bin, $workDir);
        self::cleanup($workDir);

        $log = trim((string) $res['log']);
        if ($log === '') {
            $log = $res['ok'] ? '语法检查通过，未发现编译错误' : '语法检查未通过（无输出信息）';
        }
        return ['ok' => (bool) $res['ok'], 'log' => $log];
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
        self::prependPath($bin);
        $src = $workDir . DIRECTORY_SEPARATOR . self::$languages[$lang]['file'];

        if ($lang === 'c' || $lang === 'cpp') {
            $exe = $workDir . DIRECTORY_SEPARATOR . ($isWin ? 'main.exe' : 'main');
            $std = $lang === 'c' ? 'c11' : self::cppStandard($bin, $workDir);
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
     * 根据编译器版本选择 C++ 标准（GCC 4.x 不支持 c++17）
     */
    protected static function cppStandard($bin, $workDir)
    {
        static $std = null;
        if ($std !== null) {
            return $std;
        }
        $out = self::execCapture(self::q($bin) . ' -dumpversion', $workDir, 10);
        $major = intval(strtok(trim((string) $out['output']), '.'));
        $std = $major >= 5 ? 'c++17' : 'c++11';
        return $std;
    }

    /**
     * 执行一个测试用例
     */
    protected static function runCase($runBase, $case, $workDir, $problem, $idx, $lang = '')
    {
        $isWin = DIRECTORY_SEPARATOR === '\\';
        $inFile = $workDir . DIRECTORY_SEPARATOR . 'in_' . $idx . '.txt';
        $outFile = $workDir . DIRECTORY_SEPARATOR . 'out_' . $idx . '.txt';
        $errFile = $workDir . DIRECTORY_SEPARATOR . 'err_' . $idx . '.txt';
        file_put_contents($inFile, (string) $case['input']);
        @unlink($outFile);
        @unlink($errFile);

        $timeLimit = max(100, intval($problem['time_limit']));
        if ($isWin) {
            // Windows 本地环境下安全软件会随机阻塞进程启动，放宽超时避免误判
            $timeLimit += 5000;
        }
        $memoryLimit = max(1024, intval($problem['memory_limit']));
        // 解释器自身需要较大的虚拟地址空间，限制过紧会导致系统错误
        $vmLimit = $memoryLimit;
        if ($lang === 'python') {
            $vmLimit = max($memoryLimit, 1048576);
        } elseif ($lang === 'js') {
            $vmLimit = max($memoryLimit, 2097152);
        }

        if ($isWin) {
            $cmd = $runBase . ' < ' . self::q($inFile) . ' > ' . self::q($outFile) . ' 2> ' . self::q($errFile);
        } else {
            // ulimit -f 按 512 字节块限制单个文件大小，避免无限输出写满磁盘
            $inner = 'ulimit -v ' . $vmLimit . '; ulimit -f ' . intval(self::MAX_OUTPUT_BYTES / 512) . '; exec ' . $runBase
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
        $tick = 0;

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
                self::killTree($st['pid']);
                @proc_terminate($proc, 9);
                usleep(30000);
                $st2 = proc_get_status($proc);
                $exitCode = $st2['exitcode'];
                break;
            }

            // Windows 无法用 ulimit 限制输出文件，运行期间轮询文件大小
            if ($isWin && ++$tick % 40 === 0) {
                clearstatcache(true, $outFile);
                if (@filesize($outFile) > self::MAX_OUTPUT_BYTES) {
                    self::killTree($st['pid']);
                    @proc_terminate($proc, 9);
                    usleep(30000);
                    $st2 = proc_get_status($proc);
                    $exitCode = $st2['exitcode'];
                    break;
                }
            }
            usleep(4000);
        }

        $elapsedMs = intval(round((microtime(true) - $start) * 1000));
        @proc_close($proc);

        $output = self::readLimited($outFile);
        $error = self::readLimited($errFile);

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
     * 递归结束进程及其子进程，避免超时后残留派生进程
     */
    protected static function killTree($pid)
    {
        $pid = intval($pid);
        if ($pid <= 0) {
            return;
        }
        if (DIRECTORY_SEPARATOR === '\\') {
            $fp = @popen('taskkill /F /T /PID ' . $pid . ' > NUL 2>&1', 'r');
            if ($fp) {
                @pclose($fp);
            }
            return;
        }
        $childrenFile = '/proc/' . $pid . '/task/' . $pid . '/children';
        if (is_file($childrenFile)) {
            $children = preg_split('/\s+/', trim((string) @file_get_contents($childrenFile)), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($children as $child) {
                self::killTree(intval($child));
            }
        }
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);
        }
    }

    /**
     * 读取输出文件，最多读取 MAX_OUTPUT_BYTES，防止巨型文件占满内存
     */
    protected static function readLimited($file)
    {
        if (!is_file($file)) {
            return '';
        }
        $size = @filesize($file);
        if ($size !== false && $size > self::MAX_OUTPUT_BYTES) {
            return (string) @file_get_contents($file, false, null, 0, self::MAX_OUTPUT_BYTES);
        }
        return (string) @file_get_contents($file);
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
     * 把编译器所在目录加入本进程 PATH
     * gcc 调用 as/ld、C++ 程序加载运行库都依赖同目录下的工具与 DLL
     */
    protected static function prependPath($bin)
    {
        $dir = dirname((string) $bin);
        if ($dir === '' || $dir === '.') {
            return;
        }
        $sep = DIRECTORY_SEPARATOR === '\\' ? ';' : ':';
        $path = getenv('PATH');
        if ($path === false || $path === null) {
            $path = '';
        }
        $parts = $path === '' ? [] : explode($sep, $path);
        if (in_array($dir, $parts, true)) {
            return;
        }
        // 部分面板环境会禁用 putenv，禁用后调用会直接抛错，这里做兼容处理
        if (function_exists('putenv')) {
            @putenv('PATH=' . $dir . ($path === '' ? '' : $sep . $path));
        }
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
            if ($name === 'node') {
                $dirs[] = 'C:\Program Files\nodejs';
                $dirs[] = 'C:\Program Files (x86)\nodejs';
            }
            if ($name === 'python' || $name === 'python3') {
                foreach ((array) glob('C:\Users\*\AppData\Local\Programs\Python\Python*') as $dir) {
                    $dirs[] = $dir;
                }
                foreach ((array) glob('C:\Program Files\Python*') as $dir) {
                    $dirs[] = $dir;
                }
                foreach ((array) glob('C:\Python*') as $dir) {
                    $dirs[] = $dir;
                }
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
