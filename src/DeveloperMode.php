<?php

namespace CacheControl;

class DeveloperMode
{
	public const PREFIX = 'X-Debug-dxw-Cache-Control-';
	protected array $headers = [];

	public function addHeader(string $key, int|string $value): void
	{
		$this->headers[$key] = $value;
	}

	public function output(): void
	{
		foreach ($this->headers as $key => $value) {
			header(self::PREFIX . $key . ": " . $value);
		}
	}
}
