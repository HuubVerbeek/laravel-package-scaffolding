<?php

namespace BRISP\LivewireComponentRequests\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use function Livewire\invade;
use function Livewire\str;
use Livewire\Wireable;

trait ValidatesComponent
{
    /**
     * @var
     */
    protected $withValidatorCallback;

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    protected function passesAuthorization()
    {
        return method_exists($this, 'authorize') ? app()->call([$this, 'authorize']) : true;
    }

    /**
     * @return array
     */
    protected function getRules()
    {
        if (method_exists($this, 'rules')) {
            return $this->rules();
        }

        return [];
    }

    /**
     * @return array|mixed
     */
    protected function getMessages()
    {
        if (method_exists($this, 'messages')) {
            return $this->messages();
        }

        return [];
    }

    /**
     * @return array|mixed
     */
    protected function getValidationAttributes()
    {
        if (method_exists($this->component, 'validationAttributes')) {
            return $this->component->validationAttributes();
        }
        if (property_exists($this->component, 'validationAttributes')) {
            return $this->component->validationAttributes;
        }

        return [];
    }

    /**
     * @return array|mixed
     */
    protected function getValidationCustomValues()
    {
        if (method_exists($this->component, 'validationCustomValues')) {
            return $this->component->validationCustomValues();
        }
        if (property_exists($this->component, 'validationCustomValues')) {
            return $this->component->validationCustomValues;
        }

        return [];
    }

    /**
     * @param $name
     * @return Collection
     */
    public function rulesForModel($name)
    {
        if (empty($this->getRules())) {
            return collect();
        }

        return collect($this->getRules())
            ->filter(function ($value, $key) use ($name) {
                return $this->component->beforeFirstDot($key) === $name;
            });
    }

    /**
     * @param $dotNotatedProperty
     * @return bool
     */
    public function hasRuleFor($dotNotatedProperty)
    {
        $propertyWithStarsInsteadOfNumbers = $this->component->ruleWithNumbersReplacedByStars($dotNotatedProperty);

        // If property has numeric indexes in it,
        if ($dotNotatedProperty !== $propertyWithStarsInsteadOfNumbers) {
            return collect($this->getRules())->keys()->contains($propertyWithStarsInsteadOfNumbers);
        }

        return collect($this->getRules())
            ->keys()
            ->map(function ($key) {
                return (string) str($key)->before('.*');
            })->contains($dotNotatedProperty);
    }

    /**
     * @param $dotNotatedProperty
     * @return string
     */
    public function ruleWithNumbersReplacedByStars($dotNotatedProperty)
    {
        // Convert foo.0.bar.1 -> foo.*.bar.*
        return (string) str($dotNotatedProperty)
            // Replace all numeric indexes with an array wildcard: (.0., .10., .007.) => .*.
            // In order to match overlapping numerical indexes (foo.1.2.3.4.name),
            // We need to use a positive look-behind, that's technically all the magic here.
            // For better understanding, see: https://regexr.com/5d1n3
            ->replaceMatches('/(?<=(\.))\d+\./', '*.')
            // Replace all numeric indexes at the end of the name with an array wildcard
            // (Same as the previous regex, but ran only at the end of the string)
            // For better undestanding, see: https://regexr.com/5d1n6
            ->replaceMatches('/\.\d+$/', '.*');
    }

    /**
     * @param $dotNotatedProperty
     * @return bool
     */
    public function missingRuleFor($dotNotatedProperty)
    {
        return ! $this->hasRuleFor($dotNotatedProperty);
    }

    /**
     * @param $callback
     * @return Component
     */
    public function withValidator($callback)
    {
        $this->withValidatorCallback = $callback;

        return $this->component;
    }

    /**
     * @param $rules
     * @param $data
     * @return void
     *
     * @throws \Throwable
     */
    protected function checkRuleMatchesProperty($rules, $data)
    {
        collect($rules)
            ->keys()
            ->each(function ($ruleKey) use ($data) {
                throw_unless(
                    array_key_exists($this->component->beforeFirstDot($ruleKey), $data),
                    new \Exception('No property found for validation: ['.$ruleKey.']')
                );
            });
    }

