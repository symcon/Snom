<?php
	declare(strict_types=1);
	include_once __DIR__ . '/stubs/GlobalStubs.php';
	include_once __DIR__ . '/stubs/KernelStubs.php';
	include_once __DIR__ . '/stubs/MessageStubs.php';
	include_once __DIR__ . '/stubs/GlobalStubs.php';
	
	use PHPUnit\Framework\TestCase;
	

	class UnitDeskphoneTest extends TestCase
	{
		protected $snomDeskphoneModuleId = "{6A66A16E-5525-10FE-72D4-772C9ADD8D45}";

		protected function setUp(): void 
		{
			IPS\Kernel::reset();
			$file = __DIR__ . '/../library.json';
			IPS\ModuleLoader::loadLibrary($file);
			parent::setUp();
		}

		public function testCreatedInstanceExists()
		{
			$deskphoneId = IPS_CreateInstance($this->snomDeskphoneModuleId);
			$existingInstances = IPS_GetInstanceListByModuleID($this->snomDeskphoneModuleId);
			$this->assertContains($deskphoneId, $existingInstances);
		}

		public function testInstanceWithGivenIpExists()
		{
			$deskphoneId1 = IPS_CreateInstance($this->snomDeskphoneModuleId);
			IPS_SetProperty($deskphoneId1, 'PhoneIP', '192.168.178.20');
			IPS_ApplyChanges($deskphoneId1);
			$deskphoneId2 = IPS_CreateInstance($this->snomDeskphoneModuleId);
			IPS_SetProperty($deskphoneId2, 'PhoneIP', '192.168.178.20');
			IPS_ApplyChanges($deskphoneId2);
			$this->assertTrue(SNMD_instanceIpExists($deskphoneId2));
		}
    }