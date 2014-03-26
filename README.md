Laravel Extended Validator
==========================

# Introduction

This package was created for several purposes:

1.  To ease the creation of validation services - we all know validaton should be moved out of the controller 
2.  Provide an easy way to provide context for validations - such as providing a modified set of validation rules for edit vs. create
3.  Provide an easy way to group validations - that is, calling one 'passes()' method to validate multiple models 

# Installation

Install this package through Composer. Add the following to your composer.json file:
```php
"require": {
    "crhayes/validation": "dev-master"
}
```
Next, run composer install

Finally, add the service provider and the facade to app/config/app.php.
```php
'providers' => array(
    // ...

    'Crhayes\Validation\ValidationServiceProvider'
)

'aliases' => array(
    // ...

    'GroupedValidator' => 'Crhayes\Validation\Facade'
)
```

# Validation as a Service

## Most Basic Validator

To create a validation service simply extend the base abstract ContextualValidation class and provide an array of rules and messages (messages will override Laravel's default validation messages).
```php
<?php

namespace App\Services\Validators;

use Crhayes\Validation\ContextualValidator;

class UserValidator extends ContextualValidator
{
    protected $rules = [
        'first_name' => 'required',
        'last_name' => 'required'
    ]

    protected $messages = [
        'first_name.required' => 'First name is required!'
    ]
}
```
This service is then instantiated and the validation works much the same as Laravel's built-in validation.
```php
use App\Services\Validators\UserValidator;

...

$userValidator = new UserValidator(Input::all());
OR
$userValidator = UserValidator::make(Input::all());

...

if ($userValidator->passes()) {
  // yay, successful validation
}

// nay, get the errors
$errors = $userValidator->errors();
```
# Contextual Validation

## Providing Contexts

Sometimes you need to have different rules for the same model depending on the context you are using it within. For instance, maybe some fields are mandatory during registration, but may not be when the user is editing their profile. 

We can turn our rules array into a multidimensional array, with unique keys for each of our contexts. The default key denotes our default rules, which will be used for every validation. Default rules can be overridden very easily by setting the rule in other contexts.
```php
<?php

namespace App\Services\Validators;

use Crhayes\Validation\ContextualValidator;

class UserValidator extends ContextualValidator
{
    protected $rules = [
        'default' => [
            'first_name' => 'required',
            'last_name' => 'required'
        ],
        'create' => [
            'first_name' => 'required|max:255' // override default
        ],
        'edit' => [
            'website' => 'required|url' // add a new rule for website while editing
        ]
    ]
}
```
Let's see how we can use one of the contexts during the creation of a user:
```php
use App\Services\Validators\UserValidator;

...

$userValidator = UserValidator::make(Input::all())->addContext('create');

if ($userValidator->passes()) {
  // yay, successful validation
}

// nay, get the errors
$errors = $userValidator->errors();
```
While editing a user, we would do this:
```php
use App\Services\Validators\UserValidator;

$userValidator = UserValidator::make(Input::all())->addContext('edit');

if ($userValidator->passes()) {
  // yay, successful validation
}

// nay, get the errors
$errors = $userValidator->errors();
```
Multiple contexts can be added as well:
```php
use App\Services\Validators\UserValidator;

$userValidator = UserValidator::make(Input::all())
    ->addContext(['create', 'profile']);

// or chained
$userValidator->addContext('create')->addContext('profile');
```
## Providing Data to Contexts

Sometimes you need to provide data to your rules. For instance, you may have a unique rule on a field, but when editing the record you don't want an error to be thrown when the user submits a form with the value they previously had.

Luckily, we have a way to handle these instances.
```php
<?php

namespace App\Services\Validators;

use Crhayes\Validation\ContextualValidator;

class UserValidator extends ContextualValidator
{
    protected $rules = [
        'default' => [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email'
        ],
        'edit' => [
            'email' => 'required|email|unique:users,email,@id'
        ]
    ]
} 
```
Notice that we have set the email field to be unique by default. However, when the users submits an edit form we want to ignore the current user's id. We use the @id placeholder, and then replace that with the desired value:
```php
$userValidator = UserValidator::make(Input::all())
    ->addContext('edit')
    ->bindReplacement('email', ['id' => $currentUser->id]);
```
The method bindReplacement() takes the rule name as the first parameter; the second parameter is an array of bindings, with the key being the placeholder and value being the replacement value.

# Grouped Validator

Have you ever had a form submit data that required two different models be created or updated? Validating both models can be a pain, but that's where the GroupedValidator class comes in.

Let's say we posted a form that is going to create a new user and their car; we therefore need to validate both a user and a car model.
```php
use App\Services\Validators\UserValidator;
use App\Services\Validators\CarValidator;
use Crhayes\Validation\GroupedValidator;

$userValidator = UserValidator::make(Input::only('first_name', 'last_name'));
$carValidator = CarValidator::make(Input::only('make', 'model'));

$groupedValidator = GroupedValidator::make()
    ->addValidator($userValidator)
    ->addValidator($carValidator);

if ($groupedValidator->passes()) {
  // yay, successful validation
}

// nay, get the errors
$errors = $groupedValidator->errors(); // return errors for all validators
```
We can use validator contexts with grouped validation as well:
```php
$userValidator = UserValidator::make(Input::only('first_name', 'last_name'))
    ->addContext('create');
$carValidator = CarValidator::make(Input::only('make', 'model'))
    ->addContext('create');

$groupedValidator = GroupedValidator::make()
    ->addValidator($userValidator)
    ->addValidator($carValidator);
```
We can also mix and match ContextualValidator services and native Laravel Validators:
```php
$userValidator = UserValidator::make(Input::only('first_name', 'last_name'));
$carValidator = Validator::make(Input::only('make', 'model'), [
    'make' => 'required',
    'model' => 'required'
]);

$groupedValidator = GroupedValidator::make()
    ->addValidator($userValidator)
    ->addValidator($carValidator);
```

# Adding Custom Complex Validation
Adding complex validation to your validation services is as easy as follows:
```php
<?php

namespace App\Services\Validators;

use Crhayes\Validation\ContextualValidator;

class UserValidator extends ContextualValidator
{
    protected $rules = [
        'default' => [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email'
        ],
        'edit' => [
            'email' => 'required|email|unique:users,email,@id'
        ]
    ]
    
    protected function addConditionalRules($validator)
    {
        $validator->sometimes('some_field', 'required' function($input)
        {
            // perform a check
        });
    }
} 
```
All we do is add the ```addConditionalRules($validator)``` method to our validation service, which accepts an instance of ```Illuminate\Validation\Validator```, allowing us to utilize Laravel's built-in support for complex validations.
