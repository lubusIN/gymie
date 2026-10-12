<?php

namespace App\Services\Communication;

use App\Enums\CommunicationDeliveryStatus;
use App\Enums\CommunicationTemplateKey;
use App\Jobs\SendCommunicationEmail;
use App\Mail\CommunicationMail;
use App\Models\CommunicationDelivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CommunicationService
{
    public function __construct(
        private readonly CommunicationTemplateRenderer $templateRenderer,
        private readonly CommunicationDeliveryTracker $deliveryTracker,
        private readonly EmailDeliveryContext $emailContext,
        private readonly CommunicationPreferenceService $preferenceService,
    ) {}

    /**
     * Record and queue a template-based email.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function queue(
        CommunicationTemplateKey|string $templateKey,
        string $recipient,
        array $variables = [],
        ?Model $related = null,
        ?int $actorId = null,
        bool $isTest = false,
    ): CommunicationDelivery {
        $delivery = $this->deliveryTracker->queued(
            $templateKey,
            $recipient,
            $related,
            $actorId,
            [
                'handler' => RetryCommunicationDelivery::COMMUNICATION_HANDLER,
                'variables' => $variables,
            ],
            $isTest,
        );

        if ($delivery->status === CommunicationDeliveryStatus::Skipped) {
            return $delivery;
        }

        if (! $this->emailContext->isDeliveryConfigured()) {
            $this->deliveryTracker->skipped($delivery->getKey(), $this->emailContext->unavailableReason());

            return $delivery->refresh();
        }

        SendCommunicationEmail::dispatch(
            deliveryId: (int) $delivery->getKey(),
        )->afterCommit();

        return $delivery;
    }

    /**
     * Deliver a queued template-based email.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function send(int $deliveryId, array $variables = []): void
    {
        $delivery = CommunicationDelivery::query()
            ->with(['template', 'related'])
            ->find($deliveryId);

        if (
            $delivery === null
            || in_array($delivery->status, [CommunicationDeliveryStatus::Sent, CommunicationDeliveryStatus::Skipped], true)
        ) {
            return;
        }

        $optOutReason = $this->preferenceService->emailOptOutReason($delivery->related);

        if ($delivery->channel === 'email' && $optOutReason !== null) {
            $this->deliveryTracker->skipped($deliveryId, $optOutReason);

            return;
        }

        if ($variables === []) {
            $storedVariables = data_get($delivery->retry_payload, 'variables', []);
            $variables = is_array($storedVariables) ? $storedVariables : [];
        }

        $recipient = $delivery->recipient;

        if (! $this->emailContext->isDeliveryConfigured()) {
            $this->deliveryTracker->skipped($deliveryId, $this->emailContext->unavailableReason());

            return;
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->deliveryTracker->skipped($deliveryId, 'Recipient email is invalid.');
            Log::warning('Skipping communication email: invalid recipient email.', ['delivery_id' => $deliveryId]);

            return;
        }

        $settings = $this->emailContext->settings();

        try {
            $this->emailContext->withLocale($settings, function () use ($delivery, $deliveryId, $recipient, $variables, $settings): void {
                $identity = $this->emailContext->identity($settings);
                $rendered = $this->templateRenderer->render($delivery->template, [
                    ...$variables,
                    'gym_name' => $identity['name'],
                ]);
                $subject = $delivery->is_test
                    ? __('app.communication.test_subject', ['subject' => $rendered['subject']])
                    : $rendered['subject'];

                Mail::to($recipient)->send(new CommunicationMail(
                    subjectLine: $subject,
                    messageBody: $rendered['body'],
                    fromAddress: $this->emailContext->fromAddress(),
                    gymName: $identity['name'],
                    gymEmail: $identity['email'],
                    gymContact: $identity['contact'],
                ));

                $this->deliveryTracker->sent($deliveryId, $subject);
            });
        } catch (Throwable $exception) {
            $this->deliveryTracker->failed($deliveryId, $exception);

            throw $exception;
        }
    }
}
