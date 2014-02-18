<?php

namespace Crhayes\Validation\Tests;

use Crhayes\Validation\ContextualValidator;

class ConcreteValidator extends ContextualValidator
{
	protected $rules = [
		'default' => [
			'first_name' => 'required',
			'last_name'  => 'required',
			'website'    => 'required|url'
		],
		'edit' => 'required|min:255'
	];
}
