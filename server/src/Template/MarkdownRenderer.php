<?php
declare(strict_types=1);

namespace ReleaseHub\Template;

class MarkdownRenderer
{
    private string $templateDir;

    public function __construct(string $templateDir)
    {
        $this->templateDir = rtrim($templateDir, '/\\');
    }

    public function render(string $pageTemplate, array $params = [], ?string $layout = 'layout.md'): string
    {
        $pagePath = $this->templateDir . '/' . ltrim($pageTemplate, '/\\');
        $pageContent = file_exists($pagePath) ? (string)file_get_contents($pagePath) : '';

        // ページ内プレースホルダー置換
        $contentRendered = $this->replacePlaceholders($pageContent, $params);
        $contentHtml = $this->markdownToHtml($contentRendered);

        if ($layout === null) {
            return $contentHtml;
        }

        $layoutPath = $this->templateDir . '/' . ltrim($layout, '/\\');
        $layoutContent = file_exists($layoutPath) ? (string)file_get_contents($layoutPath) : '{CONTENT}';

        $layoutParams = array_merge($params, [
            'CONTENT' => $contentHtml
        ]);

        $layoutRendered = $this->replacePlaceholders($layoutContent, $layoutParams);
        return $layoutRendered;
    }

    public function renderComponent(string $componentName, array $params = []): string
    {
        $compPath = $this->templateDir . '/components/' . ltrim($componentName, '/\\');
        if (!str_ends_with($compPath, '.md')) {
            $compPath .= '.md';
        }

        $content = file_exists($compPath) ? (string)file_get_contents($compPath) : '';
        $replaced = $this->replacePlaceholders($content, $params);
        return $this->markdownToHtml($replaced);
    }

    public function replacePlaceholders(string $template, array $params): string
    {
        foreach ($params as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $valStr = (string)$value;
            $template = str_replace('{' . $key . '}', $valStr, $template);
        }
        return $template;
    }

    public function markdownToHtml(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $html = '';
        $inList = false;
        $inTable = false;
        $inCodeBlock = false;
        $codeBlockBuffer = [];

        foreach ($lines as $line) {
            // コードブロック
            if (preg_match('/^```(.*)$/', $line, $m)) {
                if ($inCodeBlock) {
                    $html .= '<pre class="code-block"><code>' . htmlspecialchars(implode("\n", $codeBlockBuffer), ENT_QUOTES, 'UTF-8') . "</code></pre>\n";
                    $codeBlockBuffer = [];
                    $inCodeBlock = false;
                } else {
                    $inCodeBlock = true;
                }
                continue;
            }

            if ($inCodeBlock) {
                $codeBlockBuffer[] = $line;
                continue;
            }

            $trimmed = trim($line);

            // 空行
            if ($trimmed === '') {
                if ($inList) {
                    $html .= "</ul>\n";
                    $inList = false;
                }
                if ($inTable) {
                    $html .= "</tbody></table></div>\n";
                    $inTable = false;
                }
                continue;
            }

            // テーブル
            if (str_starts_with($trimmed, '|')) {
                if (!$inTable) {
                    $html .= "<div class=\"table-responsive\"><table class=\"data-table\">\n";
                    $inTable = true;
                    // ヘッダー行
                    $cells = array_values(array_filter(array_map('trim', explode('|', $trimmed)), fn($c) => $c !== ''));
                    $html .= "<thead><tr>\n";
                    foreach ($cells as $cell) {
                        $html .= '<th>' . $this->formatInline($cell) . "</th>\n";
                    }
                    $html .= "</tr></thead><tbody>\n";
                    continue;
                } elseif (preg_match('/^[\|\s\-:]+$/', $trimmed)) {
                    // セパレーター行
                    continue;
                } else {
                    // データ行
                    $cells = array_values(array_filter(array_map('trim', explode('|', $trimmed)), fn($c) => $c !== ''));
                    $html .= "<tr>\n";
                    foreach ($cells as $cell) {
                        $html .= '<td>' . $this->formatInline($cell) . "</td>\n";
                    }
                    $html .= "</tr>\n";
                    continue;
                }
            } elseif ($inTable) {
                $html .= "</tbody></table></div>\n";
                $inTable = false;
            }

            // 見出し
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
                if ($inList) {
                    $html .= "</ul>\n";
                    $inList = false;
                }
                $level = strlen($m[1]);
                $text = $this->formatInline($m[2]);
                $html .= "<h{$level}>{$text}</h{$level}>\n";
                continue;
            }

            // リスト
            if (preg_match('/^[\-\*]\s+(.*)$/', $line, $m)) {
                if (!$inList) {
                    $html .= "<ul class=\"md-list\">\n";
                    $inList = true;
                }
                $text = $this->formatInline($m[1]);
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // 通常段落
            if ($inList) {
                $html .= "</ul>\n";
                $inList = false;
            }

            // 既にHTMLタグで始まっている場合はそのまま通す
            if (preg_match('/^<[a-zA-Z\/]/', $trimmed)) {
                $html .= $line . "\n";
            } else {
                $text = $this->formatInline($line);
                $html .= "<p>{$text}</p>\n";
            }
        }

        if ($inList) {
            $html .= "</ul>\n";
        }
        if ($inTable) {
            $html .= "</tbody></table></div>\n";
        }
        if ($inCodeBlock) {
            $html .= '<pre class="code-block"><code>' . htmlspecialchars(implode("\n", $codeBlockBuffer), ENT_QUOTES, 'UTF-8') . "</code></pre>\n";
        }

        return $html;
    }

    private function formatInline(string $text): string
    {
        // 太字 **text**
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        // イタリック *text*
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)\*/', '<em>$1</em>', $text);
        // インラインコード `code`
        $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);
        // リンク [text](url)
        $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $text);

        return $text;
    }
}
