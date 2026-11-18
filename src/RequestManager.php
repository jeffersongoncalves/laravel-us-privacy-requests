<?php

namespace JeffersonGoncalves\UsPrivacyRequests;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JeffersonGoncalves\UsPrivacy\Contracts\HasPersonalData;
use JeffersonGoncalves\UsPrivacy\StateRules;
use JeffersonGoncalves\UsPrivacy\UsPrivacy;
use JeffersonGoncalves\UsPrivacyRequests\Contracts\ResolvesConsumer;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestStatus;
use JeffersonGoncalves\UsPrivacyRequests\Enums\RequestType;
use JeffersonGoncalves\UsPrivacyRequests\Events\AppealDecided;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestAppealed;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestClosed;
use JeffersonGoncalves\UsPrivacyRequests\Events\ConsumerRequestOpened;
use JeffersonGoncalves\UsPrivacyRequests\Models\ConsumerRequest;
use LogicException;

class RequestManager
{
    public function __construct(protected UsPrivacy $privacy) {}

    /**
     * Open a request. The deadline follows the law of the consumer's state ($state, a two-letter
     * code; null = unknown: the strictest rules).
     *
     * @param  array{name?: string|null, email?: string|null, details?: string|null}  $requester
     */
    public function open(RequestType $type, ?Model $subject = null, array $requester = [], ?string $state = null): ConsumerRequest
    {
        $state = $state === null ? null : strtoupper($state);
        $rules = $this->privacy->rulesFor($state);

        $request = ConsumerRequest::create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'requester_name' => $requester['name'] ?? null,
            'requester_email' => $requester['email'] ?? null,
            'details' => $requester['details'] ?? null,
            'type' => $type,
            'status' => RequestStatus::Pending,
            'state' => $state,
            'due_at' => $rules?->requestDeadline(now()) ?? now()->add(CarbonInterval::make((string) config('us-privacy-requests.fallback_deadline', '45 days'))),
        ]);

        event(new ConsumerRequestOpened($request));

        return $request;
    }

    /**
     * Mark the requester's identity as authenticated. Required before fulfil(): never export or erase on an unauthenticated request.
     */
    public function verify(ConsumerRequest $request, ?Model $subject = null): ConsumerRequest
    {
        $request->forceFill(array_filter([
            'verified_at' => now(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
        ]))->save();

        return $request;
    }

    /**
     * Run the automatic types (access, portability, delete) and complete the request.
     */
    public function fulfil(ConsumerRequest $request, ?string $response = null): ConsumerRequest
    {
        if (! $request->isOpen()) {
            throw new LogicException("Request [{$request->getKey()}] is already closed.");
        }

        if (! $request->isVerified()) {
            throw new LogicException("Verify the requester's identity before fulfilling request [{$request->getKey()}].");
        }

        if (! $request->type->isAutomatic()) {
            throw new LogicException("Requests of type [{$request->type->value}] are answered by hand: call complete() when done.");
        }

        $subject = $request->subject;

        if (! $subject instanceof Model) {
            throw new LogicException("Request [{$request->getKey()}] has no consumer: pass one to verify().");
        }

        match ($request->type) {
            RequestType::Delete => $this->erase($subject),
            default => $request->export_path = $this->exportToFile($subject, "request-{$request->getKey()}.json"),
        };

        return $this->complete($request, $response);
    }

    /**
     * Extend the deadline once by the state's extension (45 days) when reasonably necessary.
     * Tell the consumer, with the reasons, within the initial period.
     */
    public function extend(ConsumerRequest $request, string $reason): ConsumerRequest
    {
        if ($request->status !== RequestStatus::Pending || $request->extended_at !== null) {
            throw new LogicException("Request [{$request->getKey()}] is closed or was already extended.");
        }

        if ($request->due_at->isPast()) {
            throw new LogicException("Request [{$request->getKey()}] is past its deadline: extend it within the initial period.");
        }

        $days = $this->rules($request)->extensionDays ?? 45;

        $request->forceFill([
            'due_at' => $request->due_at->copy()->addDays($days),
            'extended_at' => now(),
            'extension_reason' => $reason,
        ])->save();

        return $request;
    }

    public function complete(ConsumerRequest $request, ?string $response = null): ConsumerRequest
    {
        return $this->close($request, RequestStatus::Completed, $response);
    }

    /**
     * Refuse the request. Tell the consumer why and, where their state has one, how to appeal.
     */
    public function reject(ConsumerRequest $request, string $reason): ConsumerRequest
    {
        return $this->close($request, RequestStatus::Rejected, $reason);
    }

    /**
     * The consumer appeals a refusal (or a partial answer). Every state but Utah requires an
     * appeal process; the decision is due within the state's window (45 or 60 days).
     */
    public function appeal(ConsumerRequest $request, ?string $reason = null): ConsumerRequest
    {
        if (! in_array($request->status, [RequestStatus::Rejected, RequestStatus::Completed], true) || $request->appealed_at !== null) {
            throw new LogicException("Request [{$request->getKey()}] is not answered yet or was already appealed.");
        }

        $rules = $this->rules($request);
        $due = $rules?->appealDeadline(now());

        if ($due === null) {
            throw new LogicException("The law of [{$request->state}] has no appeal process.");
        }

        $request->forceFill([
            'status' => RequestStatus::Appealed,
            'appealed_at' => now(),
            'appeal_reason' => $reason,
            'appeal_due_at' => $due,
        ])->save();

        event(new ConsumerRequestAppealed($request));

        return $request;
    }

    /**
     * Extend the appeal decision once where the state allows it (Colorado, Oregon, Minnesota).
     */
    public function extendAppeal(ConsumerRequest $request, string $reason): ConsumerRequest
    {
        $days = $this->rules($request)->appealExtensionDays ?? 0;

        if ($request->status !== RequestStatus::Appealed || $request->appeal_extended_at !== null || $days === 0) {
            throw new LogicException("The appeal of request [{$request->getKey()}] cannot be extended.");
        }

        if ($request->appeal_due_at?->isPast()) {
            throw new LogicException("The appeal of request [{$request->getKey()}] is past its deadline.");
        }

        $request->forceFill([
            'appeal_due_at' => $request->appeal_due_at?->copy()->addDays($days),
            'appeal_extended_at' => now(),
            'extension_reason' => trim(($request->extension_reason ?? '')."\nAppeal: {$reason}"),
        ])->save();

        return $request;
    }

    /**
     * Decide the appeal in writing, with the reasons. Granted: the request is pending again, to
     * be answered by the appeal deadline. Denied: it closes; the response must tell the consumer
     * how to contact their attorney general to submit a complaint.
     */
    public function decideAppeal(ConsumerRequest $request, bool $granted, string $response): ConsumerRequest
    {
        if ($request->status !== RequestStatus::Appealed) {
            throw new LogicException("Request [{$request->getKey()}] has no pending appeal.");
        }

        $request->forceFill([
            'appeal_result' => $granted ? 'granted' : 'denied',
            'appeal_response' => $response,
            'appeal_decided_at' => now(),
            'status' => $granted ? RequestStatus::Pending : RequestStatus::Rejected,
            'due_at' => $granted ? ($request->appeal_due_at ?? Carbon::now()) : $request->due_at,
            'completed_at' => $granted ? null : now(),
        ])->save();

        event(new AppealDecided($request));

        return $request;
    }

    /**
     * Every declared personal data of the subject, keyed by table (access and portability).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function export(Model $subject): array
    {
        $data = [];

        foreach ($this->records($subject) as $record) {
            $data[$record->getTable()][] = $record->exportPersonalData();
        }

        return $data;
    }

    /**
     * @return string the path on the configured disk
     */
    public function exportToFile(Model $subject, string $filename): string
    {
        $path = trim((string) config('us-privacy-requests.directory', 'us-privacy/requests'), '/').'/'.$filename;

        Storage::disk(config('us-privacy-requests.disk'))->put(
            $path,
            (string) json_encode($this->export($subject), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $path;
    }

    /**
     * Anonymize every record of the subject with each column's strategy (Keep columns stay: exceptions to deletion).
     *
     * @return int records anonymized
     */
    public function erase(Model $subject): int
    {
        return DB::transaction(function () use ($subject): int {
            $count = 0;

            foreach ($this->records($subject) as $record) {
                $record->anonymizePersonalData();
                $count++;
            }

            return $count;
        });
    }

    /**
     * Records of us-privacy.models that belong to the subject: the subject itself, plus ResolvesConsumer models.
     *
     * @return iterable<int, Model&HasPersonalData>
     */
    public function records(Model $subject): iterable
    {
        foreach ($this->privacy->models() as $model) {
            if ($subject instanceof $model) {
                yield $subject;

                continue;
            }

            if (! is_subclass_of($model, ResolvesConsumer::class)) {
                continue;
            }

            /** @var Builder<Model&HasPersonalData> $query */
            $query = $model::consumerQuery($subject);

            foreach ($query->lazyById() as $record) {
                yield $record;
            }
        }
    }

    protected function rules(ConsumerRequest $request): ?StateRules
    {
        return $this->privacy->rulesFor($request->state);
    }

    protected function close(ConsumerRequest $request, RequestStatus $status, ?string $response): ConsumerRequest
    {
        if (! $request->isOpen()) {
            throw new LogicException("Request [{$request->getKey()}] is already closed.");
        }

        $request->forceFill([
            'status' => $status,
            'response' => $response,
            'completed_at' => now(),
        ])->save();

        event(new ConsumerRequestClosed($request));

        return $request;
    }
}
