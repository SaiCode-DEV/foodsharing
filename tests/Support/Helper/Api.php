<?php

declare(strict_types=1);

namespace Tests\Support\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Module;

class Api extends Module
{
    /**
     * Checks the response content is html.
     *
     * @throws ModuleException
     */
    public function seeResponseIsHtml(): void
    {
        $response = $this->getModule('REST')->response;
        $this->assertRegExp('~<!doctype html>.*~im', $response);
    }

    /**
     * Checks is a regular expression is found in response content.
     *
     * @throws ModuleException
     */
    public function seeRegExp($pattern): void
    {
        $response = $this->getModule('REST')->response;
        $this->assertRegExp($pattern, $response);
    }

    /**
     * @throws ModuleException
     */
    public function dontSeeRegExp($pattern): void
    {
        $response = $this->getModule('REST')->response;
        $this->assertNotRegExp($pattern, $response);
    }

    /**
     * @throws ModuleException
     */
    public function login($email, $pass = 'password'): void
    {
        $rest = $this->getModule('REST');
        $rest->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $rest->sendPOST('api/login', [
                'email' => $email,
                'password' => $pass
        ]);
        // Re-apply CSRF header after login
        $rest->haveHttpHeader('X-CSRF-Token', CSRF_TEST_TOKEN);
    }
}
