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

require_once MAX_PATH . '/lib/OA/Maintenance/Priority/AdServer/Task/ECPMforRemnant.php';
require_once MAX_PATH . '/lib/max/Dal/Admin/Data_intermediate_ad.php';

/**
 * A class for testing the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class.
 *
 * @package    OpenXMaintenance
 * @subpackage TestSuite
 */
class Test_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant extends UnitTestCase
{
    private $mockDal;
    private $mockDalIntermediateAd;

    public const IDX_ADS = OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::IDX_ADS;
    public const IDX_MIN_IMPRESSIONS = OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::IDX_MIN_IMPRESSIONS;
    public const IDX_WEIGHT = OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::IDX_WEIGHT;
    public const IDX_ZONES = OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::IDX_ZONES;

    public const ALPHA = OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::ALPHA;

    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
        Mock::generate(
            'OA_Dal_Maintenance_Priority',
            $this->mockDal = 'MockOA_Dal_Maintenance_Priority' . rand()
        );
        Mock::generate(
            'MAX_Dal_Admin_Data_intermediate_ad',
            $this->mockDalIntermediateAd = 'MAX_Dal_Admin_Data_intermediate_ad' . rand()
        );
        Mock::generatePartial(
            'OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant',
            'PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant',
            ['_getDal', '_factoryDal', 'getTodaysRemainingOperationIntervals',
                'calculateCampaignEcpm'
            ]
        );
    }

    /**
     * Used for asserting that two arrays are equal even if
     * both arrays contain floats. All values are first rounded
     * to the given precision before comparing
     */
    public function assertEqualsFloatsArray($aExpected, $aChecked, $precision = 4)
    {
        $this->assertTrue(is_array($aExpected));
        $this->assertTrue(is_array($aChecked));
        $aExpected = $this->roundArray($aExpected, $precision);
        $aChecked = $this->roundArray($aChecked, $precision);
        $this->assertEqual($aExpected, $aChecked);
    }

    public function roundArray($arr, $precision)
    {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->roundArray($v, $precision);
            } else {
                $arr[$k] = round($v, $precision);
            }
        }
        return $arr;
    }

    /**
     * A method to test the preloadZonesAvailableImpressionsForAgency() method.
     *
     * Requirements
     * Test 1: Test that contracts are correctly calculated based on the forecasts and allocations
     */
    public function testPreloadZonesAvailableImpressionsForAgency()
    {
        // Mock the OA_Dal_Maintenance_Priority class used in the constructor method
        $oDal = new $this->mockDal($this);
        $aZonesForecasts = [
            1 => 10,
            2 => 20,
            3 => 50,
        ];
        $oDal->setReturnReference('getZonesForecasts', $aZonesForecasts);
        $aZonesAllocations = [
            1 => 10,
            2 => 30,
            4 => 10, // this should be ignored
        ];
        $oDal->setReturnReference('getZonesAllocationsForEcpmRemnantByAgency', $aZonesAllocations);
        $oServiceLocator = OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oDal);

        // Partially mock the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class
        $oEcpm = new PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant($this);
        $oEcpm->aOIDates['start'] = $oEcpm->aOIDates['end'] = new Date();
        $oEcpm->setReturnReference('_getDal', $oDal);
        (new ReflectionMethod(OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::class, '__construct'))->invoke($oEcpm);

        // Test
        $aZonesExpectedContracts = [
            1 => 0, // 10 - 10
            2 => 0, // 20 - 30 = -10, so it should be 0
            3 => 50, // 50 - no allocations for this zone
        ];
        $dataJustLoaded = $oEcpm->preloadZonesAvailableImpressionsForAgency(123);
        $this->assertEqual($aZonesExpectedContracts, $oEcpm->aZonesAvailableImpressions);
        $this->assertTrue($dataJustLoaded);

        $dataJustLoaded = $oEcpm->preloadZonesAvailableImpressionsForAgency(152);
        $this->assertEqual($aZonesExpectedContracts, $oEcpm->aZonesAvailableImpressions);
        $this->assertFalse($dataJustLoaded);
    }

    /**
     * A method to test the preloadCampaignsDeliveredImpressionsForAgency() method.
     *
     * Requirements
     * Test 1: Test that campaign impressions are correctly preloaded
     */
    public function testPreloadCampaignsDeliveredImpressionsForAgency()
    {
        // Mock the MAX_Dal_Admin_Data_intermediate_ad class used in the constructor method
        $oDal = new $this->mockDalIntermediateAd($this);
        $aCampaignsImpressions = [
            1 => 10,
            2 => 20,
        ];
        $oDal->setReturnReference('getDeliveredEcpmCampainImpressionsByAgency', $aCampaignsImpressions);

        // Partially mock the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class
        $oEcpm = new PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant($this);
        $oEcpm->setReturnReference('_factoryDal', $oDal);
        (new ReflectionMethod(OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant::class, '__construct'))->invoke($oEcpm);

        $oEcpm->preloadCampaignsDeliveredImpressionsForAgency(123);
        $this->assertEqual($aCampaignsImpressions, $oEcpm->aCampaignsDeliveredImpressions);

        // preload another agency and check that array is unchanged
        $oEcpm->preloadCampaignsDeliveredImpressionsForAgency(255);
        $this->assertEqual($aCampaignsImpressions, $oEcpm->aCampaignsDeliveredImpressions);
    }

    /**
     * A method to test the preloadCampaignsDeliveredImpressionsForAgency() method.
     *
     * Requirements
     * Test 1: Test that campaign impressions are correctly preloaded
     */
    public function testPrepareCampaignsParameters()
    {
        $aCampaignsInfo = [];
        $aEcpm = [];
        $aCampaignsDeliveredImpressions = [];
        $aExpAdsEcpmPowAlpha = [];
        $aExpZonesEcpmPowAlphaSums = [];
        $aExpAdsMinImpressions = [];

        // 2 operation intervals left to the end of the day
        // (to deliver all minimum impressions)
        $leftOi = 2;

        ///////////////////////////////////////////////////
        // one ad linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId1 = 1] = [
            self::IDX_ADS => [
                $adId1 = 1 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId1 = 1],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 100,
        ];
        $aEcpm[$campaignId1] = 0.5;
        $aCampaignsDeliveredImpressions[$campaignId1] = 0;
        $aExpAdsEcpmPowAlpha[$adId1] = pow(0.5, self::ALPHA);
        $aExpZonesEcpmPowAlphaSums[$zoneId1] = $aExpAdsEcpmPowAlpha[$adId1];
        // all minimum impressions go to the only ad
        $aExpAdsMinImpressions[$adId1] = $min / $leftOi;

        ///////////////////////////////////////////////////
        // one ad linked to two zones
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId2 = 2] = [
            self::IDX_ADS => [
                $adId2 = 2 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId2 = 2, $zoneId3 = 3],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 200,
        ];
        $aEcpm[$campaignId2] = 0.6;
        $aCampaignsDeliveredImpressions[$campaignId2] = $delivered = 100; // half delivered
        $aExpAdsEcpmPowAlpha[$adId2] = pow(0.6, self::ALPHA);
        $aExpZonesEcpmPowAlphaSums[$zoneId2] =
            $aExpZonesEcpmPowAlphaSums[$zoneId3] =
            $aExpAdsEcpmPowAlpha[$adId2];
        // all left minimum impressions go to the only ad
        $aExpAdsMinImpressions[$adId2] = ($min - $delivered) / $leftOi;

        ///////////////////////////////////////////////////
        // two ads linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId3 = 3] = [
            self::IDX_ADS => [
                $adId3 = 3 => [
                    self::IDX_WEIGHT => $w1 = 1,
                    self::IDX_ZONES => [$zoneId4 = 4],
                ],
                $adId4 = 4 => [
                    self::IDX_WEIGHT => $w2 = 2, // different weights
                    self::IDX_ZONES => [$zoneId4],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 300,
        ];
        $aEcpm[$campaignId3] = 0.7;
        $aCampaignsDeliveredImpressions[$campaignId3] = $delivered = 200;
        $aExpAdsEcpmPowAlpha[$adId3] = pow(0.7, self::ALPHA);
        $aExpAdsEcpmPowAlpha[$adId4] = pow(0.7, self::ALPHA);
        $aExpZonesEcpmPowAlphaSums[$zoneId4] =
            $aExpAdsEcpmPowAlpha[$adId3] + $aExpAdsEcpmPowAlpha[$adId4];
        // all left minimum impressions go to two ads based on their weights
        $sumW = $w1 + $w2;
        $toDeliverInNextOI = ($min - $delivered) / $leftOi;
        $aExpAdsMinImpressions[$adId3] = $w1 / $sumW * $toDeliverInNextOI;
        $aExpAdsMinImpressions[$adId4] = $w2 / $sumW * $toDeliverInNextOI;

        ///////////////////////////////////////////////////
        // simple scenario for the case when all impr. are delivered already
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId4 = 4] = [
            self::IDX_ADS => [
                $adId5 = 5 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId5 = 5],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => 100,
        ];
        $aEcpm[$campaignId4] = 0.8;
        $aCampaignsDeliveredImpressions[$campaignId4] = 100; // all delivered
        $aExpAdsEcpmPowAlpha[$adId5] = pow(0.8, self::ALPHA);
        $aExpZonesEcpmPowAlphaSums[$zoneId5] =
            $aExpAdsEcpmPowAlpha[$adId5];
        $aExpAdsMinImpressions[$adId5] = 0; // all min. impressions delivered

        // Partially mock the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class
        $oEcpm = new PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant($this);
        // lets assume only two intervals are left till the end of the day
        $oEcpm->setReturnValue('getTodaysRemainingOperationIntervals', $leftOi);
        foreach ($aEcpm as $campId => $ecpm) {
            $oEcpm->setReturnValue('calculateCampaignEcpm', $ecpm, [$campId, '*']);
        }
        // Impressions delivered today in eahc of campaigns
        $oEcpm->aCampaignsDeliveredImpressions = $aCampaignsDeliveredImpressions;

        // Test
        $oEcpm->prepareCampaignsParameters($aCampaignsInfo);

        $this->assertEqual($aExpAdsEcpmPowAlpha, $oEcpm->aAdsEcpmPowAlpha);
        $this->assertEqual($aExpZonesEcpmPowAlphaSums, $oEcpm->aZonesEcpmPowAlphaSums);
        $this->assertEqualsFloatsArray($aExpAdsMinImpressions, $oEcpm->aAdsMinImpressions);
    }

    /**
     * A method to test the calculateAdsZonesMinimumRequiredImpressions() method.
     *
     * Requirements
     * Test 1: Test that minimum ads/zones pair are correctly calculated
     */
    public function testCalculateAdsZonesMinimumRequiredImpressions()
    {
        // we assume that only one operation interval is left to the end of the day
        // (it doesn't impact the test and will make calculations easier)
        // prepare test data
        $aCampaignsInfo = [];
        $aAdsMinImpressions = [];
        $aZonesAvailableImpressions = [];

        $aExpAdZonesMinImpressions = [];

        ///////////////////////////////////////////////////
        // one ad linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId1 = 1] = [
            self::IDX_ADS => [
                $adId1 = 1 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId1 = 1],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 100,
        ];
        // less impressions available than the required minumum
        $aZonesAvailableImpressions[$zoneId1] = 10;
        // can't get more impressions if a contract is smaller than
        // the required minimum
        $aExpAdZonesMinImpressions[$adId1][$zoneId1] = 10;

        ///////////////////////////////////////////////////
        // one ad linked to two zones
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId2 = 2] = [
            self::IDX_ADS => [
                $adId2 = 2 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId2 = 2, $zoneId3 = 3],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 200,
        ];
        $aEcpm[$campaignId2] = 0.6;
        // as many impressions in first zone as in second, sum equal to required minimum
        $aZonesAvailableImpressions[$zoneId2] = 100;
        $aZonesAvailableImpressions[$zoneId3] = 100;
        // ad should get all impr. in both zones
        $aExpAdZonesMinImpressions[$adId2][$zoneId2] = 100;
        $aExpAdZonesMinImpressions[$adId2][$zoneId3] = 100;

        ///////////////////////////////////////////////////
        // two ads linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId3 = 3] = [
            self::IDX_ADS => [
                $adId3 = 3 => [
                    self::IDX_WEIGHT => $w1 = 1,
                    self::IDX_ZONES => [$zoneId4 = 4],
                ],
                $adId4 = 4 => [
                    self::IDX_WEIGHT => $w2 = 2, // different weights
                    self::IDX_ZONES => [$zoneId4],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 300,
        ];
        $aEcpm[$campaignId3] = 0.7;
        // all left minimum impressions go to two ads based on their weights
        $sumW = $w1 + $w2;
        // twice as many impressions as required
        $aZonesAvailableImpressions[$zoneId4] = 600;
        // ad should get impr. in both zones according to their weights
        $aExpAdZonesMinImpressions[$adId3][$zoneId4] = $w1 / $sumW * $min;
        $aExpAdZonesMinImpressions[$adId4][$zoneId4] = $w2 / $sumW * $min;

        // Partially mock the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class
        $oEcpm = new PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant($this);
        $oEcpm->setReturnValue('getTodaysRemainingOperationIntervals', 1);
        foreach ($aEcpm as $campId => $ecpm) {
            $oEcpm->setReturnValue('calculateCampaignEcpm', $ecpm, [$campId, '*']);
        }
        $oEcpm->aCampaignsDeliveredImpressions = []; // nothing was delivered so far
        // precalculate the min/required impressions per ad
        $oEcpm->prepareCampaignsParameters($aCampaignsInfo);
        $oEcpm->aZonesAvailableImpressions = $aZonesAvailableImpressions;

        // Test
        $aAdsZonesMinImpressions = $oEcpm->calculateAdsZonesMinimumRequiredImpressions($aCampaignsInfo);
        $this->assertEqual($aExpAdZonesMinImpressions, $aAdsZonesMinImpressions);
    }

    /**
     * A method to test the calculateDeliveryProbabilities() method.
     *
     * Requirements
     * Test 1: Test that method calculates correct probabilities for each ad/zone
     */
    public function testCalculateDeliveryProbabilities()
    {
        $aExpAdZonesProbabilities = [];
        $aZonesAvailableImpressions = [];
        ///////////////////////////////////////////////////
        // one ad linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId1 = 1] = [
            self::IDX_ADS => [
                $adId1 = 1 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId1 = 1],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 100,
        ];
        $aEcpm[$campaignId1] = 0.1;
        // less impressions available than the required minumum
        $aZonesAvailableImpressions[$zoneId1] = 10;
        $aExpAdZonesProbabilities[$adId1][$zoneId1] = 1;

        ///////////////////////////////////////////////////
        // one ad linked to two zones
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId2 = 2] = [
            self::IDX_ADS => [
                $adId2 = 2 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId2 = 2, $zoneId3 = 3],
                ]
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 200,
        ];
        $aEcpm[$campaignId2] = 0.6;
        // as many impressions in first zone as in second, sum equal to required minimum
        $aZonesAvailableImpressions[$zoneId2] = 100;
        $aZonesAvailableImpressions[$zoneId3] = 100;
        // separate zones, so each zone get 100%
        $aExpAdZonesProbabilities[$adId2][$zoneId2] = 1;
        $aExpAdZonesProbabilities[$adId2][$zoneId3] = 1;

        ///////////////////////////////////////////////////
        // two ads linked to one zone
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId3 = 3] = [
            self::IDX_ADS => [
                $adId3 = 3 => [
                    self::IDX_WEIGHT => $w1 = 1,
                    self::IDX_ZONES => [$zoneId4 = 4],
                ],
                $adId4 = 4 => [
                    self::IDX_WEIGHT => $w2 = 2, // different weights
                    self::IDX_ZONES => [$zoneId4],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 300,
        ];
        $aEcpm[$campaignId3] = 0.7;
        $aZonesAvailableImpressions[$zoneId4] = $M = 600;
        // actual algorithm
        $ecpmPow = pow(0.7, self::ALPHA);
        $ecpmZoneSum = $ecpmPow + $ecpmPow;
        $p = $ecpmPow / $ecpmZoneSum;

        $sumW = $w1 + $w2;
        $adMinImpr1 = $w1 / $sumW * $min;
        $adMinImpr2 = $w2 / $sumW * $min;
        $sumAdMinImpr = $adMinImpr1 + $adMinImpr2;

        $aExpAdZonesProbabilities[$adId3][$zoneId4] =
            $adMinImpr1 / $M + (1 - $sumAdMinImpr / $M) * $p;
        $aExpAdZonesProbabilities[$adId4][$zoneId4] =
            $adMinImpr2 / $M + (1 - $sumAdMinImpr / $M) * $p;

        ///////////////////////////////////////////////////
        // two ads linked to one zone
        //////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId3 = 3] = [
            self::IDX_ADS => [
                $adId3 = 3 => [
                    self::IDX_WEIGHT => $w1 = 1,
                    self::IDX_ZONES => [$zoneId4 = 4],
                ],
                $adId4 = 4 => [
                    self::IDX_WEIGHT => $w2 = 2, // different weights
                    self::IDX_ZONES => [$zoneId4],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 300,
        ];
        $aEcpm[$campaignId3] = 0.7;
        $aZonesAvailableImpressions[$zoneId4] = $M = 600;
        // actual algorithm
        $ecpmPow = pow(0.7, self::ALPHA);
        $ecpmZoneSum = $ecpmPow + $ecpmPow;
        $p = $ecpmPow / $ecpmZoneSum;

        $sumW = $w1 + $w2;
        $adMinImpr1 = $w1 / $sumW * $min;
        $adMinImpr2 = $w2 / $sumW * $min;
        $sumAdMinImpr = $adMinImpr1 + $adMinImpr2;

        $aExpAdZonesProbabilities[$adId3][$zoneId4] =
            $adMinImpr1 / $M + (1 - $sumAdMinImpr / $M) * $p;
        $aExpAdZonesProbabilities[$adId4][$zoneId4] =
            $adMinImpr2 / $M + (1 - $sumAdMinImpr / $M) * $p;

        ///////////////////////////////////////////////////
        // two campaigns with different eCPM linked to one zone
        //////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId4 = 4] = [
            self::IDX_ADS => [
                $adId5 = 5 => [
                    self::IDX_WEIGHT => $w1 = 1,
                    self::IDX_ZONES => [$zoneId5 = 5],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 100,
        ];
        $aEcpm[$campaignId4] = 0.5;
        $aCampaignsInfo[$campaignId5 = 5] = [
            self::IDX_ADS => [
                $adId6 = 6 => [
                    self::IDX_WEIGHT => $w2 = 1,
                    self::IDX_ZONES => [$zoneId5 = 5],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min = 100,
        ];
        $aEcpm[$campaignId5] = 1.0;
        $aZonesAvailableImpressions[$zoneId5] = $M = 1000;
        // actual algorithm
        $ecpmPow1 = pow(0.5, self::ALPHA);
        $ecpmPow2 = pow(1.0, self::ALPHA);
        $ecpmZoneSum = $ecpmPow1 + $ecpmPow2;
        $p1 = $ecpmPow1 / $ecpmZoneSum;
        $p2 = $ecpmPow2 / $ecpmZoneSum;

        $adMinImpr1 = $min;
        $adMinImpr2 = $min;
        $sumAdMinImpr = $adMinImpr1 + $adMinImpr2;

        $aExpAdZonesProbabilities[$adId5][$zoneId5] =
            $adMinImpr1 / $M + (1 - $sumAdMinImpr / $M) * $p1;
        $aExpAdZonesProbabilities[$adId6][$zoneId5] =
            $adMinImpr2 / $M + (1 - $sumAdMinImpr / $M) * $p2;

        ///////////////////////////////////////////////////
        // two ads linked to one zone -
        //      case with too many testing impressions required
        ///////////////////////////////////////////////////
        $aCampaignsInfo[$campaignId6 = 6] = [
            self::IDX_ADS => [
                $adId7 = 7 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId6 = 6],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min1 = 100,
        ];
        $aEcpm[$campaignId6] = 0.5;
        $aCampaignsInfo[$campaignId7 = 7] = [
            self::IDX_ADS => [
                $adId8 = 8 => [
                    self::IDX_WEIGHT => 1,
                    self::IDX_ZONES => [$zoneId6 = 6],
                ],
            ],
            self::IDX_MIN_IMPRESSIONS => $min2 = 200,
        ];
        $aEcpm[$campaignId7] = 1.0;

        $aZonesAvailableImpressions[$zoneId6] = $M = 100; // less than $min1 + $min2
        // algorithm never tries to deliver more impressions than estimated
        // available impressions - takes minimum
        $min1 = min($min1, $M);
        $min2 = min($min2, $M);
        // probabilities
        $aExpAdZonesProbabilities[$adId7][$zoneId6] =
            $min1 / ($min1 + $min2);
        $aExpAdZonesProbabilities[$adId8][$zoneId6] =
            $min2 / ($min1 + $min2);
        ////////////////////////////////////////////////

        // Partially mock the OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant class
        $oEcpm = new PartialMock_OA_Maintenance_Priority_AdServer_Task_ECPMforRemnant($this);
        $oEcpm->setReturnValue('getTodaysRemainingOperationIntervals', 1);
        foreach ($aEcpm as $campId => $ecpm) {
            $oEcpm->setReturnValue('calculateCampaignEcpm', $ecpm, [$campId, '*']);
        }
        $oEcpm->aCampaignsDeliveredImpressions = []; // nothing was delivered so far
        // precalculate the min/required impressions per ad
        $oEcpm->aZonesAvailableImpressions = $aZonesAvailableImpressions;
        $oEcpm->prepareCampaignsParameters($aCampaignsInfo);

        // Test
        $aAdZonesProbabilities = $oEcpm->calculateDeliveryProbabilities($aCampaignsInfo);
        $this->assertEqualsFloatsArray($aExpAdZonesProbabilities, $aAdZonesProbabilities);
    }
}
