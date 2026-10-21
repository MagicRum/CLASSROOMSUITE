<?php
// +----------------------------------------------------------------------
// | 在线判题 - 题目管理
// +----------------------------------------------------------------------

namespace app\controller\admin;

use app\service\JudgeService;
use think\facade\Db;

class OjProblem extends Base
{
    public function index()
    {
        $categoryId = intval(input('category_id', 0));
        $difficulty = intval(input('difficulty', 0));
        $status = input('status', '');
        $keyword = trim(input('keyword', ''));

        $query = Db::name('oj_problems');
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        if ($difficulty) {
            $query->where('difficulty', $difficulty);
        }
        if ($status !== '') {
            $query->where('is_public', intval($status));
        }
        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        $list = $query->order('sort', 'asc')->order('id', 'desc')->paginate([
            'list_rows' => 20,
            'query'     => request()->param(),
        ]);

        $categories = Db::name('oj_categories')->order('sort', 'asc')->select();
        $categoryMap = [0 => '未分类'];
        foreach ($categories as $c) {
            $categoryMap[intval($c['id'])] = $c['name'];
        }

        $caseCounts = [];
        foreach (Db::name('oj_test_cases')->field('problem_id, COUNT(*) AS cnt')->group('problem_id')->select() as $row) {
            $caseCounts[intval($row['problem_id'])] = intval($row['cnt']);
        }

        $subStats = [];
        $rows = Db::name('oj_submissions')
            ->where('is_test', 0)
            ->field("problem_id, COUNT(*) AS total, SUM(CASE WHEN status='ac' THEN 1 ELSE 0 END) AS ac_count")
            ->group('problem_id')
            ->select();
        foreach ($rows as $row) {
            $subStats[intval($row['problem_id'])] = [
                'total' => intval($row['total']),
                'ac'    => intval($row['ac_count']),
            ];
        }
        foreach ($list as $item) {
            $pid = intval($item['id']);
            if (!isset($caseCounts[$pid])) {
                $caseCounts[$pid] = 0;
            }
            if (!isset($subStats[$pid])) {
                $subStats[$pid] = ['total' => 0, 'ac' => 0];
            }
        }

        return view('admin/oj/problem/index', [
            'list'          => $list,
            'categories'    => $categories,
            'categoryMap'   => $categoryMap,
            'caseCounts'    => $caseCounts,
            'subStats'      => $subStats,
            'difficultyMap' => JudgeService::$difficultyMap,
            'languageMap'   => JudgeService::$languages,
            'category_id'   => $categoryId,
            'difficulty'    => $difficulty,
            'status'        => $status,
            'keyword'       => $keyword,
        ]);
    }

