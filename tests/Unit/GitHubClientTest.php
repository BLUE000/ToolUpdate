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
}
