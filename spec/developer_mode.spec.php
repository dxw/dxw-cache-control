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
});
