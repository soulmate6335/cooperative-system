<?php

require dirname(__DIR__).'/vendor/autoload.php';

$testVariables = [
    'DB_TEST_HOST',
    'DB_TEST_PORT',
    'DB_TEST_DATABASE',
    'DB_TEST_USERNAME',
    'DB_TEST_PASSWORD',
];

$missingVariables = array_values(array_filter(
    $testVariables,
    fn (string $variable): bool => getenv($variable) === false,
));

if ($missingVariables !== []) {
    throw new RuntimeException('PostgreSQL test configuration is incomplete. Set: '.implode(', ', $missingVariables));
}

$environment = [
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => getenv('DB_TEST_HOST'),
    'DB_PORT' => getenv('DB_TEST_PORT'),
    'DB_DATABASE' => getenv('DB_TEST_DATABASE'),
    'DB_USERNAME' => getenv('DB_TEST_USERNAME'),
    'DB_PASSWORD' => getenv('DB_TEST_PASSWORD'),
    'DB_URL' => '',
];

foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
