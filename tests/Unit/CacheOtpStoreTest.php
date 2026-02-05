<?php

use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('stores hashed code with ttl', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    $store->store('identifier123', 'hashedcode', 600);
    expect(Cache::get('test:otp:identifier123'))->toBe('hashedcode');
});

it('retrieves stored code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    $store->store('id', 'hash', 600);
    expect($store->get('id'))->toBe('hash');
});

it('returns null for missing code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    expect($store->get('missing'))->toBeNull();
});

it('forgets stored code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    $store->store('id', 'hash', 600);
    $store->forget('id');
    expect($store->get('id'))->toBeNull();
});

it('checks if code exists', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    expect($store->has('id'))->toBeFalse();
    $store->store('id', 'hash', 600);
    expect($store->has('id'))->toBeTrue();
});
