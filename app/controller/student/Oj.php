<?php
// +----------------------------------------------------------------------
// | 学生端 - 在线判题
// +----------------------------------------------------------------------

namespace app\controller\student;

use app\service\JudgeService;
use think\facade\Db;

class Oj
{
    protected function student()
    {
        return $_SESSION['student_user'] ?? [];
    }

    /**
     * 题目列表
     */
    public function index()
    {
        $student = $this->student();
        $keyword = trim(input('keyword', ''));
        $difficulty = intval(input('difficulty', 0));
        $categoryId = intval(input('category_id', 0));

        $query = Db::name('oj_problems')->where('is_public', 1);
        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }
        if ($difficulty) {
            $query->where('difficulty', $difficulty);
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        $list = $query->order('sort', 'asc')->order('id', 'asc')->paginate([
            'list_rows' => 20,
            'query'     => request()->param(),
        ]);

        $state = [];
        if (!empty($student['id'])) {
            $rows = Db::name('oj_submissions')
                ->where('student_id', $student['id'])
                ->where('is_test', 0)
                ->field("problem_id, MAX(score) AS best, COUNT(*) AS cnt, SUM(CASE WHEN status='ac' THEN 1 ELSE 0 END) AS ac")
                ->group('problem_id')
                ->select();
            foreach ($rows as $row) {
                $state[intval($row['problem_id'])] = [
                    'best' => floatval($row['best']),
                    'cnt'  => intval($row['cnt']),
                    'ac'   => intval($row['ac']) > 0,
                ];
            }
        }

        $categories = Db::name('oj_categories')->order('sort', 'asc')->select();

        return view('student/oj/index', [
            'list'          => $list,
            'state'         => $state,
            'categories'    => $categories,
            'difficultyMap' => JudgeService::$difficultyMap,
            'keyword'       => $keyword,
            'difficulty'    => $difficulty,
            'category_id'   => $categoryId,
        ]);
    }

