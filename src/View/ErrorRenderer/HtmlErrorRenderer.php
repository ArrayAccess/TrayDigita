<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\ErrorRenderer;

use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function get_class;
use function htmlentities;
use function sprintf;

/**
 * @deprecated
 */
class HtmlErrorRenderer extends AbstractErrorRenderer
{
    public function renderHtmlBody(string $title = '', string $html = ''): string
    {
        $title = htmlentities($title);
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>$title</title>
    <style>
body {
    padding:0;
    margin:0;
    color: #333;
    font-size: 14px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial,
     "Noto Sans", "Liberation Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji",
     "Segoe UI Symbol", "Noto Color Emoji";
}
div.e-details p > span:first-child {
    min-width: 100px;
    display: inline-block;
}
h2, h1 {
    margin-top: 1em;
    margin-bottom: 0.5rem;
    font-weight: 500;
    line-height: 1.2;
}
h1 {
    font-size: 3em;
}
h3 {
    font-size: 1.4em;
    margin-bottom: .3em;
}
pre {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: .9em;
    padding: 1rem;
    overflow: auto;
    margin: 0 auto;
    background: #f1f1f1;
    border-left: 3px solid;
    max-height: 90vh;
    min-height: 200px;
}
.wrap {
    width: 800px;
    max-width: 90%;
    margin: 0 auto;
}
</style>
</head>
<body>
    <div class="wrap">
        <h1>$title</h1>
        <div>$html</div>
    </div>
</body>
</html>
HTML;
    }

    public function formatException(Throwable $exception) : string
    {
        $className = get_class($exception);
        $message = htmlentities($exception->getMessage());
        return <<<HTML
<div class="e-details">
    <p class="e-class">
        <span><strong>Type :</strong></span>
        <span>$className</span>
    </p>
    <p class="e-code">
        <span><strong>Code :</strong></span>
        <span>{$exception->getCode()}</span>
    </p>
    <p class="e-message">
        <span><strong>Message :</strong></span>
        <span>$message</span>
    </p>
    <p class="e-file">
        <span><strong>File :</strong></span>
        <span>{$exception->getFile()}</span>
    </p>
    <p class="e-line">
        <span><strong>Line :</strong></span>
        <span>{$exception->getLine()}</span>
    </p>
    <h2>Trace</h2>
    <pre>{$exception->getTraceAsString()}</pre>
</div>
HTML;
    }

    protected function format(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ): string {
        $view = ContainerHelper::use(ViewInterface::class, $this->getContainer());
        if ($view) {
            $code = $exception instanceof HttpException
                ? $exception->getCode()
                : 500;
            /**
             * @var ViewInterface $view
             */
            $path = "errors/$code";
            if ($view->exist($path)) {
                return $view->render(
                    $path,
                    [
                        'exception' => $exception,
                        'displayErrorDetails' => $displayErrorDetails
                    ]
                );
            }
        }

        if (!$displayErrorDetails) {
            $html = sprintf('<p>%s</p>', $this->getErrorDescription($exception));
        } else {
            $html = $this->formatException($exception);
        }
        return $this->renderHtmlBody(
            $this->getErrorTitle($exception),
            $html
        );
    }
}
