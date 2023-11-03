<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Outlook Integration
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/outlook-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: 26bfa1fab74a68212506685b1b343192
 ***********************************************************************************/

namespace Espo\Modules\Outlook\Core\Outlook\Clients;

use Espo\Core\Exceptions\Error;

use Espo\Core\ExternalAccount\OAuth2\Client;

use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;

class Outlook extends \Espo\Core\ExternalAccount\Clients\OAuth2Abstract
{
    protected $baseUrl = 'https://graph.microsoft.com/v1.0/me/';

    protected $calendar;

    protected $contacts;

    protected $mail;

    protected $redirectUri = null;

    protected $original = null;

    protected $tenant = 'common';

    const HEADER_REGEX = "(^([^()<>@,;:\\\"/[\]?={}\x01-\x20\x7F]++):[ \t]*+((?:[ \t]*+[\x21-\x7E\x80-\xFF]++)*+)[ \t]*+\r?\n)m";

    const HEADER_FOLD_REGEX = "(\r?\n[ \t]++)";

    const ACCESS_TOKEN_EXPIRATION_MARGIN = '20 seconds';

    public function getParam($name)
    {
        $method = '_getParam' . ucfirst($name);

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return parent::getParam($name);
    }

    public function setParams(array $params)
    {
        foreach ($params as $k => $v) {
            $method = '_setParam' . ucfirst($k);

            if (method_exists($this, $method)) {
                $this->$method($v);
            }
        }

        parent::setParams($params);
    }

    public function setParam($name, $value)
    {
        $method = '_setParam' . ucfirst($name);

        if (method_exists($this, $method)) {
            $this->$method($value);

            return;
        }

        parent::setParam($name, $value);
    }

    protected function _getParamTokenEndpoint()
    {
        $endpoint = $this->tokenEndpoint;

        $tenant = $this->tenant ?: 'common';

        $endpoint = str_replace('{tenant}', $tenant, $endpoint);

        return $endpoint;
    }

    protected function _setParamTenant($value)
    {
        $this->tenant = $value;
    }

    protected function buildUrl($url)
    {
        return $this->baseUrl . trim($url, '\/');
    }

    public function requestUserData()
    {
        return $this->request($this->baseUrl);
    }

    public function setOriginal($original)
    {
        $this->original = $original;
    }

    public function batchRequest(array $itemList)
    {
        $httpHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $requestData = (object) [
            'requests' => $itemList,
        ];

        $body = json_encode($requestData, \JSON_PRETTY_PRINT);

        $url = $this->buildUrl('../$batch');

        $responseHeaders = [];

        $response = $this->request($url, $body, Client::HTTP_METHOD_POST, null, true, $httpHeaders, $responseHeaders);

        $resultList = $response['responses'] ?? [];

        return $resultList;
    }

    public function request(
        $url,
        $params = null,
        $httpMethod = Client::HTTP_METHOD_GET,
        $contentType = null,
        $allowRenew = true,
        ?array $httpHeaders = null,
        ?array &$responseHeaders = null
    ) {
        if ($this->original) {
            return $this->original->request(
                $url, $params, $httpMethod, $contentType, $allowRenew, $httpHeaders, $responseHeaders
            );
        }

        if (method_exists($this, 'handleAccessTokenActuality')) {
            $this->handleAccessTokenActuality();
        }

        $httpHeaders = $httpHeaders ?? [];

        if (!empty($contentType)) {
            $httpHeaders['Content-Type'] = $contentType;

            switch ($contentType) {
                case Client::CONTENT_TYPE_MULTIPART_FORM_DATA:
                    $httpHeaders['Content-Length'] = strlen($params);
                    break;

                case Client::CONTENT_TYPE_APPLICATION_JSON:
                    $httpHeaders['Content-Length'] = strlen($params);
                    break;
            }
        }

        $r = $this->client->request($url, $params, $httpMethod, $httpHeaders);

        $code = null;

        if (!empty($r['code'])) {
            $code = intval($r['code']);
        }

        if (!is_null($responseHeaders)) {
            if (isset($r['header'])) {
                $msg = $r['header'] . "\n";
                $responseHeaders = $this->parseResponce($msg)['headers'];
            }
        }

        if ($code >= 200 && $code < 300) {
            return $r['result'];
        }
        else {
            $handledData = $this->handleErrorResponse($r);

            if ($allowRenew && is_array($handledData)) {
                if ($handledData['action'] == 'refreshToken') {
                    $GLOBALS['log']->debug(
                        "Outlook: Refresh token action required for client {$this->clientId}; Response: " . json_encode($r)
                    );

                    if ($this->refreshToken()) {
                        return $this->request($url, $params, $httpMethod, $contentType, false);
                    }
                } else if ($handledData['action'] == 'renew') {
                    $GLOBALS['log']->debug(
                        "Outlook: Renew action required for client {$this->clientId}; Response: " . json_encode($r)
                    );

                    return $this->request($url, $params, $httpMethod, $contentType, false);
                }
            }
        }

        $reasonPart = '';

        $errorResult = [];

        if (isset($r['result']['error']) && isset($r['result']['error']['message'])) {
            $reasonPart = '; Reason: ' . $r['result']['error']['message'];
        }

        $errorResult = $r['result']['error'] ?? [];

        throw ApiError::create(
            "Outlook Oauth: Error after requesting {$httpMethod} {$url}{$reasonPart}. Code: {$code}.",
            $errorResult,
            $code
        );
    }

