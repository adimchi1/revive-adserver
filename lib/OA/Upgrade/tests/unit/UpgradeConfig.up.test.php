<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

require_once(MAX_PATH . '/lib/OA/Upgrade/Configuration.php');

/**
 * A class for testing the OpenX Upgrade Configuration class.
 *
 * @package    OpenX Upgrade
 * @subpackage TestSuite
 */
class Test_OA_Upgrade_Config extends UnitTestCase
{
    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function test_constructor()
    {
        $oUpConfig = new OA_Upgrade_Config();
        $this->assertIsA($oUpConfig, 'OA_Upgrade_Config', 'class mismatch: OA_Upgrade_Config');
        $this->assertIsA($oUpConfig->aConfig, 'array', 'class mismatch: array');
        $this->assertIsA($oUpConfig->oSettings, 'OA_Admin_Settings', 'class mismatch: OA_Admin_Settings');
    }

    public function setUp()
    {
        // Tests in this class need to use the "real" configuration
        // file writing method, not the one reserved for the test
        // environment...
        $GLOBALS['override_TEST_ENVIRONMENT_RUNNING'] = true;
        $this->serverSave = $_SERVER['HTTP_HOST'];
    }

    public function tearDown()
    {
        // Resume normal service with regards to the configuration file writer...
        unset($GLOBALS['override_TEST_ENVIRONMENT_RUNNING']);
        $_SERVER['HTTP_HOST'] = $this->serverSave;
    }

    public function test_writeConfig()
    {
        TestEnv::restoreConfig();

        // TEST 1

        $hostAdmin = 'admin.mydomain.net';
        $_SERVER['HTTP_HOST'] = $hostDelivery = 'delivery.mydomain.net';

        $fileDefault = MAX_PATH . '/var/default.conf.php';
        $fileFake = MAX_PATH . '/var/' . $hostAdmin . '.conf.php';
        $fileReal = MAX_PATH . '/var/' . $hostDelivery . '.conf.php';

        if (file_exists($fileDefault)) {
            @copy($fileDefault, $fileDefault . '.bak');
            @unlink($fileDefault);
        }

        @unlink($fileReal);
        @unlink($fileFake);

        $oConf = new OA_Upgrade_Config();

        // Build the local conf array manually.
        $aConfig['webpath']['admin'] = $hostAdmin;
        $aConfig['webpath']['delivery'] = $hostDelivery;
        $aConfig['webpath']['deliverySSL'] = $hostDelivery;

        $oConf->setupConfigWebPath($aConfig['webpath']);

        $this->assertTrue($oConf->writeConfig(), 'Error writing config file');
        $this->assertTrue(file_exists($fileReal), 'Real config file does not exist');
        $this->assertTrue(file_exists($fileFake), 'Fake config file does not exist');

        $aRealConfig = @parse_ini_file($fileReal, true);
        $this->assertEqual($oConf->aConfig, $aRealConfig, 'Delivery config has incorrect values');
        $this->assertFalse(isset($aRealConfig['realConfig']));
        $this->assertTrue(isset($aRealConfig['openads']));
        $this->assertEqual($aRealConfig['openads']['installed'], 1);
        $this->assertEqual($aRealConfig['webpath']['admin'], $hostAdmin);
        $this->assertEqual($aRealConfig['webpath']['delivery'], $hostDelivery);
        $this->assertEqual($aRealConfig['webpath']['deliverySSL'], $hostDelivery);

        $aFakeConfig = @parse_ini_file($fileFake, true);
        $this->assertTrue(isset($aFakeConfig['realConfig']));
        $this->assertTrue($aFakeConfig['realConfig'], $hostDelivery);

        // default.conf.php only gets created if no other foreign confs exist
        if (file_exists($fileDefault)) {
            $aDefConfig = @parse_ini_file($fileDefault, true);
            $this->assertTrue(isset($aDefConfig['realConfig']));
            $this->assertTrue($aDefConfig['realConfig'], $hostDelivery);
            @unlink($fileDefault);
        }
        // Clean up
        @unlink($fileReal);
        @unlink($fileFake);
        TestEnv::restoreConfig();

        // TEST 2  : reverse the hosts

        $hostAdmin = 'admin.mydomain.net';
        $hostDelivery = 'delivery.mydomain.net';

        $fileReal = MAX_PATH . '/var/' . $hostAdmin . '.conf.php';
        $fileFake = MAX_PATH . '/var/' . $hostDelivery . '.conf.php';

        @unlink($fileAdmin);
        @unlink($fileDelivery);

        $oConf = new OA_Upgrade_Config();

        // Build the local conf array manually.
        $aConfig['webpath']['admin'] = $hostDelivery;
        $aConfig['webpath']['delivery'] = $hostAdmin;
        $aConfig['webpath']['deliverySSL'] = $hostAdmin;

        $oConf->setupConfigWebPath($aConfig['webpath']);

        $this->assertTrue($oConf->writeConfig(), 'Error writing config file');
        $this->assertTrue(file_exists($fileReal), 'Real config file does not exist');
        $this->assertTrue(file_exists($fileFake), 'Fake config file does not exist');

        $aRealConfig = @parse_ini_file($fileReal, true);
        $this->assertEqual($oConf->aConfig, $aRealConfig, 'Real config has incorrect values');
        $this->assertFalse(isset($aRealConfig['realConfig']));
        $this->assertTrue(isset($aRealConfig['openads']));
        $this->assertEqual($aRealConfig['openads']['installed'], 1);
        $this->assertEqual($aRealConfig['webpath']['admin'], $hostDelivery);
        $this->assertEqual($aRealConfig['webpath']['delivery'], $hostAdmin);
        $this->assertEqual($aRealConfig['webpath']['deliverySSL'], $hostAdmin);

        $aFakeConfig = @parse_ini_file($fileFake, true);
        $this->assertTrue(isset($aFakeConfig['realConfig']));
        $this->assertTrue($aFakeConfig['realConfig'], $hostAdmin);

        // default.conf.php only gets created if no other foreign confs exist
        if (file_exists($fileDefault)) {
            $aDefConfig = @parse_ini_file($fileDefault, true);
            $this->assertTrue(isset($aDefConfig['realConfig']));
            $this->assertTrue($aDefConfig['realConfig'], $hostAdmin);
            @unlink($fileDefault);
            @copy($fileDefault . '.bak', $fileDefault);
            @unlink($fileDefault . '.bak');
        }
        // Clean up
        @unlink($fileReal);
        @unlink($fileFake);
        TestEnv::restoreConfig();
    }