    /**
     * 题目详情
     */
    public function problem()
    {
        $id = intval(input('id', 0));
        $problem = Db::name('oj_problems')->where('id', $id)->where('is_public', 1)->find();
        if (!$problem) {
            return redirect('/oj');
        }

        $samples = Db::name('oj_test_cases')
            ->where('problem_id', $id)
            ->where('type', 1)
            ->where('enabled', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();
        $samples = is_array($samples) ? $samples : $samples->toArray();

        // 未配置测试用例时，用题目公开样例兜底展示
        if (!$samples) {
            $sampleInput = (string) ($problem['sample_input'] ?? '');
            $sampleOutput = (string) ($problem['sample_output'] ?? '');
            if (trim($sampleInput) !== '' || trim($sampleOutput) !== '') {
                $samples = [[
                    'name'   => '样例',
                    'input'  => $sampleInput,
                    'output' => $sampleOutput,
                ]];
            }
        }

        $allowed = array_values(array_filter(explode(',', $problem['languages']), function ($lang) {
            return isset(JudgeService::$languages[$lang]);
        }));
        $binaries = JudgeService::detectBinaries();
        $available = [];
        foreach ($allowed as $lang) {
            $available[$lang] = !empty($binaries[$lang]);
        }

        $student = $this->student();
        $best = 0;
        $myCount = 0;
        if (!empty($student['id'])) {
            $best = floatval(Db::name('oj_submissions')
                ->where('student_id', $student['id'])
                ->where('problem_id', $id)
                ->where('is_test', 0)
                ->max('score'));
            $myCount = Db::name('oj_submissions')
                ->where('student_id', $student['id'])
                ->where('problem_id', $id)
                ->where('is_test', 0)
                ->count();
        }

        return view('student/oj/problem', [
            'problem'       => $problem,
            'samples'       => $samples,
            'allowed'       => $allowed,
            'available'     => $available,
            'languageMap'   => JudgeService::$languages,
            'difficultyMap' => JudgeService::$difficultyMap,
            'best'          => $best,
            'myCount'       => $myCount,
        ]);
    }

    /**
     * 正式提交
     */
    public function submit()
    {
        return $this->createSubmission(0);
    }

    /**
     * 样例自测（只跑公开样例，不计成绩）
     */
    public function run()
    {
        return $this->createSubmission(2);
    }

    /**
     * 语法检查（只编译，不运行用例）
     */
    public function check()
    {
        $student = $this->student();
        if (empty($student['id'])) {
            return json(['code' => 401, 'msg' => '请先登录']);
        }

        $problemId = intval(input('post.problem_id', 0));
        $problem = Db::name('oj_problems')->where('id', $problemId)->where('is_public', 1)->find();
        if (!$problem) {
            return json(['code' => 0, 'msg' => '题目不存在或已下架']);
        }

        $language = (string) input('post.language', '');
        $allowed = explode(',', $problem['languages']);
        if (!in_array($language, $allowed, true) || !isset(JudgeService::$languages[$language])) {
            return json(['code' => 0, 'msg' => '该题目不支持所选语言']);
        }

        $code = isset($_POST['code']) ? (string) $_POST['code'] : '';
        $res = JudgeService::syntaxCheck($language, $code);
        return json(['code' => 1, 'data' => ['ok' => $res['ok'], 'log' => $res['log']]]);
    }

    protected function createSubmission($isTest)
    {
        $student = $this->student();
        if (empty($student['id'])) {
            return json(['code' => 401, 'msg' => '请先登录']);
        }

        $problemId = intval(input('post.problem_id', 0));
        $problem = Db::name('oj_problems')->where('id', $problemId)->where('is_public', 1)->find();
        if (!$problem) {
            return json(['code' => 0, 'msg' => '题目不存在或已下架']);
        }

        $language = (string) input('post.language', '');
        $allowed = explode(',', $problem['languages']);
        if (!in_array($language, $allowed, true) || !isset(JudgeService::$languages[$language])) {
            return json(['code' => 0, 'msg' => '该题目不支持所选语言']);
        }

        $code = isset($_POST['code']) ? (string) $_POST['code'] : '';
        if (trim($code) === '') {
            return json(['code' => 0, 'msg' => '代码不能为空']);
        }
        if (strlen($code) > 65535) {
            return json(['code' => 0, 'msg' => '代码过长，请精简后提交']);
        }

        if ($isTest === 2) {
            $hasSample = Db::name('oj_test_cases')
                ->where('problem_id', $problemId)
                ->where('type', 1)
                ->where('enabled', 1)
                ->count();
            $hasSampleText = trim((string) ($problem['sample_input'] ?? '')) !== ''
                || trim((string) ($problem['sample_output'] ?? '')) !== '';
            if (!$hasSample && !$hasSampleText) {
                return json(['code' => 0, 'msg' => '该题目暂未配置公开样例']);
            }
        }

        $id = Db::name('oj_submissions')->insertGetId([
            'problem_id'   => $problemId,
            'student_id'   => intval($student['id']),
            'student_no'   => $student['student_no'],
            'student_name' => $student['name'],
            'class_id'     => intval($student['class_id']),
            'language'     => $language,
            'code'         => $code,
            'status'       => 'pending',
            'is_test'      => $isTest,
            'ip'           => request()->ip(),
            'create_time'  => date('Y-m-d H:i:s'),
        ]);

        JudgeService::spawnWorker();

        return json(['code' => 1, 'msg' => '已提交判题', 'data' => ['id' => $id]]);
    }

    /**
     * 判题状态轮询
     */
    public function status()
    {
        $student = $this->student();
        $id = intval(input('id', 0));
        $sub = Db::name('oj_submissions')->where('id', $id)->find();
        if (!$sub || intval($sub['student_id']) !== intval($student['id'] ?? 0)) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        if ($sub['status'] === 'pending' && strtotime($sub['create_time']) < time() - 3) {
            JudgeService::spawnWorker();
        }

        $cases = $sub['result_json'] ? json_decode($sub['result_json'], true) : [];
        return json([
            'code' => 1,
            'data' => [
                'id'          => intval($sub['id']),
                'status'      => $sub['status'],
                'status_text' => JudgeService::statusText($sub['status']),
                'score'       => floatval($sub['score']),
                'time_used'   => intval($sub['time_used']),
                'memory_used' => intval($sub['memory_used']),
                'compile_log' => (string) $sub['compile_log'],
                'cases'       => is_array($cases) ? $cases : [],
                'is_test'     => intval($sub['is_test']),
            ],
        ]);
    }

    /**
     * 我的提交记录
     */
    public function records()
    {
        $student = $this->student();
        $list = Db::name('oj_submissions')
            ->where('student_id', $student['id'])
            ->where('is_test', 0)
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => 20,
                'query'     => request()->param(),
            ]);

        $problemMap = [];
        foreach (Db::name('oj_problems')->field('id,title')->select() as $p) {
            $problemMap[intval($p['id'])] = $p['title'];
        }

        $summary = Db::name('oj_submissions')
            ->where('student_id', $student['id'])
            ->where('is_test', 0)
            ->field("COUNT(*) AS total, SUM(CASE WHEN status='ac' THEN 1 ELSE 0 END) AS ac_count, COUNT(DISTINCT CASE WHEN status='ac' THEN problem_id END) AS solved")
            ->find();

        return view('student/oj/records', [
            'list'        => $list,
            'problemMap'  => $problemMap,
            'statusMap'   => JudgeService::$statusMap,
            'languageMap' => JudgeService::$languages,
            'summary'     => $summary ?: ['total' => 0, 'ac_count' => 0, 'solved' => 0],
        ]);
    }
}