    public function edit()
    {
        $id = intval(input('id', 0));
        $problem = $id ? Db::name('oj_problems')->where('id', $id)->find() : null;
        if ($id && !$problem) {
            return redirect('/admin/oj/problem');
        }
        if (!$problem) {
            $problem = [
                'id'              => 0,
                'category_id'     => 0,
                'title'           => '',
                'description'     => '',
                'input_desc'      => '',
                'output_desc'     => '',
                'sample_input'    => '',
                'sample_output'   => '',
                'hint'            => '',
                'difficulty'      => 2,
                'tags'            => '',
                'languages'       => 'c,cpp,python,js',
                'time_limit'      => 1000,
                'memory_limit'    => 262144,
                'total_score'     => 100,
                'score_mode'      => 'case',
                'ignore_trailing' => 1,
                'show_detail'     => 0,
                'is_public'       => 1,
                'sort'            => 0,
            ];
        }

        $cases = [];
        if ($id) {
            $cases = Db::name('oj_test_cases')
                ->where('problem_id', $id)
                ->order('type', 'asc')
                ->order('sort', 'asc')
                ->order('id', 'asc')
                ->select();
            $cases = is_array($cases) ? $cases : $cases->toArray();
        }

        $categories = Db::name('oj_categories')->order('sort', 'asc')->select();

        return view('admin/oj/problem/edit', [
            'problem'       => $problem,
            'cases'         => $cases,
            'casesJson'     => json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'descriptionJson' => json_encode((string) $problem['description'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'selectedLanguages' => explode(',', (string) $problem['languages']),
            'categories'    => $categories,
            'difficultyMap' => JudgeService::$difficultyMap,
            'languageMap'   => JudgeService::$languages,
        ]);
    }

    public function save()
    {
        $id = intval(input('post.id', 0));
        $title = trim(input('post.title', ''));
        if ($title === '') {
            return $this->error('题目标题不能为空');
        }

        $supported = array_keys(JudgeService::$languages);
        $languages = input('post.languages/a', []);
        $languages = array_values(array_intersect($supported, is_array($languages) ? $languages : []));
        if (!$languages) {
            return $this->error('请至少勾选一种支持的编程语言');
        }

        $scoreMode = input('post.score_mode', 'case');
        if (!in_array($scoreMode, ['all', 'case'], true)) {
            $scoreMode = 'case';
        }

        $data = [
            'category_id'     => intval(input('post.category_id', 0)),
            'title'           => $title,
            'description'     => isset($_POST['description']) ? (string) $_POST['description'] : '',
            'input_desc'      => isset($_POST['input_desc']) ? (string) $_POST['input_desc'] : '',
            'output_desc'     => isset($_POST['output_desc']) ? (string) $_POST['output_desc'] : '',
            'sample_input'    => isset($_POST['sample_input']) ? (string) $_POST['sample_input'] : '',
            'sample_output'   => isset($_POST['sample_output']) ? (string) $_POST['sample_output'] : '',
            'hint'            => isset($_POST['hint']) ? (string) $_POST['hint'] : '',
            'difficulty'      => max(1, min(3, intval(input('post.difficulty', 2)))),
            'tags'            => trim(input('post.tags', '')),
            'languages'       => implode(',', $languages),
            'time_limit'      => max(100, min(60000, intval(input('post.time_limit', 1000)))),
            'memory_limit'    => max(1024, min(1048576, intval(input('post.memory_limit', 262144)))),
            'total_score'     => max(0, min(1000, floatval(input('post.total_score', 100)))),
            'score_mode'      => $scoreMode,
            'ignore_trailing' => intval(input('post.ignore_trailing', 0)) ? 1 : 0,
            'show_detail'     => intval(input('post.show_detail', 0)) ? 1 : 0,
            'is_public'       => intval(input('post.is_public', 1)) ? 1 : 0,
            'sort'            => intval(input('post.sort', 0)),
            'update_time'     => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            Db::name('oj_problems')->where('id', $id)->update($data);
            return $this->success('保存成功', ['id' => $id], '/admin/oj/problem/edit?id=' . $id);
        }

        $data['create_time'] = date('Y-m-d H:i:s');
        $newId = Db::name('oj_problems')->insertGetId($data);
        return $this->success('创建成功，请继续配置测试用例', ['id' => $newId], '/admin/oj/problem/edit?id=' . $newId);
    }

    public function delete()
    {
        $id = intval(input('post.id', 0));
        if (!$id) {
            return $this->error('参数错误');
        }
        $used = Db::name('oj_submissions')->where('problem_id', $id)->where('is_test', 0)->count();
        if ($used > 0) {
            return $this->error('该题目已有学生提交记录，建议下架而不是删除');
        }
        Db::name('oj_test_cases')->where('problem_id', $id)->delete();
        Db::name('oj_submissions')->where('problem_id', $id)->delete();
        Db::name('oj_problems')->where('id', $id)->delete();
        return $this->success('删除成功');
    }

    public function copy()
    {
        $id = intval(input('post.id', 0));
        $problem = Db::name('oj_problems')->where('id', $id)->find();
        if (!$problem) {
            return $this->error('题目不存在');
        }

        $data = $problem;
        unset($data['id']);
        $data['title'] = $problem['title'] . '（副本）';
        $data['is_public'] = 0;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['update_time'] = date('Y-m-d H:i:s');
        $newId = Db::name('oj_problems')->insertGetId($data);

        $cases = Db::name('oj_test_cases')->where('problem_id', $id)->select();
        foreach ($cases as $case) {
            $row = $case;
            unset($row['id']);
            $row['problem_id'] = $newId;
            Db::name('oj_test_cases')->insert($row);
        }

        return $this->success('复制成功', ['id' => $newId], '/admin/oj/problem/edit?id=' . $newId);
    }

    public function toggle()
    {
        $id = intval(input('post.id', 0));
        $isPublic = intval(input('post.is_public', 0)) ? 1 : 0;
        if (!$id) {
            return $this->error('参数错误');
        }
        Db::name('oj_problems')->where('id', $id)->update([
            'is_public'   => $isPublic,
            'update_time' => date('Y-m-d H:i:s'),
        ]);
        return $this->success($isPublic ? '已上架' : '已下架');
    }

    /**
     * 题干图片上传（Quill 编辑器）
     */
    public function upload()
    {
        $file = request()->file('image');
        if (!$file) {
            $file = request()->file('file');
        }
        if (!$file) {
            return json(['code' => 0, 'msg' => '请选择图片']);
        }

        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return json(['code' => 0, 'msg' => '仅支持 jpg/png/gif/webp 图片']);
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return json(['code' => 0, 'msg' => '图片不能超过 5MB']);
        }

        $relative = 'uploads/oj/' . date('Ym') . '/';
        $dir = public_path() . $relative;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $name = date('dHis') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
        $file->move($dir, $name);

        return json(['code' => 1, 'msg' => '上传成功', 'url' => '/' . $relative . $name]);
    }
}
