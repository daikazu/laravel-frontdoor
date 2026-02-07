<?php

use Daikazu\LaravelFrontdoor\Support\RegistrationField;

it('constructs with defaults', function () {
    $field = new RegistrationField(name: 'email', label: 'Email');

    expect($field->name)->toBe('email');
    expect($field->label)->toBe('Email');
    expect($field->type)->toBe('text');
    expect($field->required)->toBeFalse();
    expect($field->rules)->toBe([]);
    expect($field->options)->toBe([]);
});

it('constructs with all params including select options', function () {
    $field = new RegistrationField(
        name: 'country',
        label: 'Country',
        type: 'select',
        required: true,
        rules: ['string', 'in:us,ca,uk'],
        options: ['us' => 'United States', 'ca' => 'Canada', 'uk' => 'United Kingdom'],
    );

    expect($field->name)->toBe('country');
    expect($field->label)->toBe('Country');
    expect($field->type)->toBe('select');
    expect($field->required)->toBeTrue();
    expect($field->rules)->toBe(['string', 'in:us,ca,uk']);
    expect($field->options)->toBe(['us' => 'United States', 'ca' => 'Canada', 'uk' => 'United Kingdom']);
});

it('toArray returns correct structure', function () {
    $field = new RegistrationField(
        name: 'phone',
        label: 'Phone number',
        type: 'tel',
        required: false,
        rules: ['string', 'max:20'],
    );

    expect($field->toArray())->toBe([
        'name' => 'phone',
        'label' => 'Phone number',
        'type' => 'tel',
        'required' => false,
        'rules' => ['string', 'max:20'],
        'options' => [],
    ]);
});
