<?php
declare(strict_types=1);

use ReleaseHub\Notifier\FeedGenerator;

function runFeedRoutesIntegrationTests(): void
{
    $feedGen = new FeedGenerator();

    $manifests = [
        [
            'tool_id' => 'TrustChain',
            'tool_name' => 'TrustChain Authenticator',
            'releases' => [
                [
                    'version' => 'v2.1.0',
                    'release_date' => '2026-08-20T00:00:00+09:00',
                    'release_notes' => 'Feed Integration Notes',
                    'full_package' => [
                        'filename' => 'TrustChain_v2.1.0_full.zip',
                        'size' => 1024
                    ]
                ]
            ]
        ]
    ];

    // IT-12: 全体RSS 2.0
    $rss = $feedGen->generateRss($manifests);
    TestAssert::assertStringContains('<rss version="2.0"', $rss, 'Feed IT-12: RSS 2.0 root tag');
    TestAssert::assertStringContains('<title>TrustChain Authenticator v2.1.0 Released</title>', $rss, 'Feed IT-12: Release item title');

    // IT-13: ツール個別RSS
    $rssTc = $feedGen->generateRss($manifests, 'TrustChain');
    TestAssert::assertStringContains('<title>ReleaseHub - TrustChain Updates</title>', $rssTc, 'Feed IT-13: Tool specific feed title');

    // IT-14: WinSparkle Appcast XML
    $appcast = $feedGen->generateAppcast($manifests[0]);
    TestAssert::assertStringContains('xmlns:sparkle=', $appcast, 'Feed IT-14: Sparkle namespace');
    TestAssert::assertStringContains('sparkle:version="v2.1.0"', $appcast, 'Feed IT-14: Version attribute in enclosure');
}
