<?php

namespace App\Services\System;

use App\Dao\System\SystemApiInterfaceDao;
use App\Services\Service;

class SystemApiInterfaceService extends Service
{
    public function __construct(SystemApiInterfaceDao $dao)
    {
        $this->dao = $dao;
    }

    public function saveOrUpdate(array $data): void
    {
        $id = intval($data['id'] ?? 0);
        $save = [
            'name' => (string) ($data['name'] ?? ''),
            'module' => (string) ($data['module'] ?? ''),
            'path' => ltrim((string) ($data['path'] ?? ''), '/'),
            'method' => strtoupper((string) ($data['method'] ?? 'POST')),
            'request_params' => $this->decodeParams($data['request_params'] ?? []),
            'response_params' => $this->decodeParams($data['response_params'] ?? []),
            'is_enable' => intval($data['is_enable'] ?? 1),
            'remark' => (string) ($data['remark'] ?? ''),
        ];

        if ($id > 0) {
            $this->dao->update($id, $save);
            return;
        }

        $exists = $this->dao->search([
            'method' => $save['method'],
            'path' => $save['path'],
        ])->first();

        if ($exists) {
            $this->dao->update($exists['id'], $save);
            return;
        }

        $this->dao->save($save);
    }

    public function getDetail(int $id): array
    {
        $row = $this->dao->get($id);
        if (!$row) {
            return [];
        }

        $data = $row->toArray();
        $data['request_preview'] = $this->paramsToExample($data['request_params'] ?? []);
        $data['response_preview'] = $this->paramsToExample($data['response_params'] ?? []);
        return $data;
    }

    public function importFromApiRoutes(): array
    {
        $routes = $this->parseApiRoutes(base_path('routes/api.php'));
        $count = 0;
        $importedKeys = [];
        foreach ($routes as $route) {
            if ($route['path'] === 'v/{alias}') {
                continue;
            }
            $this->saveOrUpdate([
                'name' => $route['name'],
                'module' => $route['module'],
                'path' => $route['path'],
                'method' => $route['method'],
                'request_params' => [],
                'response_params' => [],
                'is_enable' => 1,
                'remark' => '由 routes/api.php 导入',
            ]);
            $importedKeys[] = strtoupper($route['method']) . ' ' . ltrim($route['path'], '/');
            $count++;
        }

        $this->disableStaleImportedRoutes($importedKeys);

        return ['count' => $count];
    }

    private function disableStaleImportedRoutes(array $importedKeys): void
    {
        $valid = array_flip($importedKeys);
        foreach ($this->dao->search(['is_enable' => 1])->where('remark', '由 routes/api.php 导入')->get() as $row) {
            $key = strtoupper((string) $row['method']) . ' ' . ltrim((string) $row['path'], '/');
            if (!isset($valid[$key])) {
                $this->dao->update($row['id'], ['is_enable' => 0]);
            }
        }
    }

    private function decodeParams($params): array
    {
        if (is_array($params)) {
            return $params;
        }

        $decoded = json_decode((string) $params, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function paramsToExample(array $params): array
    {
        $result = [];
        foreach ($params as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = (string) ($item['key'] ?? $item['name'] ?? '');
            if ($key === '') {
                continue;
            }
            $result[$key] = $item['example'] ?? $this->defaultValueByType((string) ($item['type'] ?? 'string'));
        }
        return $result;
    }

    private function defaultValueByType(string $type)
    {
        return match (strtolower($type)) {
            'int', 'integer' => 1,
            'float', 'double', 'decimal' => 1.0,
            'bool', 'boolean' => true,
            'array', 'list' => [],
            'object' => new \stdClass(),
            default => '',
        };
    }

    private function parseApiRoutes(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $routes = [];
        $prefixStack = [];
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $pendingComment = '';
        $pendingPrefix = null;
        $pendingPrefixStack = [];
        $braceDepth = 0;

        foreach ($lines as $line) {
            $trim = trim($line);
            if (str_starts_with($trim, '//')) {
                $pendingComment = trim(substr($trim, 2));
            }

            if (preg_match('/Route::prefix\(\'([^\']+)\'\)/', $trim, $match)) {
                $pendingPrefix = trim($match[1], '/');
            }

            if (preg_match('/Route::group\(\s*\[.*?[\'\"]prefix[\'\"]\s*=>\s*[\'\"]([^\'\"]+)[\'\"]/i', $trim, $match)) {
                $pendingPrefix = trim($match[1], '/');
            }

            if (preg_match('/(?:Route::|\$route->)(get|post|put|delete|any)\(\'([^\']+)\'/i', $trim, $match)) {
                $method = strtoupper($match[1]);
                $routePath = trim($match[2], '/');
                $prefix = implode('/', array_filter($prefixStack));
                $fullPath = trim($prefix . '/' . $routePath, '/');
                $routes[] = [
                    'method' => $method,
                    'path' => $fullPath,
                    'module' => explode('/', $fullPath)[0] ?? '',
                    'name' => $pendingComment !== '' ? $pendingComment : $fullPath,
                ];
                $pendingComment = '';
            }

            $open = substr_count($line, '{');
            $close = substr_count($line, '}');

            if ($pendingPrefix !== null && $open > 0) {
                $prefixStack[] = $pendingPrefix;
                $pendingPrefixStack[] = $braceDepth + $open - $close;
                $pendingPrefix = null;
            }

            $braceDepth += $open - $close;

            while (!empty($pendingPrefixStack) && $braceDepth < end($pendingPrefixStack) && !empty($prefixStack)) {
                array_pop($prefixStack);
                array_pop($pendingPrefixStack);
            }
        }

        return $routes;
    }
}
