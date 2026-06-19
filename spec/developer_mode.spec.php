<?php

describe(\CacheControl\DeveloperMode::class, function () {
	beforeEach(function () {
		$this->developerMode = new \CacheControl\DeveloperMode();
	});
	describe('->addHeader()', function () {
		it('allows string values to be added', function () {
			$closure = function () {
				$this->developerMode->addHeader('foo', 'bar');
			};
			expect($closure)->not->toThrow(new \TypeError());
		});
		it('allows integer values to be added', function () {
			$closure = function () {
				$this->developerMode->addHeader('foo', 123);
			};
			expect($closure)->not->toThrow(new \TypeError());
		});
		it('does not allow a non-integer or string type value to be added', function () {
			$closure = function () {
				$this->developerMode->addHeader('foo', new stdClass());
			};
			expect($closure)->toThrow(new \TypeError());
		});
	});
	describe('->output()', function () {
		context('developer mode is active', function () {
			beforeEach(function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('local');
				allow('get_field')->toBeCalled()->andReturn(true);
			});
			it('outputs no headers when none have been added', function () {
				expect('header')->not->toBeCalled();
				$this->developerMode->output();
			});
			it('outputs headers when they have been added', function () {
				allow('header')->toBeCalled();
				$this->developerMode->addHeader('foo', 'bar');
				$this->developerMode->addHeader('moo', 123);
				expect('header')->toBeCalled()->once()->with(CacheControl\DeveloperMode::PREFIX . "foo: bar");
				expect('header')->toBeCalled()->once()->with(CacheControl\DeveloperMode::PREFIX . "moo: 123");
				$this->developerMode->output();
			});
		});
		context('developer mode is inactive', function () {
			beforeEach(function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('production');
			});
			it('does nothing', function () {
				$this->developerMode->addHeader('foo', 'bar');
				$this->developerMode->addHeader('moo', 123);
				expect('header')->not->toBeCalled();
				$this->developerMode->output();
			});
		});
	});

	describe('->active()', function () {
		context('environment type is local', function () {
			it('returns the option value', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('local');
				allow('get_field')->toBeCalled()->andReturn(true);
				expect($this->developerMode->active())->toEqual(true);
			});
		});
		context('environment type is development', function () {
			it('returns the option value', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('development');
				allow('get_field')->toBeCalled()->andReturn(true);
				expect($this->developerMode->active())->toEqual(true);
			});
		});
		context('environment type is anything else', function () {
			it('returns false', function () {
				allow('wp_get_environment_type')->toBeCalled()->andReturn('production');
				expect('get_field')->not->toBeCalled();
				expect($this->developerMode->active())->toEqual(false);
			});
		});
		it('memo-ises the value so only needs to call logic once', function () {
			allow('wp_get_environment_type')->toBeCalled()->andReturn('local');
			expect('wp_get_environment_type')->toBeCalled()->once();
			allow('get_field')->toBeCalled()->andReturn(true);
			expect('get_field')->toBeCalled()->once();
			expect($this->developerMode->active())->toEqual(true);
			expect($this->developerMode->active())->toEqual(true);
		});
	});
});
