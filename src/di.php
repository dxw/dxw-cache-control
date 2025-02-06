<?php

$registrar->addInstance(new \CacheControl\Page());
$registrar->addInstance(new \CacheControl\SendHeaders(
	$registrar->getInstance(\CacheControl\Page::class)
));
$registrar->addInstance(new \CacheControl\Options());