    public function test_setupConfigDatabase()
    {
        $oUpConfig = new OA_Upgrade_Config();

        $aConfig['username'] = 'myname';
        $aConfig['password'] = 'mypass';
        $aConfig['name'] = 'mydb';
        $aConfig['persistent'] = '0';

        $aConfig['type'] = 'mysql';

        $aConfig['host'] = 'localhost';
        $aConfig['socket'] = '';
        $aConfig['port'] = '3306';
        $aConfig['protocol'] = 'tcp';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], $aConfig['host']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], $aConfig['port']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);

        $aConfig['host'] = '';
        $aConfig['socket'] = '';
        $aConfig['port'] = '';
        $aConfig['protocol'] = 'unix';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], 'localhost');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], '3306');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);

        $aConfig['host'] = '';
        $aConfig['socket'] = '/var/lib/mysql/mysql.sock';
        $aConfig['port'] = '';
        $aConfig['protocol'] = 'unix';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], 'localhost');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], '3306');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);

        $aConfig['type'] = 'pgsql';

        $aConfig['host'] = 'localhost';
        $aConfig['socket'] = '';
        $aConfig['port'] = '5432';
        $aConfig['protocol'] = 'tcp';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], $aConfig['host']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], $aConfig['port']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);

        $aConfig['host'] = '';
        $aConfig['socket'] = '';
        $aConfig['port'] = '';
        $aConfig['protocol'] = 'unix';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], 'localhost');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], '5432');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);

        $aConfig['host'] = '';
        $aConfig['socket'] = '/tmp/pgsql.sock';
        $aConfig['port'] = '';
        $aConfig['protocol'] = 'unix';
        $oUpConfig->setupConfigDatabase($aConfig);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['type'], $aConfig['type']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['protocol'], $aConfig['protocol']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['host'], 'localhost');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['socket'], $aConfig['socket']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['port'], '5432');
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['username'], $aConfig['username']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['password'], $aConfig['password']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['name'], $aConfig['name']);
        $this->assertEqual($oUpConfig->oSettings->aConf['database']['persistent'], $aConfig['persistent']);
    }

    public function test_getInitialConfig()
    {
        $oUpConfig = new OA_Upgrade_Config();
        $oUpConfig->getInitialConfig();
    }

    /**
     * This function checks for any new items in the config dist file
     */
    public function test_checkForConfigAdditions()
    {
        $oUpConfig = new OA_Upgrade_Config();
        // First check that the working config file agrees with the dist config file
        $this->assertFalse($oUpConfig->checkForConfigAdditions(null), 'New config items have NOT been added to working test.conf.php');

        // Assert no new items detected when $new === $old
        $new = $oUpConfig->aConfig;
        $this->assertFalse($oUpConfig->checkForConfigAdditions($new), 'New dist.conf.php items mistakenly detected');

        // Add a new item to an existing sub-array
        $new = $oUpConfig->aConfig;
        $new['database']['key'] = 'value';
        $this->assertTrue($oUpConfig->checkForConfigAdditions($new), 'New dist.conf.php items (added to existing section) not detected');

        // Add a completely new empty sub-array
        $new = $oUpConfig->aConfig;
        $new['newSubArray'] = [];
        $this->assertTrue($oUpConfig->checkForConfigAdditions($new), 'New dist.conf.php items (empty section) not detected');

        // Add a new sub-array with a new item
        $new = $oUpConfig->aConfig;
        $new['newSubArray'] = ['key' => 'value'];
        $this->assertTrue($oUpConfig->checkForConfigAdditions($new), 'New dist.conf.php items (new section with value) not detected');

        // Add a new item not in a sub-array (so top level)
        $new = $oUpConfig->aConfig;
        $new['key'] = 'value';
        $this->assertTrue($oUpConfig->checkForConfigAdditions($new), 'New (top level) dist.conf.php items not detected');
    }

    public function test_generateDeliverySecret()
    {
        $oUpConfig = new OA_Upgrade_Config();
        $oUpConfig->aConfig['delivery']['secret'] = null;
        $oUpConfig->generateDeliverySecret();
        $this->assertEqual(32, strlen(base64_decode($oUpConfig->aConfig['delivery']['secret'])));

        $oUpConfig->aConfig['delivery']['secret'] = 'foobar';
        $oUpConfig->generateDeliverySecret();
        $this->assertEqual(32, strlen(base64_decode($oUpConfig->aConfig['delivery']['secret'])));

        $oUpConfig->aConfig['delivery']['secret'] = base64_encode('foobar');
        $oUpConfig->generateDeliverySecret();
        $this->assertEqual(32, strlen(base64_decode($oUpConfig->aConfig['delivery']['secret'])));

        $secret = $oUpConfig->aConfig['delivery']['secret'];
        $oUpConfig->generateDeliverySecret();
        $this->assertEqual($secret, $oUpConfig->aConfig['delivery']['secret']);
    }
}
