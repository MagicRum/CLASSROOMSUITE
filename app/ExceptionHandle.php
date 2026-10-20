<?php
// +----------------------------------------------------------------------
// | 全局异常处理（简洁友好）
// +----------------------------------------------------------------------

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;

class ExceptionHandle extends Handle
{
    public function render($request, \Throwable $e): Response
    {
        // AJAX/API 请求返回 JSON
        if ($request->isAjax() || $request->isJson()) {
            $code = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return json([
                'code' => $code,
                'msg'  => env('app_debug', false) ? $e->getMessage() : '系统繁忙，请稍后再试',
            ]);
        }
        return parent::render($request, $e);
    }
}
