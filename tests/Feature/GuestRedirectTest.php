<?php

it('redirects a guest hitting a protected route to session.expired', function (): void {
    $response = $this->get('/dashboard');
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/session-expired');
});

it('does not 500 when hitting /login with missing SSO config', function (): void {
    config()->set('sso.authorization_url', null);
    $response = $this->get('/login');
    expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('/session-expired');
});

it('redirects to the SSO authorization URL when configured', function (): void {
    config()->set('sso.authorization_url', 'http://localhost:3000/ccms/auth/sso/login');
    $this->get('/login')->assertRedirect('http://localhost:3000/ccms/auth/sso/login');
});
