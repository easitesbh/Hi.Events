<?php

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\QuestionDomainObject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validates the question answers submitted when an attendee is created manually
 * from the organiser dashboard. Mirrors OrderQuestionRule, but the payload lives
 * at the top level (`questions`) instead of `order.questions`.
 */
class ManualAttendeeQuestionRule extends BaseQuestionRule
{
    /**
     * @throws ValidationException
     */
    protected function validateRequiredQuestionArePresent(Collection $answers): void
    {
        $requiredQuestionIds = $this->questions
            ->filter(fn (QuestionDomainObject $question) => $question->getRequired())
            ->filter(fn (QuestionDomainObject $question) => ! $question->getIsHidden())
            ->map(fn (QuestionDomainObject $question) => $question->getId());

        $answeredQuestionIds = $answers
            ->map(fn ($answer) => isset($answer['question_id']) ? (int) $answer['question_id'] : null)
            ->filter()
            ->toArray();

        if (array_diff($requiredQuestionIds->toArray(), $answeredQuestionIds)) {
            throw ValidationException::withMessages([
                'questions' => __('Required questions have not been answered. You may need to reload the page.'),
            ]);
        }
    }

    protected function validateQuestions(mixed $questions): array
    {
        $validationMessages = [];

        foreach ($questions as $index => $questionData) {
            $questionId = isset($questionData['question_id']) ? (int) $questionData['question_id'] : null;
            $questionDomainObject = $this->getQuestionDomainObject($questionId);
            $key = 'questions.'.$index.'.response';
            $response = $questionData['response'] ?? null;
            $answer = $response['answer'] ?? $response;

            if (! $questionDomainObject) {
                $validationMessages[$key.'.answer'][] = __('This question is outdated. Please reload the page.');

                continue;
            }

            if (is_null($response) && ! $questionDomainObject->getRequired()) {
                continue;
            }

            if ($questionDomainObject->getRequired()) {
                $validationMessages = $this->validateRequiredFields($questionDomainObject, $response, $key, $validationMessages);
            }

            if (! $questionDomainObject->isAnswerValid($answer)) {
                $validationMessages[$key.'.answer'][] = __('Please select an option');
            }

            $validationMessages = $this->validateResponseLength($questionDomainObject, $response, $key, $validationMessages);
        }

        return $validationMessages;
    }
}
