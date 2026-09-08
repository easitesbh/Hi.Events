<?php

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\QuestionAndAnswerViewDomainObject;
use HiEvents\Repository\Interfaces\QuestionAndAnswerViewRepositoryInterface;
use Illuminate\Config\Repository;
use Throwable;

class OrderTaxNumberResolver
{
    public function __construct(
        private readonly QuestionAndAnswerViewRepositoryInterface $questionAndAnswerViewRepository,
        private readonly Repository $config,
    ) {}

    public function resolveForOrder(?int $orderId): ?string
    {
        if ($orderId === null) {
            return null;
        }

        $label = trim((string) $this->config->get('app.invoice_tax_number_question_label'));

        if ($label === '') {
            return null;
        }

        try {
            $answers = $this->questionAndAnswerViewRepository->findWhere([
                'order_id' => $orderId,
            ]);
        } catch (Throwable) {
            return null;
        }

        foreach ($answers as $answer) {
            if (! $answer instanceof QuestionAndAnswerViewDomainObject) {
                continue;
            }

            if ($answer->getAttendeeId() !== null) {
                continue;
            }

            if (! str_starts_with(mb_strtolower($answer->getTitle()), mb_strtolower($label))) {
                continue;
            }

            $value = $answer->getAnswer();

            if (is_array($value)) {
                $value = implode(' ', array_filter($value, static fn ($item) => is_scalar($item)));
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
