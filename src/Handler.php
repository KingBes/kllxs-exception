<?php

namespace Kllxs\Exception;

use Throwable;
use Webman\Exception\ExceptionHandler;
use Webman\Http\Request;
use Webman\Http\Response;

class Handler extends ExceptionHandler
{
    protected function getLinesAround(string $filename, int $lineNumber, int $range = 10): array
    {
        // 创建 SplFileObject 对象
        $file = new \SplFileObject($filename);
        if (!$file->isReadable()) {
            throw new \RuntimeException("Unable to read file: $filename");
        }

        // 初始化变量
        $startLine = max(1, $lineNumber - $range);  // 计算起始行号
        $endLine = $lineNumber + $range;            // 计算结束行号
        $lines = [];

        // 设置指针到文件开头
        $file->rewind();

        // 逐行读取文件
        while (!$file->eof()) {
            $currentLine = $file->key() + 1;  // 获取当前行号（SplFileObject 的 key 是从 0 开始的）
            $lineContent = $file->current();   // 获取当前行内容

            // 如果当前行在所需的范围内，则保存它
            if ($currentLine >= $startLine && $currentLine <= $endLine) {
                $lines[] = [
                    'line' => $currentLine,
                    'content' => $lineContent
                ];
            }

            // 移动到下一行
            $file->next();

            // 如果已经过了所需范围的最后一行，则可以停止读取
            if ($currentLine > $endLine) {
                break;
            }
        }

        // 返回结果
        return $lines;
    }

    public function render(Request $request, Throwable $exception): Response
    {
        /* var_dump($exception->getFile());
        var_dump($exception->getLine()); */
        // var_dump($exception->getTrace());
        $code = $exception->getCode();
        if ($request->expectsJson() || !config("plugin.kllxs.exception.app.enable")) {
            return parent::render($request, $exception);
        }

        if (!$this->debug) {
            return new Response($code ?: 500, [], 'Server internal error');
        } else {
            $tpl = config("plugin.kllxs.exception.app.template");
            $getLinesAround = $this->getLinesAround(
                $exception->getFile(),
                $exception->getLine()
            );
            $error_code = "";
            foreach ($getLinesAround as $k => $val) {
                $then = "";
                if ($val["line"] == $exception->getLine()) {
                    $then = "this";
                }
                $error_code .= "<div class='" . $then . "'> <div class='line '>" . $val["line"] . ".</div> <pre class='content'>" . $val["content"] . "</pre></div>";
            }

            $vars = [
                "error_file" => $exception->getFile() . "  line:" . $exception->getLine(),
                "error_msg" => $exception->getMessage(),
                "error_code" => $error_code
            ];
            return $this->response($tpl, $vars);
        }
    }

    protected function response(string $path, array $vars, int $code = 500): Response
    {
        extract($vars);
        ob_start();
        try {
            include $path;
        } catch (Throwable $e) {
            // ob_end_clean();
            throw $e;
        }
        $body = ob_get_clean();
        // var_dump($body);
        return new Response($code, ['Content-Type' => 'text/html'], $body);
    }
}
