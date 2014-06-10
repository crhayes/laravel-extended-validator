<?php

namespace Crhayes\Validation;

use Crhayes\Validation\Exceptions\ReplacementBindingException;
use Crhayes\Validation\Exceptions\ValidatorContextException;
use Illuminate\Support\Contracts\MessageProviderInterface;
use Input;
use Validator;

abstract class ContextualValidator implements MessageProviderInterface
{
	const DEFAULT_KEY = 'default';

	/**
	 * Store the attributes we are validating.
	 * 
	 * @var array
	 */
	protected $attributes = array();

	/**
	 * Store the validation rules.
	 * 
	 * @var array
	 */
	protected $rules = array();

	/**
	 * Store any custom messages for validation rules.
	 * 
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Store any contexts we are validating within.
	 * 
	 * @var array
	 */
	protected $contexts = array();

	/**
	 * Store replacement values for any bindings in our rules.
	 * 
	 * @var array
	 */
	protected $replacements = array();

	/**
	 * Store any validation messages generated.
	 * 
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Our constructor will store the attributes we are validating, and
	 * may also take as a second parameter the contexts within which 
	 * we are validating.
	 * 
	 * @param array 	$attributes
	 * @param mixed 	$context
	 */
	public function __construct($attributes = null, $context = null)
	{
		$this->attributes = $attributes ?: Input::all();

		if ($context) $this->addContext($context);
	}

	/**
	 * Static shorthand for creating a new validator.
	 * 
	 * @param  mixed 	$validator
	 * @return Crhayes\Validation\GroupedValidator
	 */
	public static function make($attributes = null, $context = null)
	{
		return new static($attributes, $context);
	}

	/**
	 * Stub method that can be extended by child classes.
	 * Passes a validator object and allows for adding complex conditional validations.
	 * 
	 * @param \Illuminate\Validation\Validator $validator
	 */
	protected function addConditionalRules($validator) {}

	/**
	 * Set the validation attributes.
	 *
	 * @param  array $attributes
	 * @return Crhayes\Validation\GroupedValidator
	 */
	public function setAttributes($attributes = null)
	{
		$this->attributes = $attributes ?: Input::all();

		return $this;
	}

	/**
	 * Retrieve the validation attributes.
	 *
	 * @return array
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * Add a validation context.
	 * 
	 * @param array 	$context
	 */
	public function addContext($context)
	{
		$context = is_array($context) ? $context : array($context);
		
		$this->contexts = array_merge($this->contexts, $context);

		return $this;
	}

	/**
	 * Set the validation context.
	 *
	 * @param  array|string $context
	 * @return Crhayes\Validation\GroupedValidator
	 */
	public function setContext($context)
	{
		$this->contexts = is_array($context) ? $context : array($context);

		return $this;
	}

	/**
	 * Retrieve the valiation context.
	 * 
	 * @return array
	 */
	public function getContexts()
	{
		return $this->contexts;
	}

	/**
	 * Bind a replacement value to a placeholder in a rule.
	 * 
	 * @param  string 	$field
	 * @param  array 	$replacement
	 * @return Crhayes\Validation\ContextualValidator
	 */
	public function bindReplacement($field, array $replacement)
	{
		$this->replacements[$field] = $replacement;

		return $this;
	}

	/**
	 * Get a bound replacement by key.
	 * 
	 * @param  string $key
	 * @return array
	 */
	public function getReplacement($key)
	{
		return array_get($this->replacements, $key, array());
	}

	/**
	 * Perform a validation check against our attributes.
	 * 
	 * @return boolean
	 */
	public function passes()
	{
		$rules = $this->bindReplacements($this->getRulesInContext());

		$validator = Validator::make($this->attributes, $rules, $this->messages);

		$this->addConditionalRules($validator);

		if ($validator->passes()) return true;

		$this->errors = $validator->messages();

		return false;
	}

	/**
	 * Determine if the data fails the validation rules.
	 *
	 * @return bool
	 */
	public function fails()
	{
		return ! $this->passes();
	}

	/**
	 * Get the messages for the instance.
	 *
	 * @return \Illuminate\Support\MessageBag
	 */
	public function getMessageBag()
	{
		return $this->errors();
	}

	/**
	 * Return any errors.
	 * 
	 * @return Illuminate\Support\MessageBag
	 */
	public function errors()
	{
		if ( ! $this->errors) $this->passes();

		return $this->errors;
	}

	/**
	 * Get the validaton rules within the context of the current validation.
	 * 
	 * @return array
	 */
	protected function getRulesInContext()
	{
		if ( ! $this->hasContext())	return $this->rules;

		$rulesInContext = array_get($this->rules, self::DEFAULT_KEY, array());

		foreach ($this->contexts as $context)
		{
			if ( ! array_get($this->rules, $context))
			{
				throw new ValidatorContextException(
					sprintf(
						"'%s' does not contain the validation context '%s'", 
						get_called_class(), 
						$context
					)
				);
			}

			$rulesInContext = array_merge($rulesInContext, $this->rules[$context]);
		}

		return $rulesInContext;
	}

	/**
	 * Spin through our contextual rules array and bind any replacement
	 * values to placeholders within the rules.
	 * 
	 * @param  array 	$rules
	 * @return array
	 */
	protected function bindReplacements($rules)
	{
		foreach ($rules as $field => &$rule)
		{
			$replacements = $this->getReplacement($field);

			try
			{
				$rule = preg_replace_callback('/@(\w+)/', function($matches) use($replacements)
				{
					return $replacements[$matches[1]];
				}, $rule);
			}
			catch (\ErrorException $e)
			{
				$replacementCount = substr_count($rule, '@');

				throw new ReplacementBindingException(
					sprintf(
						"Invalid replacement count in rule '%s' for field '%s'; Expecting '%d' bound %s",
						$rule,
						$field,
						$replacementCount,
						str_plural('replacement', $replacementCount)
					)
				);
			}
		}

		return $rules;
	}

	/**
	 * Check if the current validation has a context.
	 * 
	 * @return boolean
	 */
	protected function hasContext()
	{
		return (count($this->contexts) OR array_get($this->rules, self::DEFAULT_KEY));
	}
}
