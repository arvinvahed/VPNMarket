<?php

use App\Services\MarzneshinService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

beforeEach(function () {
    Log::shouldReceive('error')->andReturnNull();
    Log::shouldReceive('info')->andReturnNull();
});

test('constructor initializes properties correctly', function () {
    $service = new MarzneshinService(
        'https://example.com/',
        'admin',
        'password',
        'https://node.example.com'
    );

    expect($service)->toBeInstanceOf(MarzneshinService::class);
});

test('login returns true on successful authentication', function () {
    Http::fake([
        '*/api/admins/token' => Http::response([
            'access_token' => 'test-token-123',
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->login();

    expect($result)->toBeTrue();
});

test('login returns false on failed authentication', function () {
    Http::fake([
        '*/api/admins/token' => Http::response([
            'detail' => 'Invalid credentials',
        ], 401),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'wrong-password',
        'https://node.example.com'
    );

    $result = $service->login();

    expect($result)->toBeFalse();
});

test('login returns false when response missing access token', function () {
    Http::fake([
        '*/api/admins/token' => Http::response([
            'some_other_field' => 'value',
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->login();

    expect($result)->toBeFalse();
});

test('login handles exceptions gracefully', function () {
    Http::fake(fn () => throw new \Exception('Network error'));

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->login();

    expect($result)->toBeFalse();
});

test('createUser sends correct API request with proper field mapping', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users' => Http::response([
            'username' => 'testuser',
            'subscription_url' => '/sub/abc123',
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600, // 2025-01-01 00:00:00 UTC
        'data_limit' => 10737418240, // 10 GB in bytes
        'service_ids' => [1, 2, 3],
    ];

    $result = $service->createUser($userData);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('username')
        ->and($result['username'])->toBe('testuser');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://example.com/api/users'
            && $body['username'] === 'testuser'
            && $body['expire_strategy'] === 'fixed_date'
            && isset($body['expire_date'])
            && $body['data_limit'] === 10737418240
            && $body['data_limit_reset_strategy'] === 'no_reset'
            && $body['service_ids'] === [1, 2, 3];
    });
});

test('createUser handles missing service_ids gracefully', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users' => Http::response([
            'username' => 'testuser',
            'subscription_url' => '/sub/abc123',
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600,
        'data_limit' => 10737418240,
    ];

    $result = $service->createUser($userData);

    expect($result)->toBeArray();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://example.com/api/users'
            && $body['service_ids'] === [];
    });
});

test('createUser authenticates automatically if not logged in', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users' => Http::response(['username' => 'testuser'], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600,
        'data_limit' => 10737418240,
    ];

    $service->createUser($userData);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/admins/token');
    });
});

test('createUser returns error detail on authentication failure', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['detail' => 'Invalid credentials'], 401),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'wrong-password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600,
        'data_limit' => 10737418240,
    ];

    $result = $service->createUser($userData);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('detail')
        ->and($result['detail'])->toBe('Authentication failed');
});

test('createUser handles exceptions gracefully', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users' => fn () => throw new \Exception('API error'),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600,
        'data_limit' => 10737418240,
    ];

    $result = $service->createUser($userData);

    expect($result)->toBeNull();
});

