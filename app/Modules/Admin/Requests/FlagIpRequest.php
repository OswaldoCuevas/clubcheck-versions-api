<?php

namespace App\Modules\Admin\Requests;

final class FlagIpRequest extends AdminRequest
{
    public readonly string $id;
    public readonly bool $flagged;
    public readonly ?string $reason;

    public function __construct(string $id)
    {
        parent::__construct(['POST'], ['id' => $id]);

        $this->id = $this->routeParam('id');
        $this->flagged = isset($this->payload['flagged']) ? (bool) $this->payload['flagged'] : true;
        $this->reason = isset($this->payload['reason']) ? trim((string) $this->payload['reason']) : null;
    }
}
