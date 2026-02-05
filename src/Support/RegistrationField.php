<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

readonly class RegistrationField
{
    /**
     * @param  array<int, mixed>  $rules  Laravel validation rules (e.g. ['string', 'max:255'])
     * @param  array<string, string>  $options  For select type: ['value' => 'Label', ...]
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public bool $required = false,
        public array $rules = [],
        public array $options = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'rules' => $this->rules,
            'options' => $this->options,
        ];
    }
}
