<?php
// +----------------------------------------------------------------------
// | 在线判题 - 测试用例
// +----------------------------------------------------------------------

namespace app\controller\admin;

use think\facade\Db;

class OjCase extends Base
{
    public function save()
    {
        $problemId = intval(input('post.problem_id', 0));
        if (!$problemId || !Db::name('oj_problems')->where('id', $problemId)->find()) {
            return $this->error('题目不存在');
        }

        $id = intval(input('post.id', 0));
        $type = intval(input('post.type', 2)) === 1 ? 1 : 2;
        $data = [
            'problem_id' => $problemId,
            'name'       => trim(input('post.name', '')),
            'input'      => isset($_POST['input']) ? (string) $_POST['input'] : '',
            'output'     => isset($_POST['output']) ? (string) $_POST['output'] : '',
            'score'      => max(0, floatval(input('post.score', 0))),
            'type'       => $type,
            'enabled'    => intval(input('post.enabled', 1)) ? 1 : 0,
            'sort'       => intval(input('post.sort', 0)),
        ];
        if ($data['name'] === '') {
            $data['name'] = $type === 1 ? '样例' : '测试点';
        }

        if ($id) {
            Db::name('oj_test_cases')->where('id', $id)->where('problem_id', $problemId)->update($data);
            return $this->success('用例已更新');
        }

        $data['create_time'] = date('Y-m-d H:i:s');
        $newId = Db::name('oj_test_cases')->insertGetId($data);
        return $this->success('用例已添加', ['id' => $newId]);
    }

    public function delete()
    {
        $id = intval(input('post.id', 0));
        if (!$id) {
            return $this->error('参数错误');
        }
        Db::name('oj_test_cases')->where('id', $id)->delete();
        return $this->success('删除成功');
    }

    public function toggle()
    {
        $id = intval(input('post.id', 0));
        if (!$id) {
            return $this->error('参数错误');
        }
        $enabled = intval(input('post.enabled', 0)) ? 1 : 0;
        Db::name('oj_test_cases')->where('id', $id)->update(['enabled' => $enabled]);
        return $this->success($enabled ? '已启用' : '已禁用');
    }

    /**
     * 批量上传用例：支持多个 .in/.out 文件，或单个 zip 压缩包
     */
    public function upload()
    {
        $problemId = intval(input('post.problem_id', 0));
        if (!$problemId || !Db::name('oj_problems')->where('id', $problemId)->find()) {
            return $this->error('题目不存在');
        }

        $type = intval(input('post.type', 2)) === 1 ? 1 : 2;
        $files = request()->file('files');
        if (!$files) {
            $files = request()->file('file');
        }
        if (!$files) {
            return $this->error('请选择用例文件');
        }
        if (!is_array($files)) {
            $files = [$files];
        }

        $pairs = [];
        $errors = [];
        $tempDir = '';

        // 单个 zip：解压后按同名 .in/.out 配对
        if (count($files) === 1 && strtolower($files[0]->getOriginalExtension()) === 'zip') {
            if (!class_exists('ZipArchive')) {
                return $this->error('服务器未启用 zip 扩展，请改用多个 .in/.out 文件上传');
            }
            $tempDir = runtime_path() . 'oj' . DIRECTORY_SEPARATOR . 'upload_' . uniqid();
            @mkdir($tempDir, 0777, true);
            $zip = new \ZipArchive();
            if ($zip->open($files[0]->getPathname()) !== true) {
                return $this->error('压缩包解析失败');
            }
            $zip->extractTo($tempDir);
            $zip->close();

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $ext = strtolower($item->getExtension());
                if (!in_array($ext, ['in', 'out'], true)) {
                    continue;
                }
                $base = $item->getBasename('.' . $ext);
                if (!isset($pairs[$base])) {
                    $pairs[$base] = [];
                }
                $pairs[$base][$ext] = $item->getPathname();
            }
        } else {
            foreach ($files as $file) {
                $ext = strtolower($file->getOriginalExtension());
                if (!in_array($ext, ['in', 'out'], true)) {
                    $errors[] = $file->getOriginalName() . '：仅支持 .in/.out 文件';
                    continue;
                }
                if ($file->getSize() > 5 * 1024 * 1024) {
                    $errors[] = $file->getOriginalName() . '：文件超过 5MB';
                    continue;
                }
                $base = pathinfo($file->getOriginalName(), PATHINFO_FILENAME);
                if (!isset($pairs[$base])) {
                    $pairs[$base] = [];
                }
                $pairs[$base][$ext] = $file->getPathname();
            }
        }

        $sort = intval(Db::name('oj_test_cases')->where('problem_id', $problemId)->max('sort'));
        $success = 0;
        foreach ($pairs as $base => $pair) {
            if (!isset($pair['in']) || !isset($pair['out'])) {
                $errors[] = $base . '：缺少配对的 .in 或 .out（已收到 ' . implode('/', array_keys($pair)) . '）';
                continue;
            }
            Db::name('oj_test_cases')->insert([
                'problem_id'  => $problemId,
                'name'        => $base,
                'input'       => (string) file_get_contents($pair['in']),
                'output'      => (string) file_get_contents($pair['out']),
                'score'       => 0,
                'type'        => $type,
                'enabled'     => 1,
                'sort'        => ++$sort,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
            $success++;
        }

        if ($tempDir !== '') {
            $this->removeDir($tempDir);
        }

        if ($success === 0) {
            return $this->error('没有导入任何用例。' . implode('；', array_slice($errors, 0, 3)));
        }

        $msg = "成功导入 {$success} 组用例";
        if ($errors) {
            $msg .= '，' . count($errors) . ' 组被跳过：' . implode('；', array_slice($errors, 0, 3));
        }
        return $this->success($msg);
    }

    protected function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
