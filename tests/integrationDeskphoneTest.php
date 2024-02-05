<?php
/**
 * Integration tests for module Snom (Deskphone).
 * Before executing this tests, make sure that const PHONE_IP has the value
 * of a Snom phone connected to the same network as the machine were the
 * tests are running.
 */
	declare(strict_types=1);
	include_once __DIR__ . '/stubs/GlobalStubs.php';
	include_once __DIR__ . '/stubs/KernelStubs.php';
	include_once __DIR__ . '/stubs/MessageStubs.php';
	include_once __DIR__ . '/stubs/GlobalStubs.php';
	
	use PHPUnit\Framework\TestCase;

	const PHONE_IP = '10.110.10.100';
	

	class IntegrationDeskphoneTest extends TestCase
	{
		protected $snomDeskphoneModuleId = "{6A66A16E-5525-10FE-72D4-772C9ADD8D45}";

		protected function setUp(): void 
		{
			IPS\Kernel::reset();
			$file = __DIR__ . '/../library.json';
			IPS\ModuleLoader::loadLibrary($file);
			parent::setUp();
		}

		public function testPhoneIsReachable()
		{
			$deskphoneId = IPS_CreateInstance($this->snomDeskphoneModuleId);
			IPS_SetProperty($deskphoneId, 'PhoneIP', PHONE_IP);
			IPS_ApplyChanges($deskphoneId);
			$expected = "IP " . PHONE_IP . " is reachable";
			$this->assertEquals(SNMD_PingPhone($deskphoneId, PHONE_IP), $expected);
		}
    }