test('updateUser sends correct API request', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users/*' => Http::response([
            'username' => 'testuser',
            'data_limit' => 21474836480,
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'expire' => 1767225600, // 2026-01-01 00:00:00 UTC
        'data_limit' => 21474836480, // 20 GB in bytes
        'service_ids' => [2, 3],
    ];

    $result = $service->updateUser('testuser', $userData);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('username');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://example.com/api/users/testuser'
            && $request->method() === 'PUT'
            && $body['expire_strategy'] === 'fixed_date'
            && isset($body['expire_date'])
            && $body['data_limit'] === 21474836480
            && $body['service_ids'] === [2, 3];
    });
});

test('updateUser works without service_ids', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users/*' => Http::response(['username' => 'testuser'], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'expire' => 1767225600,
        'data_limit' => 21474836480,
    ];

    $result = $service->updateUser('testuser', $userData);

    expect($result)->toBeArray();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://example.com/api/users/testuser'
            && ! isset($body['service_ids']);
    });
});

test('updateUser returns null on authentication failure', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['detail' => 'Invalid credentials'], 401),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'wrong-password',
        'https://node.example.com'
    );

    $userData = [
        'expire' => 1767225600,
        'data_limit' => 21474836480,
    ];

    $result = $service->updateUser('testuser', $userData);

    expect($result)->toBeNull();
});

test('updateUser handles exceptions gracefully', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users/*' => fn () => throw new \Exception('API error'),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'expire' => 1767225600,
        'data_limit' => 21474836480,
    ];

    $result = $service->updateUser('testuser', $userData);

    expect($result)->toBeNull();
});

test('generateSubscriptionLink formats link correctly', function () {
    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userApiResponse = [
        'username' => 'testuser',
        'subscription_url' => '/sub/abc123xyz',
    ];

    $result = $service->generateSubscriptionLink($userApiResponse);

    expect($result)->toBeString()
        ->and($result)->toContain('https://node.example.com/sub/abc123xyz')
        ->and($result)->toContain('لینک سابسکریپشن شما');
});

test('ISO 8601 conversion works correctly for various timestamps', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/users' => Http::response(['username' => 'testuser'], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $userData = [
        'username' => 'testuser',
        'expire' => 1735689600, // 2025-01-01 00:00:00 UTC
        'data_limit' => 10737418240,
    ];

    $service->createUser($userData);

    Http::assertSent(function ($request) {
        $body = $request->data();

        // The ISO 8601 date should match the pattern: 2025-01-01T00:00:00+00:00 or similar
        return isset($body['expire_date'])
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $body['expire_date']);
    });
});

test('listServices returns array of services with id and name', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/services' => Http::response([
            'items' => [
                ['id' => 1, 'name' => 'Service A', 'inbound_ids' => [1, 2]],
                ['id' => 2, 'name' => 'Service B', 'inbound_ids' => [3]],
                ['id' => 3, 'name' => 'Service C', 'inbound_ids' => []],
            ],
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->listServices();

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(3)
        ->and($result[0])->toBe(['id' => 1, 'name' => 'Service A'])
        ->and($result[1])->toBe(['id' => 2, 'name' => 'Service B'])
        ->and($result[2])->toBe(['id' => 3, 'name' => 'Service C']);
});

test('listServices handles direct array response without items key', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/services' => Http::response([
            ['id' => 1, 'name' => 'Service X'],
            ['id' => 2, 'name' => 'Service Y'],
        ], 200),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->listServices();

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBe(['id' => 1, 'name' => 'Service X'])
        ->and($result[1])->toBe(['id' => 2, 'name' => 'Service Y']);
});

test('listServices returns empty array on authentication failure', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['detail' => 'Invalid credentials'], 401),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'wrong-password',
        'https://node.example.com'
    );

    $result = $service->listServices();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('listServices returns empty array on API error', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/services' => Http::response(['error' => 'Internal server error'], 500),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->listServices();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('listServices handles exceptions gracefully', function () {
    Http::fake([
        '*/api/admins/token' => Http::response(['access_token' => 'test-token'], 200),
        '*/api/services' => fn () => throw new \Exception('Network error'),
    ]);

    $service = new MarzneshinService(
        'https://example.com',
        'admin',
        'password',
        'https://node.example.com'
    );

    $result = $service->listServices();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('username with underscores matches Marzneshin pattern requirements', function () {
    // Marzneshin requires usernames to match ^\w{3,32}$
    // which means: word characters only (letters, digits, underscores), 3-32 chars
    
    $validUsernames = [
        'user_1_order_5',
        'user_123_order_456',
        'testuser',
        'user_1_order_1',
    ];
    
    $invalidUsernames = [
        'user-1-order-5',  // contains hyphens
        'us',              // too short (less than 3 chars)
        'user_with_a_very_long_name_exceeding_32_chars_limit', // too long
        'user@name',       // contains special character
    ];
    
    foreach ($validUsernames as $username) {
        expect(preg_match('/^\w{3,32}$/', $username))->toBe(1, "Username '{$username}' should be valid");
    }
    
    foreach ($invalidUsernames as $username) {
        expect(preg_match('/^\w{3,32}$/', $username))->toBe(0, "Username '{$username}' should be invalid");
    }
});

