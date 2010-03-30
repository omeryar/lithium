<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\integration\storage;

use \lithium\storage\Session;

class SessionTest extends \lithium\test\Unit {

	public function setUp() {
		Session::reset();
		$cookies = array_keys($_COOKIE);

		foreach ($cookies as $cookie) {
			setcookie($cookie, "", time()-1);
		}
	}

	public function tearDown() {
		Session::reset();
		$cookies = array_keys($_COOKIE);

		foreach ($cookies as $cookie) {
			setcookie($cookie, "", time()-1);
		}
	}

	public function assertCookie($expected, $headers) {
		$key = $expected['key'];
		$name = (isset($expected['name'])) ? $expected['name'] : 'app';
		$value = preg_quote(urlencode($expected['value']), '/');

		if (isset($expected['expires'])) {
			$date = gmdate('D, d-M-Y H:i:s \G\M\T', strtotime($expected['expires']));
			$expires = preg_quote($date, '/');
		} else {
			$expires = ".+?";
		}
		$path = preg_quote($expected['path'], '/');
		$pattern = "/^Set\-Cookie:\s$name\[$key\]=$value;\sexpires=$expires;\spath=$path/";
		$match = false;

		foreach ($headers as $header) {
			if (preg_match($pattern, $header)) {
				$match = true;
				continue;
			}
		}

		if (!$match) {
			$this->assert(false, sprintf('{:message} - Cookie %s not found in headers.', $pattern));
			return false;
		}
		return $this->assert(true, '%s');
	}

	public function testWriteReadDelete() {
		Session::config(array(
			'test' => array('adapter' => 'Php')
		));

		$key = 'test';
		$value = 'value';

		Session::write($key, $value, array('name' => 'test'));
		$result = Session::read($key, array('name' => 'test'));

		$this->assertEqual($value, $result);
		$this->assertTrue(Session::delete($key, array('name' => 'test')));

		$result = Session::read($key, array('name' => 'test'));
		$this->assertNull($result);
	}

	/**
	 * This method works in tandem with the next one - values
	 * are written here, and then are read & asserted in the next method.
	 */
	public function testCookieWriteReadDelete() {
		Session::config(array(
			'app' => array('adapter' => 'Cookie', 'expiry' => '+1 day')
		));

		Session::write('testkey1', 'value1', array('name' => 'app'));
		Session::write('testkey2', 'value2', array('name' => 'app'));
		Session::write('testkey3', 'value3', array('name' => 'app'));

		$params = array('expires' => '+1 day', 'path' => '/');

		$this->assertCookie(
			array('key' => 'testkey1', 'value' => 'value1') + $params, headers_list()
		);
		$this->assertCookie(
			array('key' => 'testkey2', 'value' => 'value2') + $params, headers_list()
		);
		$this->assertCookie(
			array('key' => 'testkey3', 'value' => 'value3') + $params, headers_list()
		);

		Session::delete('testkey1', array('name' => 'app'));
		Session::delete('testkey2', array('name' => 'app'));
		Session::delete('testkey3', array('name' => 'app'));

		$params = array('exires' => '-1 second', 'path' => '/');

		$this->assertCookie(
			array('key' => 'testkey1', 'value' => 'deleted') + $params, headers_list()
		);
		$this->assertCookie(
			array('key' => 'testkey2', 'value' => 'deleted') + $params, headers_list()
		);
		$this->assertCookie(
			array('key' => 'testkey3', 'value' => 'deleted') + $params, headers_list()
		);
	}

	public function testStrategiesPhpAdapter() {
		Session::config(array(
			'strategy' => array(
				'adapter' => 'Php',
				'strategies' => array('Hmac' => array('secret' => 'somesecretkey'))
			)
		));

		$key = 'test';
		$value = 'value';

		Session::write($key, $value, array('name' => 'strategy'));
		$result = Session::read($key, array('name' => 'strategy'));

		$this->assertEqual($value, $result);
		$this->assertTrue(Session::delete($key, array('name' => 'strategy')));
		$result = Session::read($key, array('name' => 'strategy'));
		$this->assertNull($result);

		$cache = $_SESSION;
		$_SESSION['injectedkey'] = 'hax0r';
		$this->expectException('/Possible data tampering - HMAC signature does not match data./');
		$result = Session::read($key, array('name' => 'strategy'));
		$_SESSION = $cache;
	}
}

?>