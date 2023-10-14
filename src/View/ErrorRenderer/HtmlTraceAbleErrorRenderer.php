<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\ErrorRenderer;

use ArrayAccess\TrayDigita\Exceptions\Runtime\MaximumCallstackExceeded;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Composer\Autoload\ClassLoader;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use SplFileInfo;
use Throwable;
use function defined;
use function dirname;
use function explode;
use function file_exists;
use function get_class;
use function highlight_string;
use function htmlentities;
use function htmlspecialchars;
use function implode;
use function is_string;
use function json_encode;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function realpath;
use function sprintf;
use function str_replace;
use function trim;
use const CONFIG_FILE;
use const JSON_UNESCAPED_SLASHES;

class HtmlTraceAbleErrorRenderer extends AbstractErrorRenderer
{
    protected ?string $rootDirectory = null;

    protected function format(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ): ?string {
        if ($displayErrorDetails) {
            try {
                $kernel = ContainerHelper::use(KernelInterface::class, $this->getContainer());
                $root = $kernel?->getRootDirectory();
                if (!$root) {
                    $ref = new ReflectionClass(ClassLoader::class);
                    $this->rootDirectory = dirname($ref->getFileName(), 3);
                } else {
                    $this->rootDirectory = $root;
                }
            } catch (Throwable) {
                $serverParams = $request->getServerParams();
                $this->rootDirectory = dirname(
                    $serverParams['DOCUMENT_ROOT'] ?? dirname($serverParams['SCRIPT_FILENAME'])
                );
            }
            return $this->exceptionDetail($exception);
        }

        $container = $this->getContainer();
        $view = ContainerHelper::use(ViewInterface::class, $container);
        if ($view) {
            $code = 500;
            if ($exception instanceof HttpException) {
                $code = $exception->getCode();
                $view->setParameter('title', $exception->getTitle());
            }

            /**
             * @var ViewInterface $view
             */
            $path = "errors/$code";
            if ($view->exist($path)) {
                return $view->render(
                    $path,
                    [
                        'exception' => $exception
                    ]
                );
            }
        }
        $html = sprintf('<p>%s</p>', $this->getErrorDescription($exception));
        return $this->renderHtmlBody(
            $this->getErrorTitle($exception),
            $html
        );
    }

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

