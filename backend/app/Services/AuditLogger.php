<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function record(?int $userId, string $event, array $metadata = []): void
    {
        SecurityAuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    public function recordForRequest(object $request, string $event, array $metadata = []): void
    {
        $this->record($request->user()?->id, $event, $metadata);
    }
}
