<?php
/**
* The MIT License (MIT)
*
* Copyright (c) 2014 LoyaltyLion
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*
* @author    LoyaltyLion <support@loyaltylion.com>
* @copyright 2012-2014 LoyaltyLion
* @license   http://opensource.org/licenses/MIT  The MIT License
*/

class LoyaltyLion_Connection
{
	private $token;
	private $secret;
	private $auth;
	private $base_uri;
	private $timeout = 5;

	public function __construct($token, $secret, $base_uri)
	{
		$this->token = $token;
		$this->secret = $secret;
		$this->base_uri = $base_uri;
	}

	public function post($path, $data = array())
	{
		return $this->request('POST', $path, $data);
	}

	public function put($path, $data = array())
	{
		return $this->request('PUT', $path, $data);
	}

	public function get($path, $data = array())
	{
		return $this->request('GET', $path, $data);
	}

	private function request($method, $path, $data)
	{
		$options = array(
			CURLOPT_URL => $this->base_uri.$path,
			CURLOPT_USERAGENT => 'loyaltylion-php-client-v2.0.0',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			// CURLOPT_HEADER => false,
			CURLOPT_USERPWD => $this->token.':'.$this->secret,
		);

		switch ($method)
		{
			case 'POST':
				$options += array(
					CURLOPT_POST => true,
				);
				break;
			case 'PUT':
				$options += array(
					CURLOPT_CUSTOMREQUEST => 'PUT',
				);
		}

		if ($method == 'POST' || $method == 'PUT')
		{
			$body = json_encode($data);
			$options += array(
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json',
					'Content-Length: '.strlen($body),
				),
			);
		}

		// now make the request
		$curl = curl_init();
		curl_setopt_array($curl, $options);

		$body = curl_exec($curl);
		$headers = curl_getinfo($curl);
		$error_code = curl_errno($curl);
		$error_msg = curl_error($curl);

		if ($error_code !== 0)
		{
			$response = array(
				'status' => $headers['http_code'],
				'error' => $error_msg,
			);
		}
		else
		{
			$response = array(
				'status' => $headers['http_code'],
				'headers' => $headers,
				'body' => $body,
			);
		}

		return (object)$response;
	}
}