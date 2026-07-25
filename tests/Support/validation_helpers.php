<?php
declare(strict_types=1);

use Cake\Validation\Validator;

/**
 * Build a validator for purchase tool/prompt/resource feature tests.
 *
 * @return \Cake\Validation\Validator
 */
function mcpPurchaseValidator(): Validator
{
    $validator = new Validator();
    $validator
        ->integer('id')
        ->requirePresence('id', true, 'The id field is required.')
        ->notEmptyString('id', 'The id field is required.')
        ->integer('quantity')
        ->requirePresence('quantity', true, 'The quantity field is required.')
        ->notEmptyString('quantity', 'The quantity field is required.')
        ->greaterThanOrEqual('quantity', 1)
        ->lessThanOrEqual('quantity', 5);

    return $validator;
}

/**
 * Build a validator requiring a non-empty name argument.
 *
 * @return \Cake\Validation\Validator
 */
function mcpRequiredNameValidator(): Validator
{
    $validator = new Validator();
    $validator
        ->scalar('name')
        ->requirePresence('name', true, 'The name field is required.')
        ->notEmptyString('name', 'The name field is required.');

    return $validator;
}

/**
 * Build a validator requiring a non-empty id argument.
 *
 * @return \Cake\Validation\Validator
 */
function mcpRequiredIdValidator(): Validator
{
    $validator = new Validator();
    $validator
        ->scalar('id')
        ->requirePresence('id', true, 'The id field is required.')
        ->notEmptyString('id', 'The id field is required.');

    return $validator;
}

/**
 * Build a validator requiring a boolean should_fail argument.
 *
 * @return \Cake\Validation\Validator
 */
function mcpRequiredBooleanValidator(string $field): Validator
{
    $validator = new Validator();
    $validator
        ->boolean($field)
        ->requirePresence($field, true, "The {$field} field is required.")
        ->notEmptyString($field, "The {$field} field is required.");

    return $validator;
}