    protected function refreshToken()
    {
        if (empty($this->refreshToken)) {
            throw new Error(
                "Outlook: Could not refresh token for client {$this->clientId}, because refreshToken is empty."
            );
        }

        if (method_exists($this, 'lock')) {
            $this->lock();
        }

        try {
            $r = $this->client->getAccessToken($this->getParam('tokenEndpoint'), Client::GRANT_TYPE_REFRESH_TOKEN, [
                'refresh_token' => $this->refreshToken,
            ]);
        } catch (\Exception $e) {
            $this->unlock();

            throw new Error("Oauth: Error while refreshing token: " . $e->getMessage());
        }

        if ($r['code'] == 200) {
            if (is_array($r['result'])) {
                if (!empty($r['result']['access_token'])) {
                    if (method_exists($this, 'getAccessTokenDataFromResponseResult')) {
                        $data = $this->getAccessTokenDataFromResponseResult($r['result']);
                    } else {
                        $data = [];
                        $data['accessToken'] = $r['result']['access_token'];
                        $data['tokenType'] = $r['result']['token_type'];
                    }

                    $this->setParams($data);
                    $this->afterTokenRefreshed($data);

                    if (method_exists($this, 'unlock')) {
                        $this->unlock();
                    }

                    return true;
                }
            }
        }

        if (method_exists($this, 'unlock')) {
            $this->unlock();
        }

        $GLOBALS['log']->error("Outlook: Refreshing token failed for client {$this->clientId}: " . json_encode($r));
    }

    protected function getPingUrl()
    {
    }

    private function getParams()
    {
        $params = [];

        foreach ($this->paramList as $name) {
            $params[$name] = $this->$name;
        }

        return $params;
    }

    public function getCalendarClient()
    {
        if (empty($this->calendar)) {
            $this->calendar = new Calendar($this->client, $this->getParams(), $this->manager);
            $this->calendar->setOriginal($this);
        }
        return $this->calendar;
    }

    public function getContactsClient()
    {
        if (empty($this->contacts)) {
            $this->contacts = new Contacts($this->client, $this->getParams(), $this->manager);
            $this->contacts->setOriginal($this);
        }

        return $this->contacts;
    }

    public function getMailClient()
    {
        if (empty($this->mail)) {
            $this->mail = new Mail($this->client, $this->getParams(), $this->manager);
            $this->mail->setOriginal($this);
        }

        return $this->mail;
    }

    public function ping()
    {
        if (empty($this->clientId)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty clientId.");
            return false;
        }

        if (empty($this->accessToken)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty accessToken for client {$this->clientId}.");
            return false;
        }

        if (empty($this->clientSecret)) {
            $GLOBALS['log']->notice("Outlook: Can't ping because empty clientSecret for client {$this->clientId}.");
            return false;
        }

        $calendarPingResult = $this->productPing($this->getCalendarClient()->getPingUrl());

        return $calendarPingResult;
    }

    public function productPing($url = null)
    {
        if (!$url) {
            $url = $this->getPingUrl();
        }

        try {
            $this->request($url);
            return true;
        } catch (\Exception $e) {
            $GLOBALS['log']->notice("Outlook: Ping failed for client {$this->clientId}: " . $e->getMessage());
            return false;
        }
    }

    protected function handleErrorResponse($r)
    {
        if ($r['code'] == 401 && !empty($r['result'])) {
            $result = $r['result'];

            if (
                !empty($result['error']) && !empty($result['error']['code']) &&
                (in_array($result['error']['code'], ['InvalidMsaTicket', 'TokenExpired']))
            ) {
                return [
                    'action' => 'refreshToken'
                ];
            } else {
                return [
                    'action' => 'renew'
                ];
            }
        } else if ($r['code'] == 400 && !empty($r['result'])) {
            if (
                !empty($result['error']) && !empty($result['error']['code']) &&
                (in_array($result['error']['code'], ['InvalidMsaTicket', 'TokenExpired']))
            ) {
                return [
                    'action' => 'refreshToken'
                ];
            }
        }
    }

    protected function parseResponce($message)
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }

        if (strpos($message, 'HTTP/1.1 100 Continue') === 0) {
            $message = substr($message, 21);
        }

        $message = ltrim($message, "\r\n");

        $messageParts = preg_split("/\r?\n\r?\n/", $message, 2);

        if ($messageParts === false || count($messageParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header delimiter');
        }

        list($rawHeaders, $body) = $messageParts;
        $rawHeaders .= "\r\n"; // Put back the delimiter we split previously
        $headerParts = preg_split("/\r?\n/", $rawHeaders, 2);

        if ($headerParts === false || count($headerParts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing status line');
        }

        list($startLine, $rawHeaders) = $headerParts;

        if (preg_match("/(?:^HTTP\/|^[A-Z]+ \S+ HTTP\/)(\d+(?:\.\d+)?)/i", $startLine, $matches) && $matches[1] === '1.0') {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $rawHeaders = preg_replace(self::HEADER_FOLD_REGEX, ' ', $rawHeaders);
        }

        /** @var array[] $headerLines */
        $count = preg_match_all(self::HEADER_REGEX, $rawHeaders, $headerLines, PREG_SET_ORDER);

        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($rawHeaders, "\n")) {
            // Folding is deprecated, see https://tools.ietf.org/html/self#section-3.2.4
            if (preg_match(self::HEADER_FOLD_REGEX, $rawHeaders)) {
                throw new \InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }

            throw new \InvalidArgumentException('Invalid header syntax');
        }

        $headers = [];

        foreach ($headerLines as $headerLine) {
            $headers[$headerLine[1]][] = $headerLine[2];
        }

        return [
            'start-line' => $startLine,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}
