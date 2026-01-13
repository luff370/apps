<?php

namespace App\Support\Services\ReadTask\Contracts;

use Illuminate\Http\Request;

interface ReadTaskChannelInterface
{
    public function getReadTask(string $openid): array;

    public function handleCallback(Request $request): void;

    public function verifyCallback(array $data): bool;

}