    /**
     * @param $rules
     * @param $messages
     * @param $attributes
     * @return array
     *
     * @throws ValidationException
     * @throws \Throwable
     */
    public function validate($rules = null, $messages = [], $attributes = []): array
    {
        if (! $this->passesAuthorization()) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        [$rules, $messages, $attributes] = $this->providedOrGlobalRulesMessagesAndAttributes(
            $rules,
            $messages,
            $attributes
        );

        $data = $this->prepareForValidation(
            $this->getDataForValidation($rules)
        );

        $this->checkRuleMatchesProperty($rules, $data);

        $ruleKeysToShorten = $this->getModelAttributeRuleKeysToShorten($data, $rules);

        $data = $this->unwrapDataForValidation($data);

        $validator = Validator::make($data, $rules, $messages, $attributes);

        if ($this->withValidatorCallback) {
            call_user_func($this->withValidatorCallback, $validator);

            $this->withValidatorCallback = null;
        }

        $this->shortenModelAttributesInsideValidator($ruleKeysToShorten, $validator);

        $customValues = $this->getValidationCustomValues();
        if (! empty($customValues)) {
            $validator->addCustomValues($customValues);
        }

        $validatedData = $validator->validate();

        $this->component->resetErrorBag();

        return $validatedData;
    }

    /**
     * @param $field
     * @param $rules
     * @param $messages
     * @param $attributes
     * @return array
     *
     * @throws ValidationException
     * @throws \Throwable
     */
    public function validateOnly($field, $rules = null, $messages = [], $attributes = [])
    {
        if (! $this->passesAuthorization()) {
            throw new AuthorizationException(__('This action is unauthorized.'));
        }

        [$rules, $messages, $attributes] = $this->component->providedOrGlobalRulesMessagesAndAttributes(
            $rules,
            $messages,
            $attributes
        );

        // Loop through rules and swap any wildcard '*' with keys from field, then filter down to only
        // rules that match the field, but return the rules without wildcard characters replaced,
        // so that custom attributes and messages still work as they need wildcards to work.
        $rulesForField = collect($rules)
            ->filter(function ($value, $rule) use ($field) {
                if (! str($field)->is($rule)) {
                    return false;
                }

                $fieldArray = str($field)->explode('.');
                $ruleArray = str($rule)->explode('.');

                for ($i = 0; $i < count($fieldArray); $i++) {
                    if (isset($ruleArray[$i]) && $ruleArray[$i] === '*') {
                        $ruleArray[$i] = $fieldArray[$i];
                    }
                }

                $rule = $ruleArray->join('.');

                return $field === $rule;
            });

        $ruleForField = $rulesForField->keys()->first();

        $rulesForField = $rulesForField->toArray();

        $ruleKeysForField = array_keys($rulesForField);

        $data = $this->getDataForValidation($rules);

        $data = $this->prepareForValidation($data);

        $this->checkRuleMatchesProperty($rules, $data);

        $ruleKeysToShorten = $this->getModelAttributeRuleKeysToShorten($data, $rules);

        $data = $this->unwrapDataForValidation($data);

        // If a matching rule is found, then filter collections down to keys specified in the field,
        // while leaving all other data intact. If a key isn't specified and instead there is a
        // wildcard '*' then leave that whole collection intact. This ensures that any rules
        // that depend on other fields/ properties still work.
        if ($ruleForField) {
            $ruleArray = str($ruleForField)->explode('.');
            $fieldArray = str($field)->explode('.');

            $data = $this->component->filterCollectionDataDownToSpecificKeys($data, $ruleArray, $fieldArray);
        }

        $validator = Validator::make($data, $rulesForField, $messages, $attributes);

        if ($this->withValidatorCallback) {
            call_user_func($this->withValidatorCallback, $validator);

            $this->withValidatorCallback = null;
        }

        $this->component->shortenModelAttributesInsideValidator($ruleKeysToShorten, $validator);

        $customValues = $this->getValidationCustomValues();
        if (! empty($customValues)) {
            $validator->addCustomValues($customValues);
        }

        try {
            $result = $validator->validate();
        } catch (ValidationException $e) {
            $messages = $e->validator->getMessageBag();

            invade($e->validator)->messages = $messages->merge(
                $this->component->errorBagExcept($ruleKeysForField)
            );

            throw $e;
        }

        $this->component->resetErrorBag($ruleKeysForField);

        return $result;
    }

