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
		if ($this->active()) {
			foreach ($this->headers as $key => $value) {
				header(self::PREFIX . $key . ": " . $value);
			}
		}
	}

	public function active(): bool
	{
		$result = false;
		if (wp_get_environment_type() === 'local' || wp_get_environment_type() === 'development') {
			$result = get_field('cache_control_plugin_developer_mode', 'option') ?? false;
		}
		return $result;
	}
}
