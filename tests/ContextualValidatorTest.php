<?php

use Mockery as m;
use Crhayes\Validation\Tests\ConcreteValidator;

class ContextualValidatorTest extends PHPUnit_Framework_TestCase
{
	private $validator;

	private $input;

	public function tearDown()
	{
		m::close();
	}

	public function setUp()
	{
		$this->input = [
			'first_name' => 'Chris',
			'last_name'  => 'Hayes',
			'website'    => 'http://www.chrishayes.ca'
		];

		$this->validator = new ConcreteValidator($this->input);
	}

	public function testMakeMethodReturnsContextualValidator()
	{
		$this->assertInstanceOf('\Crhayes\Validation\ContextualValidator', ConcreteValidator::make($this->input));
	}

	public function testAddContextInConstructor()
	{
		$validator = new ConcreteValidator($this->input, 'signin');

		$this->assertEquals(['signin'], $validator->getContexts());
	}

	public function testAddContextInMakeMethod()
	{
		$validator = ConcreteValidator::make($this->input, ['signin', 'signin2']);

		$this->assertEquals(['signin', 'signin2'], $validator->getContexts());
	}

	public function testGetDefaultContext()
	{
		$this->assertEquals([], $this->validator->getContexts());
	}

	public function testAddSingleContextByString()
	{
		$this->validator->addContext('create');

		$this->assertEquals(['create'], $this->validator->getContexts());
	}

	public function testAddMultipleContextsByArray()
	{
		$this->validator->addContext(['create', 'edit']);
		$this->assertEquals(['create', 'edit'], $this->validator->getContexts());
	}

	public function testAddMultipleContextsByChaining()
	{
		$this->validator->addContext('create')->addContext('edit');
		$this->assertEquals(['create', 'edit'], $this->validator->getContexts());
	}

	public function testAddMultipleContextsByChainingArrays()
	{
		$this->validator->addContext(['create', 'create2'])->addContext(['edit', 'edit2']);
		$this->assertEquals(['create', 'create2', 'edit', 'edit2'], $this->validator->getContexts());
	}

	public function testSetContext()
	{
		$this->validator->addContext(['create']);
		$this->validator->setContext(['edit']);
		$this->assertEquals(['edit'], $this->validator->getContexts());
	}

	public function testGetAttributes()
	{
		$this->assertEquals($this->input, $this->validator->getAttributes());
	}

	public function testSetAttributes()
	{
		$this->validator->setAttributes(['website' => 'http://laravel.com']);
		$this->assertEquals(['website' => 'http://laravel.com'], $this->validator->getAttributes());
	}
}
