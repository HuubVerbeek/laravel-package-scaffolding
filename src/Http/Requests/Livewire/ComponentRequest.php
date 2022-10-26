<?php

namespace BRISP\LivewireComponentRequests\Http\Requests\Livewire;

use BRISP\LivewireComponentRequests\Traits\HasErrorBag;
use BRISP\LivewireComponentRequests\Traits\HasStaticConstruction;
use BRISP\LivewireComponentRequests\Traits\ValidatesComponent;
use Livewire\Component;

abstract class ComponentRequest
{
    use ValidatesComponent,
        HasErrorBag,
        HasStaticConstruction;

    /**
     * @param  Component  $component
     */
    public function __construct(public Component $component)
    {
        //
    }

    /**
     * @return bool
     */
    abstract public function authorize(): bool;

    /**
     * @return array
     */
    abstract public function rules(): array;
}