    private function fromTrace(array $trace, &$traceCount) : array
    {
        $traceCount++;
        $contentOffset = 10;
        // $startLine = null;
        $theLine = '';
        $insideContent = '';
        $baseFile = $this->rootDirectory ? preg_replace(
            '~^'.preg_quote($this->rootDirectory, '~').'[\\\/]~',
            '',
            $trace['file']
        ) : $trace['file'];

        $mainErrorInfo = '';
        if (isset($trace['function'])) {
            $mainErrorInfo  .= sprintf('<div class="internal" data-increment="%d">', $traceCount);
            $mainErrorInfo .= '<code>';
            $mainErrorInfo .= sprintf(
                '<span style="color: #007700">[%s:%d]: </span>',
                htmlentities($baseFile),
                $trace['line']
            );
            $mainErrorInfo .= sprintf(
                '%s%s%s<span style="color: #007700">()</span>',
                isset($trace['class'])
                    ? sprintf('<span style="color: #0000BB">%s</span>', htmlentities($trace['class']))
                    : '',
                isset($trace['type'])
                    ? sprintf('<span style="color: #007700">%s</span>', htmlentities($trace['type']))
                    : '',
                sprintf('<span style="color: #0000BB">%s</span>', htmlentities($trace['function'])),
            );
            $mainErrorInfo .= '</code>';
            $mainErrorInfo .= '</div>';
        }

        // tab
        $tabNavigation  = sprintf(
            '<div class="trace-tab-nav" data-id="%d" title="%s">',
            $traceCount,
            htmlspecialchars(
                sprintf(
                    'Source of: %s in file %s at line %d',
                    $trace['class']??$trace['function']??'',
                    htmlentities($baseFile),
                    $trace['line']
                )
            )
        );
        $tabNavigation .= sprintf(
            '<span class="trace-class">%s</span>',
            htmlentities($trace['class']??$trace['function']??'')
        );
        $tabNavigation .= sprintf(
            '<span class="trace-file"><span>%s</span><span>:</span><span>%d</span></span>',
            htmlentities($baseFile),
            $trace['line']
        );

        $theLineContent  = '';
        $tabNavigation  .= '</div>';
        $spl = (new SplFileInfo($trace['file']))->openFile();
        $line = 0;
        while (!$spl->eof()) {
            $line++;
            $lineContent = $spl->getCurrentLine();
            $spl->next();
            if (($line+$contentOffset) < $trace['line']) {
                continue;
            }
            if (($line-$contentOffset) > $trace['line']) {
                break;
            }
            $insideContent .= "\n$lineContent";
            // $startLine ??= $line;
            if (trim($lineContent) !== '') {
                $lineContent = explode(
                    '<br>',
                    str_replace(
                        ['<br />', '<br/>'],
                        '<br>',
                        highlight_string("<?php\n".$lineContent, true)
                    ),
                    2
                );
                $lineContent[0]= str_replace('&lt;?php', '', $lineContent[0]);
                $lineContent = implode($lineContent);
            }
            $theLine .= sprintf(
                '<span data-line="%2$d" %1$s>%2$d</span>',
                $line === $trace['line'] ? ' class="current"' : '',
                $line
            );
            if (trim($lineContent) === '') {
                $lineContent = '<br>';
            }
            $theLineContent .= sprintf(
                '<div data-line="%d" %s>%s</div>',
                $line,
                $line === $trace['line'] ? ' class="current"' : '',
                $lineContent
            );
        }

        if (preg_match('~<span\s+style="color:\s*#FF8000">(?:&nbsp;)*/[*]{2}~', $theLineContent)) {
            // fix invalid color from orange to green
            preg_match(
                '~<span\s+style="color:\s*(#[A-F0-9]{6})">(?:&nbsp;)*[*]~',
                $theLineContent,
                $match
            );
            if (!empty($match[1])) {
                $theLineContent = preg_replace(
                    '~(<span\s+style="color:\s*)#FF8000(">(?:&nbsp;)*/[*]{2})~',
                    '$1'.$match[1].'$2',
                    $theLineContent
                );
            }
        }

        $currentTrace = $theLineContent;
        unset($theLineContent);

        $editorContent  = sprintf(
            '<div class="trace-section" data-id="%d">',
            $traceCount
        );
        $editorContent .= sprintf(
            '<div class="trace-content-file">%s</div>',
            htmlentities($baseFile)
        );
        $editorContent .= sprintf(
            '<div class="trace-content-wrapper">%s%s</div>',
            sprintf(
                '<div class="traced-content-line">%s</div>',
                $theLine
            ),
            sprintf(
                '<div class="traced-content-details" data-content="%s">%s</div>',
                htmlspecialchars(json_encode($insideContent, JSON_UNESCAPED_SLASHES)),
                $currentTrace
            )
        );
        $editorContent .= '</div>';
        return [$mainErrorInfo, $tabNavigation, $editorContent, $line, $traceCount];
    }

