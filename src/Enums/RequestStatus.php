<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
    /** The consumer appealed the refusal and waits for the decision. */
    case Appealed = 'appealed';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::Appealed];
    }
}