    /**
     * @param $data
     * @param $ruleKeys
     * @param $fieldKeys
     * @return array
     */
    protected function filterCollectionDataDownToSpecificKeys($data, $ruleKeys, $fieldKeys)
    {
        // Filter data down to specified keys in collections, but leave all other data intact
        if (count($ruleKeys)) {
            $ruleKey = $ruleKeys->shift();
            $fieldKey = $fieldKeys->shift();

            if ($fieldKey == '*') {
                // If the specified field has a '*', then loop through the collection and keep the whole collection intact.
                foreach ($data as $key => $value) {
                    $data[$key] = $this->component->filterCollectionDataDownToSpecificKeys(
                        $value,
                        $ruleKeys,
                        $fieldKeys
                    );
                }
            } else {
                // Otherwise filter collection down to a specific key
                $keyData = $data[$fieldKey];

                if ($ruleKey == '*') {
                    $data = [];
                }

                $data[$fieldKey] = $this->component->filterCollectionDataDownToSpecificKeys(
                    $keyData,
                    $ruleKeys,
                    $fieldKeys
                );
            }
        }

        return $data;
    }

    /**
     * @param $data
     * @param $rules
     * @return array
     */
    protected function getModelAttributeRuleKeysToShorten($data, $rules)
    {
        // If a model ($foo) is a property, and the validation rule is
        // "foo.bar", then set the attribute to just "bar", so that
        // the validation message is shortened and more readable.

        $toShorten = [];

        foreach ($rules as $key => $value) {
            $propertyName = $this->component->beforeFirstDot($key);

            if ($data[$propertyName] instanceof Model) {
                $toShorten[] = $key;
            }
        }

        return $toShorten;
    }

    /**
     * @param $ruleKeys
     * @param $validator
     * @return void
     */
    protected function shortenModelAttributesInsideValidator($ruleKeys, $validator)
    {
        foreach ($ruleKeys as $key) {
            if (str($key)->snake()->replace('_', ' ')->is($validator->getDisplayableAttribute($key))) {
                $validator->addCustomAttributes(
                    [$key => $validator->getDisplayableAttribute($this->component->afterFirstDot($key))]
                );
            }
        }
    }

    /**
     * @param $rules
     * @param $messages
     * @param $attributes
     * @return array
     *
     * @throws \Throwable
     */
    protected function providedOrGlobalRulesMessagesAndAttributes($rules, $messages, $attributes)
    {
        $rules = is_null($rules) ? $this->getRules() : $rules;

        throw_if(
            empty($rules),
            new \Exception('Missing [rules()] method in ComponentRequest class: '.get_class($this))
        );

        $messages = empty($messages) ? $this->getMessages() : $messages;
        $attributes = empty($attributes) ? $this->getValidationAttributes() : $attributes;

        return [$rules, $messages, $attributes];
    }

    /**
     * @param $rules
     * @return array
     */
    protected function getDataForValidation($rules)
    {
        return $this->component->getPublicPropertiesDefinedBySubClass();
    }

    /**
     * @param $data
     * @return array
     */
    protected function unwrapDataForValidation($data)
    {
        return collect($data)->map(function ($value) {
            if ($value instanceof Wireable) {
                return $value->toLivewire();
            } else {
                if ($value instanceof Collection || $value instanceof EloquentCollection || $value instanceof Model) {
                    return $value->toArray();
                }
            }

            return $value;
        })->all();
    }

    /**
     * @param $attributes
     * @return mixed
     */
    protected function prepareForValidation($attributes)
    {
        return $attributes;
    }
}
