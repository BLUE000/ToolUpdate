<?php
declare(strict_types=1);

namespace ReleaseHub\Notifier;

class FeedGenerator
{
    public function generateRss(array $allManifests, ?string $toolId = null, string $baseUrl = '.'): string
    {
        $items = [];
        foreach ($allManifests as $manifest) {
            $id = $manifest['tool_id'] ?? '';
            if ($toolId !== null && $id !== $toolId) {
                continue;
            }
            $name = $manifest['tool_name'] ?? $id;
            $releases = $manifest['releases'] ?? [];

            foreach ($releases as $rel) {
                $items[] = [
                    'tool_id' => $id,
                    'tool_name' => $name,
                    'version' => $rel['version'] ?? '',
                    'release_date' => $rel['release_date'] ?? date('c'),
                    'release_notes' => $rel['release_notes'] ?? '',
                    'timestamp' => strtotime($rel['release_date'] ?? 'now')
                ];
            }
        }

        // 時系列降順
        usort($items, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        $title = $toolId !== null ? "ReleaseHub - {$toolId} Updates" : "ReleaseHub - All Releases";
        $link = htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8');
        $desc = "Release updates feed for ReleaseHub";
        $lastBuildDate = date(DATE_RSS);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>\n";
        $xml .= "    <link>{$link}</link>\n";
        $xml .= "    <description>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</description>\n";
        $xml .= "    <lastBuildDate>{$lastBuildDate}</lastBuildDate>\n";

        foreach (array_slice($items, 0, 30) as $item) {
            $itemTitle = sprintf('%s %s Released', $item['tool_name'], $item['version']);
            $itemLink = sprintf('%s?page=tool&amp;id=%s', $baseUrl, urlencode($item['tool_id']));
            $itemGuid = sprintf('%s-%s', $item['tool_id'], $item['version']);
            $pubDate = date(DATE_RSS, $item['timestamp']);
            $itemDesc = nl2br(htmlspecialchars($item['release_notes'], ENT_QUOTES, 'UTF-8'));

            $xml .= "    <item>\n";
            $xml .= "      <title>" . htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8') . "</title>\n";
            $xml .= "      <link>{$itemLink}</link>\n";
            $xml .= "      <guid isPermaLink=\"false\">{$itemGuid}</guid>\n";
            $xml .= "      <pubDate>{$pubDate}</pubDate>\n";
            $xml .= "      <description><![CDATA[{$itemDesc}]]></description>\n";
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    public function generateAppcast(array $manifest, string $baseUrl = '.'): string
    {
        $toolId = $manifest['tool_id'] ?? 'Tool';
        $toolName = $manifest['tool_name'] ?? $toolId;
        $releases = $manifest['releases'] ?? [];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:sparkle="http://www.andymatuschak.org/xml-namespaces/sparkle" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . htmlspecialchars($toolName . " Appcast", ENT_QUOTES, 'UTF-8') . "</title>\n";
        $xml .= "    <link>" . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . "</link>\n";
        $xml .= "    <description>Updates for " . htmlspecialchars($toolName, ENT_QUOTES, 'UTF-8') . "</description>\n";

        foreach ($releases as $rel) {
            $version = $rel['version'] ?? '';
            $date = date(DATE_RSS, strtotime($rel['release_date'] ?? 'now'));
            $notes = nl2br(htmlspecialchars($rel['release_notes'] ?? '', ENT_QUOTES, 'UTF-8'));
            $fullPkg = $rel['full_package'] ?? [];
            $downloadUrl = sprintf('%s/api.php?action=download&amp;tool=%s&amp;version=%s&amp;type=full', $baseUrl, urlencode($toolId), urlencode($version));
            $length = $fullPkg['size'] ?? 0;

            $xml .= "    <item>\n";
            $xml .= "      <title>" . htmlspecialchars("{$toolName} {$version}", ENT_QUOTES, 'UTF-8') . "</title>\n";
            $xml .= "      <sparkle:releaseNotesLink><![CDATA[{$notes}]]></sparkle:releaseNotesLink>\n";
            $xml .= "      <pubDate>{$date}</pubDate>\n";
            $xml .= "      <enclosure url=\"{$downloadUrl}\" sparkle:version=\"{$version}\" length=\"{$length}\" type=\"application/octet-stream\" />\n";
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }
}
