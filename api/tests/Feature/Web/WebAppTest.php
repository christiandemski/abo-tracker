<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->appDir = public_path('app');
    $this->hadAppDir = File::isDirectory($this->appDir);
    $this->tmpDir = storage_path('framework/testing/webapp');
    File::ensureDirectoryExists($this->tmpDir.'/_expo/static/js');
    File::put($this->tmpDir.'/index.html', '<!doctype html><title>Cost-Tracker</title>');
    File::put($this->tmpDir.'/_expo/static/js/entry.js', 'console.log(1)');
    app()->usePublicPath(storage_path('framework/testing'));
    File::moveDirectory($this->tmpDir, public_path('app'), true);
});

function servedFile($response): string
{
    return $response->baseResponse->getFile()->getFilename();
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing'));
});

it('serves index.html for client-side routes', function () {
    $response = $this->get('/register?token=abc')->assertOk();

    expect(servedFile($response))->toBe('index.html')
        ->and($response->headers->get('Cache-Control'))->toContain('no-cache', 'no-store');
});

it('serves static files of the export', function () {
    expect(servedFile($this->get('/_expo/static/js/entry.js')->assertOk()))->toBe('entry.js');
});

it('does not allow path traversal', function () {
    File::put(storage_path('framework/testing/secret.txt'), 'secret');

    expect(servedFile($this->get('/..%2Fsecret.txt')))->toBe('index.html')
        ->and(servedFile($this->get('/../secret.txt')))->toBe('index.html');
});

it('keeps unknown API paths as JSON 404', function () {
    $this->get('/api/v1/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
});

it('answers 503 when the web app is not built', function () {
    File::deleteDirectory(public_path('app'));

    $this->get('/')->assertStatus(503);
});

it('publishes Android asset links when fingerprints are configured', function () {
    $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertExactJson([]);

    config(['services.android.sha256_cert_fingerprints' => ['AA:BB']]);

    $this->getJson('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertJsonPath('0.target.package_name', 'de.codekorb.kosten')
        ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB']);
});
