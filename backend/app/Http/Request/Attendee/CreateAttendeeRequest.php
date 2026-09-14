<?php

namespace HiEvents\Http\Request\Attendee;

use HiEvents\DomainObjects\Enums\QuestionBelongsTo;
use HiEvents\DomainObjects\Generated\QuestionDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use HiEvents\Repository\Interfaces\QuestionRepositoryInterface;
use HiEvents\Validators\Rules\ManualAttendeeQuestionRule;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class CreateAttendeeRequest extends BaseRequest
{
    public function rules(): array
    {
        $eventId = $this->route('event_id');

        return [
            'product_id' => ['int', 'required'],
            'event_occurrence_id' => ['int', 'nullable', Rule::exists('event_occurrences', 'id')->where('event_id', $eventId)->whereNull('deleted_at')],
            'product_price_id' => ['int', 'nullable'],
            'email' => ['required', 'email'],
            'first_name' => ['string', 'required', 'max:40'],
            'last_name' => ['string', 'max:40'],
            'amount_paid' => ['required', ...RulesHelper::MONEY],
            'send_confirmation_email' => ['required', 'boolean'],
            'taxes_and_fees' => ['array'],
            'taxes_and_fees.*.tax_or_fee_id' => ['required', 'int'],
            'taxes_and_fees.*.amount' => ['required', ...RulesHelper::MONEY],
            'locale' => ['required', Rule::in(Locale::getSupportedLocales())],
            'override_capacity' => ['boolean', 'sometimes'],
            'questions' => ['sometimes', 'bail', 'array', new ManualAttendeeQuestionRule($this->getApplicableQuestions($eventId), new Collection)],
            'questions.*.question_id' => ['required', 'int'],
        ];
    }

    /**
     * The questions an attendee created from the dashboard can answer: every order-level
     * question, plus the product-level questions attached to the selected ticket.
     */
    private function getApplicableQuestions(mixed $eventId): Collection
    {
        $productId = (int) $this->input('product_id');

        return app(QuestionRepositoryInterface::class)
            ->loadRelation(ProductDomainObject::class)
            ->findWhere([QuestionDomainObjectAbstract::EVENT_ID => $eventId])
            ->filter(
                fn (QuestionDomainObject $question) => $question->getBelongsTo() === QuestionBelongsTo::ORDER->name
                    || (bool) $question->getProducts()
                        ?->map(fn (ProductDomainObject $product) => $product->getId())
                        ->contains($productId)
            );
    }
}
