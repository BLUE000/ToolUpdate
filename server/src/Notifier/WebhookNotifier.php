<?php
declare(strict_types=1);

namespace ReleaseHub\Notifier;

class WebhookNotifier
{
    public function notify(array $toolConfig, array $releaseEntry, array $globalWebhooks = []): bool
    {
        $urls = [];

        // ツール個別Webhook
        if (!empty($toolConfig['webhook_url']) && is_string($toolConfig['webhook_url'])) {
            $urls[] = trim($toolConfig['webhook_url']);
        }

        // 全体通知Webhook
        foreach ($globalWebhooks as $url) {
            if (is_string($url) && trim($url) !== '') {
                $urls[] = trim($url);
            }
        }

        $urls = array_unique(array_filter($urls));
        if (empty($urls)) {
            return false;
        }

        $toolName = $toolConfig['name'] ?? ($toolConfig['id'] ?? 'Unknown Tool');
        $version = $releaseEntry['version'] ?? 'v0.0.0';
        $notes = $releaseEntry['release_notes'] ?? '新バージョンがリリースされました。';
        $releaseDate = $releaseEntry['release_date'] ?? date('Y-m-d H:i');

        // Discord / Slack 互換ペイロード
        $payload = [
            'username' => 'ReleaseHub',
            'content' => sprintf("🚀 **%s** の新バージョン `%s` がリリースされました！\n\n**更新日時**: %s\n**更新内容**:\n%s", $toolName, $version, $releaseDate, $notes),
            'embeds' => [
                [
                    'title' => sprintf('%s %s', $toolName, $version),
                    'description' => $notes,
                    'color' => 0x4f46e5,
                    'fields' => [
                        [
                            'name' => 'リリース日',
                            'value' => $releaseDate,
                            'inline' => true
                        ],
                        [
                            'name' => 'バージョン',
                            'value' => $version,
                            'inline' => true
                        ]
                    ]
                ]
            ]
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return false;
        }

        $success = true;
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'User-Agent: ReleaseHub-Notifier/1.0'
            ]);

            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($res === false || $code >= 400) {
                $success = false;
            }
        }

        return $success;
    }
}
