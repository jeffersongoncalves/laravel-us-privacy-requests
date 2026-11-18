<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Events;

use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;

/**
 * Completed or rejected: check $request->status. Notify the requester from a listener.
 */
class ConsumerRequestClosed
{
    public function __construct(public ConsumerRequest $request) {}
}
