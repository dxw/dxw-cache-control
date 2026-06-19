<?php

$registrar->addInstance(new \CacheControl\DeveloperMode());
$registrar->addInstance(new \CacheControl\Page());
$registrar->addInstance(new \CacheControl\SendHeaders(
	$registrar->getInstance(\CacheControl\Page::class),
	$registrar->getInstance(\CacheControl\DeveloperMode::class)
));
$registrar->addInstance(new \CacheControl\Options());
