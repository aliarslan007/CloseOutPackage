<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Shared\Exceptions\AuthCenterException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads hub/site catalog data from the Auth Center machine-to-machine
 * endpoints (secret-header protected). Responses are normalized to a
 * stable shape for the pm-software frontend.
 */
final class AuthCenterSiteService
{
    private const SITES_PATH = '/api/pm/sites';

    private const FILTER_OPTIONS_PATH = '/api/pm/sites/filter-options';

    public const TYPE_HUB = 'hub';

    public const TYPE_SMALLCELL = 'smallcell';

    /**
     * @return array{regions: list<string>, states: list<array{code: string, name: string}>}
     *
     * @throws AuthCenterException
     */
    public function filterOptions(string $type, ?string $region = null): array
    {
        $query = array_filter(['type' => $type, 'region' => $region], fn ($value) => $value !== null);

        $response = $this->get(self::FILTER_OPTIONS_PATH, $query);

        return [
            'regions' => $this->stringList($response->json('regions')),
            'states' => $this->stateList($response->json('states')),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     *
     * @throws AuthCenterException
     */
    public function sites(string $type, ?string $region = null, ?string $state = null): array
    {
        $query = array_filter(
            ['type' => $type, 'region' => $region, 'state' => $state],
            fn ($value) => $value !== null,
        );

        $response = $this->get(self::SITES_PATH, $query);
        $data = $response->json('data');

        if (! is_array($data)) {
            return [];
        }

        $sites = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            $name = is_string($item['ohpa_site'] ?? null)
                ? trim($item['ohpa_site'])
                : (is_string($item['site_name'] ?? null) ? trim($item['site_name']) : '');

            if ($id === false || $name === '') {
                continue;
            }

            $sites[] = ['id' => $id, 'name' => $name];
        }

        return $sites;
    }

    /**
     * @param  array<string, scalar|null>  $query
     *
     * @throws AuthCenterException
     */
    private function get(string $path, array $query): Response
    {
        $baseUrl = config('services.authcenter.url');
        $secret = config('services.authcenter.pm_secret');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new AuthCenterException('AuthCenter is not configured.');
        }

        if (! is_string($secret) || $secret === '') {
            throw new AuthCenterException('AuthCenter PM secret is not configured.');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-PM-Secret' => $secret])
                ->get(rtrim($baseUrl, '/').$path, $query);
        } catch (\Throwable $e) {
            Log::warning('AuthCenter sites request failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new AuthCenterException('Unable to reach the Auth Center.');
        }

        if ($response->failed()) {
            $message = is_string($response->json('message')) ? $response->json('message') : 'Unknown error';

            Log::warning('AuthCenter sites request rejected', [
                'path' => $path,
                'status' => $response->status(),
                'message' => $message,
            ]);

            throw new AuthCenterException($message);
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_string($item) ? trim($item) : '',
            $value,
        ), fn (string $item) => $item !== ''));
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function stateList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $states = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = is_string($item['code'] ?? null) ? trim($item['code']) : '';
            $name = is_string($item['name'] ?? null) ? trim($item['name']) : '';

            if ($code === '') {
                continue;
            }

            $states[] = ['code' => $code, 'name' => $name !== '' ? $name : $code];
        }

        return $states;
    }
}