    private function exceptionDetail(Throwable $exception): string
    {
        // tab
        $tabNavigation = '';
        // editor
        $editorContent = '';
        $errorLineEditor = '';
        $countLine = 1;
        $traceCount = 0;
        $errorFile = $this->rootDirectory ? preg_replace(
            '~^'.preg_quote($this->rootDirectory, '~').'[\\\/]~',
            '',
            $exception->getFile()
        ) : $exception->getFile();

        for (; $countLine <= 5; $countLine++) {
            if ($countLine === 5) {
                $errorLineEditor .= "<span>$countLine</span>";
                continue;
            }
            $errorLineEditor .= "<span class='current'>$countLine</span>";
        }
        $mainErrorInfo = sprintf(
            '<div class="current"><strong>File</strong>: <code>%s</code></div>',
            htmlentities($errorFile)
        );
        $mainErrorInfo .= sprintf(
            "<div class='current'><strong>Code</strong>: <code>%d</code></div>\n",
            $exception->getCode()
        );
        $mainErrorInfo .= sprintf(
            "<div class='current'><strong>Line</strong>: <code>%d</code></div>\n",
            $exception->getLine()
        );
        $mainErrorInfo .= sprintf(
            "<div class='current'><strong>Message</strong>: <code>%s</code></div>\n",
            htmlentities($exception->getMessage())
        );
        $mainErrorInfo .= '<div><br></div>';

        // tab
        $tabNavigationTop = '<div class="trace-tab-nav active" data-id="0">';
        $tabNavigationTop .= '<span class="trace-class">Error Details</span>';
        $tabNavigationTop .= sprintf('<span class="trace-class">%s</span>', get_class($exception));
        $tabNavigationTop .= sprintf('<span class="trace-file">%s</span>', htmlentities($exception->getMessage()));
        $tabNavigationTop .= '</div>';

        $exists = false;
        foreach ($exception->getTrace() as $trace) {
            if (!isset($trace['file'], $trace['line'])) {
                continue;
            }
            $exists = true;
            break;
        }
        // build first
        if (!$exists && file_exists($exception->getFile())) {
            [
                $_mainErrorInfo,
                $_tabNavigation,
                $_editorContent
            ] = self::fromTrace([
                'class' => get_class($exception),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
            ], $traceCount);
            $mainErrorInfo .= $_mainErrorInfo;
            $tabNavigation .= $_tabNavigation;
            $editorContent .= $_editorContent;
        }

        $kernel = ContainerHelper::use(KernelInterface::class);
        $configFile = $kernel?->getConfigFile();
        $configFile = is_string($configFile)
            ? (realpath($configFile)?:null)
            : null;
        $definedConfig = defined('CONFIG_FILE') && is_string(CONFIG_FILE)
            && file_exists(CONFIG_FILE)
            ? realpath(CONFIG_FILE)?:null
            : null;
        $isCallstack = $exception instanceof MaximumCallstackExceeded
            && count($exception->getTrace()) > 50;
        $stacks = [];
        // MaximumCallstackExceeded
        foreach ($exception->getTrace() as $trace) {
            // skip config
            if (isset($trace['file'])
                && (
                    $configFile === $trace['file']
                    || $definedConfig === $trace['file']
                )
            ) {
                continue;
            }

            if ($isCallstack) {
                $traceFileNameKey = $trace['file'] .':'. $trace['line'];
                $stacks[$traceFileNameKey] ??= 0;
                $stacks[$traceFileNameKey]++;
                if ($stacks[$traceFileNameKey] > 3) {
                    continue;
                }
            }
            $countLine++;
            $errorLineEditor .=  sprintf('<span>%d</span>', $countLine);
            if (!isset($trace['line']) || !isset($trace['file']) || !file_exists($trace['file'])) {
                $mainErrorInfo .= sprintf('<div class="internal" data-increment="%d">', $traceCount);
                $mainErrorInfo .= '<code>';
                $mainErrorInfo .= '<span style="color: #DD0000">[internal function]: </span>';
                $mainErrorInfo .= sprintf(
                    '%s%s%s<span style="color: #007700">()</span>',
                    sprintf('<span style="color: #0000BB">%s</span>', htmlentities($trace['class']??'')),
                    sprintf('<span style="color: #007700">%s</span>', htmlentities($trace['type']??'')),
                    sprintf('<span style="color: #0000BB">%s</span>', htmlentities($trace['function']??'')),
                );
                $mainErrorInfo .= '</code>';
                $mainErrorInfo .= '</div>';
            } else {
                [
                    $_mainErrorInfo,
                    $_tabNavigation,
                    $_editorContent,
                ] = $this->fromTrace($trace, $traceCount);
                $mainErrorInfo .= $_mainErrorInfo;
                $tabNavigation .= $_tabNavigation;
                $editorContent .= $_editorContent;
            }

            $mainErrorInfo .= "\n";
        }
        // freed
        $stacks = null;
        unset($stacks);
        $tabNavigationTop .= sprintf(
            '<div class="tab-info"><div><span>Trace Stack</span> <span>(%d)</span></div></div>',
            $traceCount
        );
        $tabNavigation = $tabNavigationTop . $tabNavigation;
        $additionalTrace  = '<div class="trace-section active" data-id="0">';
        $additionalTrace .= sprintf(
            '<div class="trace-content-file">%s</div>',
            $errorFile
        );
        $additionalTrace .=
            sprintf(
                '<div class="trace-content-wrapper">%s%s</div>',
                sprintf(
                    '<div class="traced-content-line" data-id="0">%s</div>',
                    $errorLineEditor
                ),
                sprintf(
                    '<div class="traced-content-details">%s</div>',
                    $mainErrorInfo
                )
            );
        $additionalTrace .= '</div>';
        $editorContent = $additionalTrace . $editorContent;
        $content = '<div id="trace-stack" class="trace-stack">';
        $content .= "<div class=\"trace-tab\">$tabNavigation</div>";
        $content .= "<div class=\"trace-details\">$editorContent</div>";
        $content .= '</div>';

        // $title = htmlentities($exception->getMessage());
        $title = htmlentities($this->getErrorTitle($exception));
        unset($tabNavigation, $mainErrorInfo, $editorContent, $exception);
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>$title</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/*# sourceURL=/error-page.css */
html *, html *::before, html *::after {
    box-sizing: border-box;
}

code, pre, .traced-content-details {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

body {
    padding: 0;
    margin: 0;
    font-size: 16px;
    line-height: 1.15;
    -webkit-text-size-adjust: 100%;
    border: 0;
    color: #444;
    font-family: system-ui, -apple-system, "Segoe UI",
        Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans",
         sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
}

.container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}
[toolbar-profiler="waterfall"] .container {
    padding-bottom: 28px;
}
.trace-stack {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: flex-start;
    height: 100%;
    width: 100%;
    max-width: 100%;
}

.trace-tab {
    z-index: 999;
    position: sticky;
    top: 0;
    flex: 1 1 300px;
    overflow-x: hidden;
    overflow-y: auto;
    width: 300px;
    background: #222;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.tab-info {
    padding: .7em 1em;
    font-size: .7em;
    color: #fff;
    font-weight: lighter;
    letter-spacing: 1px;
    background: rgba(255,255,255,.6);
    background: #4e646f;
    /*     border-top:3px solid rgba( 255, 255, 255, .3);  */
    border-bottom: 3px solid rgba( 255, 255, 255, .3);
}

.tab-info > div {
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    flex-wrap: nowrap;
}

.trace-tab-nav {
    position: relative;
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-items: flex-start;
    justify-content: center;
    width: 100%;
    padding: 1.5em 1em 1.5em calc(2em + 6px);
    color: #aaa;
    font-size: .8rem;
    cursor: pointer;
    transition: all ease .2s;
}

.trace-file {
    margin-top: .4em;
    font-size: .7rem;
    white-space: normal;
    word-wrap:break-word;
    word-break: break-word;
}

.trace-tab-nav.active, .trace-tab-nav:hover {
    background: rgba(255,255,255,.2);
}

.trace-tab-nav.active {
    color: #e2cb4c;
}

[data-id="0"].trace-tab-nav {
    color: #f1f1f1;
    background: #607D8B;
}

[data-id="0"].trace-tab-nav.active {
    color: #fff;
}

[data-id="0"] .trace-class::before {
    display: none;
}

.trace-class {
    word-break: break-all;
}

.trace-class::before {
    content: '';
    display: block;
    position: absolute;
    background: #fff;
    left: .6em;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    top: 50%;
    margin-top: -3px;
}

.trace-tab-nav.active .trace-class::before {
    background: #e2cb4c;
}

.trace-details {
    z-index: 900;
    position: relative;
    width: calc(100% - 300px);
    height: 100%;
}

.trace-section {
    position: absolute;
    /*     display: none; */
    background: #e9eae6;
    min-height: 100%;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    font-size: .8em;
    visibility: hidden;
    opacity: 0;
    transition: all ease 100ms;
}

.trace-section.active {
    left: 0;
    visibility: visible;
    opacity: 1;
    transition: none;
}

.trace-content-file {
    position: relative;
    top: 0;
    font-size: .6rem;
    padding: 1rem;
    background: #222;
    color: rgba(255, 255, 255, .5);
    z-index: 999;
}

.trace-content-wrapper {
    z-index: 900;
    position : relative;
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: flex-start;
    align-items: flex-start;
    min-height: 100%;
    line-height: 1.6;
    overflow: auto;
    height: 100%;
}

.traced-content-line {
    position: sticky;
    left:0;
    flex: 1 1 60px;
    max-width: 60px;
    width: 60px;
    background: #cfcfcf;
    min-height: 100%;
    padding-bottom: 1rem;
    font-weight: lighter;
    text-align: left;
    border-right: 1px solid rgba(0,0,0,.05);
    user-select: none;
    /* margin-right: .5em; */
}

.traced-content-line span {
    display: block;
    overflow-x: hidden;
    text-overflow: ellipsis;
    padding: 0 1rem;
}

.traced-content-line span[data-line] {
    cursor: pointer;
}

.traced-content-details {
    min-width: 100%;
    /* overflow: scroll; */
    padding-bottom: 4em;
    min-height: 100%;
    outline: none;
    box-shadow: none;
/*     margin-right: 1em; */
}

.traced-content-details > div {
    padding-left: .5rem;
    white-space: nowrap;
}

.traced-content-line span.current, .traced-content-details .current {
    background: rgba(0,0,0,.1);
    padding-top: 1rem;
    padding-bottom: 1rem;
}

[data-id="0"] .traced-content-line span.current, [data-id="0"] .traced-content-details .current {
    padding-top: .5rem;
    padding-bottom: .5rem;
}

.traced-content-details .current {
    border-left: 3px solid rgba(0,0,0,.2);
    padding-left: calc(.5rem - 3px);
}

.traced-content-line span.current {
    padding-right: calc(1rem + 1px);
    margin-right: -1px;
}

.document-copy {
    position: fixed;
    right: 2em;
    margin-top: 1em;
    top: auto;
    cursor: pointer;
    width: 40px;
    height: 40px;
    padding: 10px;
    line-height: 30px;
    visibility: hidden;
    z-index: -1;
}

.copied {
    position: absolute;
    left: calc(-100% - 1em);
    top: .5em;
    color: #8e6927;
    opacity: 1;
    visibility: visible;
    transition: all ease 1s;
}

.copied.hide-copy {
    opacity: 0;
    visibility: hidden;
}

.trace-content-wrapper:hover .document-copy, .trace-content-wrapper:focus .document-copy {
    visibility: visible;
    z-index: 999;
}
</style>
</head>
<body>
<div id="page" class="wrapper">
    <div id="content" class="container">
    $content
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('.traced-content-details').forEach(function (e) {
        e.setAttribute('contentEditable', 'true');
        e.addEventListener('cut', function (e) {
            e.preventDefault()
        });
        e.addEventListener('paste', function (e) {
            e.preventDefault()
        });
        let copy = document.createElement('div');
            copy.className = 'document-copy';
            copy.title = 'Copy content to clipboard';
        copy.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            +' stroke-width="1.5" stroke="currentColor" class="w-6 h-6">'
            +'<path stroke-linecap="round" stroke-linejoin="round" '
            +'d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125'
            +' 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06'
            +' 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.'
            +'25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621'
            +' 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125'
            +'-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125'
            +' 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" /></svg>';
        e.parentNode.prepend(copy);
        let timedoutC;
        copy.addEventListener('click', function (a) {
            a.preventDefault();
            if (!navigator||!navigator.clipboard) {
                return;
            }
            let content = e.getAttribute('data-content');
            if (content) {
                try {
                    content = JSON.parse(content);
                } catch (err) {
                    content = null;
                }
            }
            let saved = document.getElementById('copied');
            if (saved) {
                saved.remove();
            }
            if (timedoutC) {
                clearTimeout(timedoutC);
                timedoutC = null;
            }
            saved = document.createElement('div');
            saved.id = 'copied';
            saved.className = 'copied';
            saved.innerHTML = 'Copied';
            copy.prepend(saved);
            timedoutC = setTimeout(function () { 
                saved.classList.add('hide-copy');
            }, 200);
            content = content || e.textContent.replace(new RegExp(String.fromCharCode(160), "g"), ' ');
            navigator.clipboard.writeText(content);
        });
        e.addEventListener('keydown', function(e) {
            if (!e.metaKey && !/^(Arrow|Escape|Tab)/i.test(e.code)) {
                e.preventDefault();
                return false;
            }
            if (e.code === 'Escape') {
                window.getSelection()?.removeAllRanges();
            }
        });
    });
    let navTabs = document.querySelectorAll('.trace-tab-nav');
    let selectors = document.querySelectorAll('.trace-details [data-id]');
    document.querySelectorAll('.traced-content-line span[data-line]').forEach(function (e) {
        let eLine = e.getAttribute('data-line');
        if (/[^0-9]/.test(eLine)) {
            return;
        }
       e.addEventListener('click', function () {
           let Line = e.parentNode.parentNode.querySelector('.traced-content-details [data-line="'+eLine+'"]');
           if (!Line) {
               return;
           }
           let select = document.createRange();
           select.selectNode(Line);
           let windowSelection = window.getSelection();
           windowSelection.removeAllRanges();
           windowSelection.addRange(select);
       });
    });
    navTabs.forEach(function (z) {
       let id = z.getAttribute('data-id').toString();
       if (/[^0-9]/.test(id)) {
           return;
       }
       z.addEventListener('click', function (e) {
           e.preventDefault();
           z.classList.add('active');
           navTabs.forEach(function (a) {
               if (a === z) {
                   return;
               }
              a.classList.remove('active');
           });
           selectors.forEach(function (u) {
               let ids = u.getAttribute('data-id');
               if (ids !== id) {
                   u.classList.remove('active');
                   return;
               }
               u.classList.add('active');
           });
       });
    });
})(window);
</script>
</body>
</html>
HTML;
    }
}
