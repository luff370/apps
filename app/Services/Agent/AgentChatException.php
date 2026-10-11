<?php

namespace App\Services\Agent;

use RuntimeException;

class AgentChatException extends RuntimeException
{
    /** 携带返回给客户端的业务状态码。 */
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}
