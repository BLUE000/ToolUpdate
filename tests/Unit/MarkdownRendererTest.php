<?php
declare(strict_types=1);

use ReleaseHub\Template\MarkdownRenderer;

function runMarkdownRendererTests(): void
{
    $renderer = new MarkdownRenderer(__DIR__ . '/../../server/templates');

    // UT-05-01: Markdown構文
    $md = "# 見出し1\n\n**太字テキスト**\n- リスト項目1\n- リスト項目2";
    $html = $renderer->markdownToHtml($md);

    TestAssert::assertStringContains('<h1>見出し1</h1>', $html, 'MarkdownRenderer: H1 heading rendered');
    TestAssert::assertStringContains('<strong>太字テキスト</strong>', $html, 'MarkdownRenderer: Strong rendered');
    TestAssert::assertStringContains('<li>リスト項目1</li>', $html, 'MarkdownRenderer: List item rendered');

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
