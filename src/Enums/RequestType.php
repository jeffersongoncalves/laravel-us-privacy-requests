<?php

namespace JeffersonGoncalves\UsPrivacyRequests\Enums;

/**
 * Consumer rights handled as authenticated requests under the US state laws. Opting out of
 * sale, targeted advertising and profiling needs no authentication: see laravel-us-privacy-opt-out.
 */
enum RequestType: string
{
    case Access = 'access';               // confirm processing and access the data
    case Correct = 'correct';             // not in Utah and Iowa
    case Delete = 'delete';
    case Portability = 'portability';     // a portable, readily usable copy
    case ThirdParties = 'third_parties';  // list of specific third parties (Oregon, Delaware, Minnesota, Maryland)
    case Profiling = 'profiling';         // question the result of profiling (Minnesota)

    /** Types fulfil() handles on its own; the others are answered by hand, then complete(). */
    public function isAutomatic(): bool
    {
        return in_array($this, [self::Access, self::Delete, self::Portability], true);
    }

    /** The right in laravel-us-privacy's state rules. */
    public function right(): string
    {
        return match ($this) {
            self::ThirdParties => 'list_third_parties',
            self::Profiling => 'question_profiling',
            default => $this->value,
        };
    }
}
