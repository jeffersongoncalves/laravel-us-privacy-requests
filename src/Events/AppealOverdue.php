<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

class AppealOverdue
{
    public function __construct(public ConsumerRequest $request) {}
}
