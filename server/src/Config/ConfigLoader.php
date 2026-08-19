<?php
declare(strict_types=1);

namespace ReleaseHub\Config;

class ConfigLoader
{
    private string $configDir;
    private ?array $branchesCache = null;
    private ?array $webhooksCache = null;
    private ?array $geoipCache = null;

    public function __construct(string $configDir)
    {
        $this->configDir = rtrim($configDir, '/\\');
    }

    public function getBranches(): array
    {
        if ($this->branchesCache !== null) {
            return $this->branchesCache;
        }

        $filePath = $this->configDir . '/branches.json';
        if (!file_exists($filePath)) {
            $this->branchesCache = [];
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->branchesCache = [];
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['tools']) || !is_array($data['tools'])) {
            $this->branchesCache = [];
            return [];
        }

        $this->branchesCache = $data['tools'];
        return $this->branchesCache;
    }

    public function getTool(string $toolId): ?array
    {
        $tools = $this->getBranches();
        foreach ($tools as $tool) {
            if (isset($tool['id']) && $tool['id'] === $toolId) {
                return $tool;
            }
        }
        return null;
    }

    public function getWebhooks(): array
    {
        if ($this->webhooksCache !== null) {
            return $this->webhooksCache;
        }

        $filePath = $this->configDir . '/webhooks.json';
        if (!file_exists($filePath)) {
            $this->webhooksCache = ['global_webhooks' => [], 'enabled' => true];
            return $this->webhooksCache;
        }

        $content = file_get_contents($filePath);
        $data = $content !== false ? json_decode($content, true) : null;
        if (!is_array($data)) {
            $this->webhooksCache = ['global_webhooks' => [], 'enabled' => true];
            return $this->webhooksCache;
        }

        $this->webhooksCache = $data;
        return $this->webhooksCache;
    }

    public function getGeoIpData(): array
    {
        if ($this->geoipCache !== null) {
            return $this->geoipCache;
        }

        $filePath = $this->configDir . '/geoip.json';
        if (!file_exists($filePath)) {
            $this->geoipCache = ['local' => [], 'ranges' => []];
            return $this->geoipCache;
        }

        $content = file_get_contents($filePath);
        $data = $content !== false ? json_decode($content, true) : null;
        if (!is_array($data)) {
            $this->geoipCache = ['local' => [], 'ranges' => []];
            return $this->geoipCache;
        }

        $this->geoipCache = $data;
        return $this->geoipCache;
    }

    public function validateToolConfig(array $tool): bool
    {
        $requiredKeys = ['id', 'name', 'repository', 'branch'];
        foreach ($requiredKeys as $key) {
            if (!isset($tool[$key]) || !is_string($tool[$key]) || trim($tool[$key]) === '') {
                return false;
            }
        }
        return true;
    }
}
