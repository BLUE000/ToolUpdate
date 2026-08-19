<?php
declare(strict_types=1);

use ReleaseHub\Template\MarkdownRenderer;

function runMarkdownRendererTests(): void
{
    $renderer = new MarkdownRenderer(__DIR__ . '/../../server/templates');

    // UT-05-01: Markdown構文
    $md = "# 見出し1\n\n- **設定タブ**の追加\n  - 言語の切り替え\n• UIの改善";
    $html = $renderer->markdownToHtml($md);

    TestAssert::assertStringContains('<h1>見出し1</h1>', $html, 'MarkdownRenderer: H1 heading rendered');
    TestAssert::assertStringContains('<strong>設定タブ</strong>', $html, 'MarkdownRenderer: Strong in list rendered');
    TestAssert::assertStringContains('<li class="list-nested-1">言語の切り替え</li>', $html, 'MarkdownRenderer: Nested list item rendered');
    TestAssert::assertStringContains('<li>UIの改善</li>', $html, 'MarkdownRenderer: Unicode bullet list rendered');

    // UT-05-02: プレースホルダー置換
    $comp = $renderer->renderComponent('ranking_card', [
        'RANK_NUMBER' => '1',
        'TOOL_ID' => 'TrustChain',
        'TOOL_NAME' => 'TrustChain Authenticator',
        'TOTAL_DOWNLOADS' => '1,250'
    ]);

    TestAssert::assertStringContains('#1', $comp, 'MarkdownRenderer: Ranking badge #1');
    TestAssert::assertStringContains('TrustChain Authenticator', $comp, 'MarkdownRenderer: Tool name replaced');
    TestAssert::assertStringContains('1,250 DL', $comp, 'MarkdownRenderer: Downloads replaced');
}
