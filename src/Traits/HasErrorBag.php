<?php

namespace BRISP\LivewireComponentRequests\Traits;

use Illuminate\Support\MessageBag;
use function Livewire\str;

trait HasErrorBag
{
    /**
     * @var
     */
    protected $errorBag;

    /**
     * @return MessageBag
     */
    public function getErrorBag()
    {
        return $this->errorBag ?? new MessageBag;
    }

    /**
     * @param $name
     * @param $message
     * @return MessageBag
     */
    public function addError($name, $message)
    {
        return $this->getErrorBag()->add($name, $message);
    }

    /**
     * @param $bag
     * @return MessageBag
     */
    public function setErrorBag($bag)
    {
        return $this->errorBag = $bag instanceof MessageBag
            ? $bag
            : new MessageBag($bag);
    }

    /**
     * @param $field
     * @return MessageBag|void
     */
    public function resetErrorBag($field = null)
    {
        $fields = (array) $field;

        if (empty($fields)) {
            return $this->errorBag = new MessageBag;
        }

        $this->setErrorBag(
            $this->errorBagExcept($fields)
        );
    }

    /**
     * @param $field
     * @return void
     */
    public function clearValidation($field = null)
    {
        $this->resetErrorBag($field);
    }

    /**
     * @param $field
     * @return void
     */
    public function resetValidation($field = null)
    {
        $this->resetErrorBag($field);
    }

    /**
     * @param $field
     * @return MessageBag
     */
    public function errorBagExcept($field)
    {
        $fields = (array) $field;

        return new MessageBag(
            collect($this->getErrorBag())
                ->reject(function ($messages, $messageKey) use ($fields) {
                    return collect($fields)->some(function ($field) use ($messageKey) {
                        return str($messageKey)->is($field);
                    });
                })
                ->toArray()
        );
    }
}
