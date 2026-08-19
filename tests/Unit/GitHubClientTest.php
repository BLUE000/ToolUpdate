<?php
declare(strict_types=1);

use ReleaseHub\Git\GitHubClient;

function runGitHubClientTests(): void
{
    $client = new GitHubClient();

    // UT-06-01: URLパース
    $path1 = $client->parseRepoPath('https://github.com/BLUE000/TrustChain.git');
    TestAssert::assertEquals('BLUE000/TrustChain', $path1, 'GitHubClient: HTTPS with .git parsed');

    $path2 = $client->parseRepoPath('https://github.com/example-org/sample-tool');
    TestAssert::assertEquals('example-org/sample-tool', $path2, 'GitHubClient: HTTPS without .git parsed');

    // UT-06-02: レートリミット通常時
    TestAssert::assertFalse($client->isRateLimited(), 'GitHubClient: Not rate limited initially');

    // UT-06-04: 多言語README取得メソッド（構文/戻り値型検証）
    $readmeFiles = $client->getReadmeFiles('https://github.com/BLUE000/TrustChain.git');
    TestAssert::assertTrue(is_array($readmeFiles), 'GitHubClient: getReadmeFiles returns array');
}
