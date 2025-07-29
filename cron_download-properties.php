<?php
// ini_set('display_errors', 'on');

if (PHP_SAPI !== 'cli')
{
	echo 'Attempting to run CLI application outside of terminal; exiting…' . PHP_EOL;
	exit(126); // Command invoked cannot execute
}
// return false;
setlocale(LC_MONETARY, 'en_US');

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;

define('_JEXEC', 1);
define('JPATH_BASE', '/home/USERNAME/path/to/joomla');

$canFormat = class_exists('NumberFormatter');
$jfours    = [4,5];

if ($canFormat === true)
{
	$money_format = new \NumberFormatter('en_US', NumberFormatter::CURRENCY);
}

require_once(JPATH_BASE . '/includes/defines.php');
require_once(JPATH_BASE . '/includes/framework.php');
require_once(__DIR__ . '/phpidx.php');

if (in_array(Version::MAJOR_VERSION, $jfours))
{
	$container = Factory::getContainer();
	$container->alias('session', 'session.cli')
		->alias('JSession', 'session.cli')
		->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

	$app = $container->get(\Joomla\Console\Application::class);
	$app->createExtensionNamespaceMap(); // https://joomla.stackexchange.com/a/32146/41
	// $app->loadLanguage(); /* allows modules to render */

	// Set the application as global app
	Factory::$application = $app;
}
else
{
	$app = Factory::getApplication('site');
	$app->initialise();
}

// $user          = Factory::getUser();
$utc_tz        = new DateTimeZone('UTC');
$today         = new DateTime(null, $utc_tz);
$cmpt_params   = ComponentHelper::getParams('com_idxproperties');
$sitename      = $app->get('sitename');
$rentals_catid = (int) $cmpt_params->get('rentals_category', 0);
$land_catid    = (int) $cmpt_params->get('land_category', 0);
$res_catid     = (int) $cmpt_params->get('res_category', 0);
$comm_catid    = (int) $cmpt_params->get('comm_category', 0);

$abbrevs        = json_decode(file_get_contents(__DIR__ . '/state-abbreviations.json'), true);
$counter        = 0;
$has_more       = false;
$offset         = 25;
$page_number    = 0;
$current_offset = $page_number * $offset;
$properties     = array();

$idx        = new PhpIdx();
$items      = $idx->activeProperties('featured', $current_offset);
$states     = $idx->getStateList();
$has_more   = (isset($items['next']) && !is_null($items['next'])) ? true : false;
$prop_types = array(/*'Multi-Family', 'Multifamily Residential',*/ 'Residential', 'Single Family Residential');

$properties = $items['data'];

while($has_more !== false)
{
	$page_number++;
	$current_offset = $page_number * $offset;

	$items    = $idx->activeProperties('featured', $current_offset);
	$has_more = (isset($items['next']) && !is_null($items['next'])) ? true : false;

	if (isset($items['data']) && !empty($items['data']))
	{
		$properties = array_merge($properties, $items['data']);
	}
}

if (empty($properties))
{
	// var_export($idx); echo PHP_EOL;

	return false;
}

$db    = Factory::getDbo();
$query = $db->getQuery(true);

try
{
	$db->transactionStart();

	$query->clear();
	$query->update($db->quoteName('#__idxproperties'))->set('state = 0')->where('state = 1');
	$db->setQuery($query);
	$db->execute();
	$query->clear();

	foreach ($properties as $key => $property)
	{
		$catid      = 0;
		// …why are the API results not including internalID anymore!?
		// $internalID = isset($property['internalID']) ? $property['internalID'] : '';
		$internalID = isset($property['listingID']) ? $property['listingID'] : '';

		$possibleUse              = '';
		$roadFrontageType         = '';
		$roadResponsibility       = '';
		$specialListingConditions = '';
		$structureType            = '';
		$utilities                = '';
		$appliances               = '';
		$interiorFeatures         = '';
		$fireplaceFeatures        = '';
		$constructionMaterials    = '';
		$flooring                 = '';
		$foundationDetails        = '';
		$heating                  = '';
		$laundryFeatures          = '';
		$lotFeatures              = '';
		$parkingFeatures          = '';
		$roadSurfaceType          = '';
		$roof                     = '';
		$sewer                    = '';
		$communityFeatures        = '';
		$waterSource              = '';
		$associationFeeFrequency  = '';
		$ownershipType            = '';

		$query->clear();
		$query->select('id')->from($db->quoteName('#__idxproperties'))->where('internalID = ' . $db->quote($internalID))->setLimit(1);

		$db->setQuery($query);

		$existing = (int) $db->loadResult();

		$query->clear();

		switch((string) $property['idxPropType'])
		{
			case 'Land' :
				$catid = $land_catid;
				break;
			case 'Commercial' :
			case 'Commercial Sale' :
				$catid = $comm_catid;
				break;
			case 'Residential' :
			case 'Residential Income' :
				$catid = $res_catid;
				break;
			case 'Residential Lease' :
				$catid = $rentals_catid;
				break;
		}

		$images    = array();
		$idxPhotos = array();

		if (isset($property['image']['totalCount']) && $property['image']['totalCount'] > 0)
		{
			for($i = 0; $i < $property['image']['totalCount']; $i++)
			{
				$idxPhotos[$i]['url']     = $property['image'][$i]['url'];
				$idxPhotos[$i]['caption'] = $property['image'][$i]['caption'];

				// echo $property['image'][$i]['url'] . PHP_EOL;
			}
		}

		$images['image_intro']        = $property['image'][0]['url'];
		$images['image_intro_alt']    = $property['image'][0]['caption'];
		$images['image_fulltext']     = $property['image'][0]['url'];
		$images['image_fulltext_alt'] = $property['image'][0]['caption'];

		$registry = new Registry($images);
		$images   = $registry->toString();

		$registry  = new Registry($idxPhotos);
		$idxPhotos = $registry->toString();
		// echo PHP_EOL . $idxPhotos . PHP_EOL;

		$listingContractDate = isset($property['advanced']['listingContractDate']) ? new DateTime($property['advanced']['listingContractDate'], $utc_tz) : null;
		$dateModified        = isset($property['dateModified']) ? new DateTime($property['dateModified'], $utc_tz) : null;

		if (isset($property['advanced']['possibleUse']))
		{
			$possibleUse = new Registry($property['advanced']['possibleUse']);
			$possibleUse = $possibleUse->toString();
		}

		if (isset($property['advanced']['roadFrontageType']))
		{
			$roadFrontageType = new Registry($property['advanced']['roadFrontageType']);
			$roadFrontageType = $roadFrontageType->toString();
		}

		if (isset($property['advanced']['roadResponsibility']))
		{
			$roadResponsibility = new Registry($property['advanced']['roadResponsibility']);
			$roadResponsibility = $roadResponsibility->toString();
		}

		if (isset($property['advanced']['specialListingConditions']))
		{
			$specialListingConditions = new Registry($property['advanced']['specialListingConditions']);
			$specialListingConditions = $specialListingConditions->toString();
		}

		if (isset($property['advanced']['structureType']))
		{
			$structureType = new Registry($property['advanced']['structureType']);
			$structureType = $structureType->toString();
		}

		if (isset($property['advanced']['utilities']))
		{
			$utilities = new Registry($property['advanced']['utilities']);
			$utilities = $utilities->toString();
		}

		if (isset($property['advanced']['appliances']))
		{
			$appliances              = new Registry($property['advanced']['appliances']);
			$appliances              = $appliances->toString();
		}

		if (isset($property['advanced']['interiorFeatures']))
		{
			$interiorFeatures        = new Registry($property['advanced']['interiorFeatures']);
			$interiorFeatures        = $interiorFeatures->toString();
		}

		if (isset($property['advanced']['fireplaceFeatures']))
		{
			$fireplaceFeatures       = new Registry($property['advanced']['fireplaceFeatures']);
			$fireplaceFeatures       = $fireplaceFeatures->toString();
		}

		if (isset($property['advanced']['constructionMaterials']))
		{
			$constructionMaterials   = new Registry($property['advanced']['constructionMaterials']);
			$constructionMaterials   = $constructionMaterials->toString();
		}

		if (isset($property['advanced']['flooring']))
		{
			$flooring                = new Registry($property['advanced']['flooring']);
			$flooring                = $flooring->toString();
		}

		if (isset($property['advanced']['foundationDetails']))
		{
			$foundationDetails       = new Registry($property['advanced']['foundationDetails']);
			$foundationDetails       = $foundationDetails->toString();
		}

		if (isset($property['advanced']['heating']))
		{
			$heating                 = new Registry($property['advanced']['heating']);
			$heating                 = $heating->toString();
		}

		if (isset($property['advanced']['laundryFeatures']))
		{
			$laundryFeatures         = new Registry($property['advanced']['laundryFeatures']);
			$laundryFeatures         = $laundryFeatures->toString();
		}

		if (isset($property['advanced']['lotFeatures']))
		{
			$lotFeatures             = new Registry($property['advanced']['lotFeatures']);
			$lotFeatures             = $lotFeatures->toString();
		}

		if (isset($property['advanced']['parkingFeatures']))
		{
			$parkingFeatures         = new Registry($property['advanced']['parkingFeatures']);
			$parkingFeatures         = $parkingFeatures->toString();
		}

		if (isset($property['advanced']['roadSurfaceType']))
		{
			$roadSurfaceType         = new Registry($property['advanced']['roadSurfaceType']);
			$roadSurfaceType         = $roadSurfaceType->toString();
		}

		if (isset($property['advanced']['roof']))
		{
			$roof                    = new Registry($property['advanced']['roof']);
			$roof                    = $roof->toString();
		}

		if (isset($property['advanced']['sewer']))
		{
			$sewer                   = new Registry($property['advanced']['sewer']);
			$sewer                   = $sewer->toString();
		}

		if (isset($property['advanced']['communityFeatures']))
		{
			$communityFeatures       = new Registry($property['advanced']['communityFeatures']);
			$communityFeatures       = $communityFeatures->toString();
		}

		if (isset($property['advanced']['waterSource']))
		{
			$waterSource             = new Registry($property['advanced']['waterSource']);
			$waterSource             = $waterSource->toString();
		}

		if (isset($property['advanced']['associationFeeFrequency']))
		{
			$associationFeeFrequency = new Registry($property['advanced']['associationFeeFrequency']);
			$associationFeeFrequency = $associationFeeFrequency->toString();
		}

		if (isset($property['advanced']['ownershipType']))
		{
			$ownershipType           = new Registry($property['advanced']['ownershipType']);
			$ownershipType           = $ownershipType->toString();
		}

		// Update item details
		$item                                      = new stdClass();
		$item->id                                  = ($existing > 0) ? (int) $existing : null;
		$item->catid                               = (int) $catid;
		$item->title                               = $property['address'] . ' (' . $property['listingID'] . ')';
		$item->alias                               = OutputFilter::stringUrlSafe($item->title);
		$item->acres                               = isset($property['acres']) ? $property['acres'] : 0;
		$item->bedrooms                            = isset($property['bedrooms']) ? $property['bedrooms'] : 0;
		$item->addressString                       = $property['address'];
		$item->buildingAreaTotal                   = isset($property['advanced']['buildingAreaTotal']) ? (float) $property['advanced']['buildingAreaTotal'] : 0;
		$item->carBuyerAgentSaleYN                 = isset($property['advanced']['carBuyerAgentSaleYN']) ? $property['advanced']['carBuyerAgentSaleYN'] : '';
		$item->carCeilingHeightFt                  = isset($property['advanced']['carCeilingHeightFt']) ? (float) $property['advanced']['carCeilingHeightFt'] : 0;
		$item->carCeilingHeightIn                  = isset($property['advanced']['carCeilingHeightIn']) ? (float) $property['advanced']['carCeilingHeightIn'] : 0;
		$item->carDeedReference                    = isset($property['advanced']['carDeedReference']) ? $property['advanced']['carDeedReference'] : '';
		$item->carDocuments                        = isset($property['advanced']['carDocuments']) ? $property['advanced']['carDocuments'] : '';
		$item->carHoaSubjectTo                     = isset($property['advanced']['carHoaSubjectTo']) ? $property['advanced']['carHoaSubjectTo'] : '';
		$item->carInsideCityYN                     = isset($property['advanced']['carInsideCityYN']) ? $property['advanced']['carInsideCityYN'] : '';
		$item->carNumberOfDriveInDoorsTotal        = isset($property['advanced']['carNumberOfDriveInDoorsTotal']) ? (int) $property['advanced']['carNumberOfDriveInDoorsTotal'] : 0;
		$item->carOwnerAgentYN                     = isset($property['advanced']['carOwnerAgentYN']) ? $property['advanced']['carOwnerAgentYN'] : '';
		$item->carPermitSyndicationYN              = isset($property['advanced']['carPermitSyndicationYN']) ? $property['advanced']['carPermitSyndicationYN'] : '';
		$item->carPropertySubTypeSecondary         = isset($property['advanced']['carPropertySubTypeSecondary']) ? $property['advanced']['carPropertySubTypeSecondary'] : '';
		$item->carRValueCeiling                    = isset($property['advanced']['carRValueCeiling']) ? (float) $property['advanced']['carRValueCeiling'] : 0;
		$item->carRestrictions                     = isset($property['advanced']['carRestrictions']) ? $property['advanced']['carRestrictions'] : '';
		$item->carRestrictionsDescription          = isset($property['advanced']['carRestrictionsDescription']) ? $property['advanced']['carRestrictionsDescription'] : '';
		$item->carRoadFrontage                     = isset($property['advanced']['carRoadFrontage']) ? (float) $property['advanced']['carRoadFrontage'] : 0;
		$item->carSqFtAvailableMaximum             = isset($property['advanced']['carSqFtAvailableMaximum']) ? (float) $property['advanced']['carSqFtAvailableMaximum'] : 0;
		$item->carSqFtAvailableMinimum             = isset($property['advanced']['carSqFtAvailableMinimum']) ? (float) $property['advanced']['carSqFtAvailableMinimum'] : 0;
		$item->carSqFtMaximumLease                 = isset($property['advanced']['carSqFtMaximumLease']) ? (float) $property['advanced']['carSqFtMaximumLease'] : 0;
		$item->carSqFtMinimumLease                 = isset($property['advanced']['carSqFtMinimumLease']) ? (float) $property['advanced']['carSqFtMinimumLease'] : 0;
		$item->carSqFtMain                         = isset($property['advanced']['carSqFtMain']) ? (float) $property['advanced']['carSqFtMain'] : 0;
		$item->carSqFtUnheatedTotal                = isset($property['advanced']['carSqFtUnheatedTotal']) ? (float) $property['advanced']['carSqFtUnheatedTotal'] : 0;
		$item->carSqFtUpper                        = isset($property['advanced']['carSqFtUpper']) ? (float) $property['advanced']['carSqFtUpper'] : 0;
		$item->carTable                            = isset($property['advanced']['carTable']) ? $property['advanced']['carTable'] : '';
		$item->carTransactionType                  = isset($property['advanced']['carTransactionType']) ? $property['advanced']['carTransactionType'] : '';
		$item->carUnitCount                        = isset($property['advanced']['carUnitCount']) ? (int) $property['advanced']['carUnitCount'] : 0;
		$item->cumulativeDaysOnMarket              = isset($property['advanced']['cumulativeDaysOnMarket']) ? (int) $property['advanced']['cumulativeDaysOnMarket'] : 0;
		$item->daysOnMarket                        = isset($property['advanced']['daysOnMarket']) ? (int) $property['advanced']['daysOnMarket'] : 0;
		$item->inclusions                          = isset($property['advanced']['inclusions']) ? $property['advanced']['inclusions'] : '';
		$item->listAgentDirectPhone                = isset($property['advanced']['listAgentDirectPhone']) ? $property['advanced']['listAgentDirectPhone'] : '';
		$item->listAgentFullName                   = isset($property['advanced']['listAgentFullName']) ? $property['advanced']['listAgentFullName'] : '';
		$item->listAgentMlsId                      = isset($property['advanced']['listAgentMlsId']) ? $property['advanced']['listAgentMlsId'] : '';
		$item->listOfficeMlsId                     = isset($property['advanced']['listOfficeMlsId']) ? $property['advanced']['listOfficeMlsId'] : '';
		$item->listOfficePhone                     = isset($property['advanced']['listOfficePhone']) ? $property['advanced']['listOfficePhone'] : '';
		$item->listingContractDate                 = !empty($listingContractDate) ? $listingContractDate->format('Y-m-d H:i:s') : null;
		$item->listingOfficeName                   = isset($property['advanced']['listingOfficeName']) ? $property['advanced']['listingOfficeName'] : '';
		$item->lotSizeDimensions                   = isset($property['advanced']['lotSizeDimensions']) ? (float) $property['advanced']['lotSizeDimensions'] : 0.00;
		$item->newConstructionYN                   = isset($property['advanced']['newConstructionYN']) ? $property['advanced']['newConstructionYN'] : '';
		$item->numberOfUnitsLeased                 = isset($property['advanced']['numberOfUnitsLeased']) ? (int) $property['advanced']['numberOfUnitsLeased'] : 0;
		$item->possibleUse                         = $possibleUse;
		$item->roadFrontageType                    = $roadFrontageType;
		$item->roadResponsibility                  = $roadResponsibility;
		$item->specialListingConditions            = $specialListingConditions;
		$item->structureType                       = $structureType;
		$item->syndicationRemarks                  = isset($property['advanced']['syndicationRemarks']) ? $property['advanced']['syndicationRemarks'] : '';
		$item->utilities                           = $utilities;
		$item->zoningDescription                   = $property['advanced']['zoningDescription'];
		$item->cityName                            = $property['cityName'];
		$item->countyName                          = $property['countyName'];
		$item->dateModified                        = !empty($dateModified) ? $dateModified->format('Y-m-d H:i:s') : null;
		$item->displayAddress                      = $property['displayAddress'];
		$item->idxFeatured                         = $property['featured'];
		$item->fullDetailsURL                      = $property['fullDetailsURL'];
		$item->idxID                               = $property['idxID'];
		$item->idxPropType                         = $property['idxPropType'];
		$item->idxStatus                           = $property['idxStatus'];
		$item->idxPhotos                           = $idxPhotos;
		$item->images                              = $images;
		$item->internalID                          = $internalID;
		$item->latitude                            = (float) $property['latitude'];
		$item->listingAgentID                      = $property['listingAgentID'];
		$item->listingID                           = $property['listingID'];
		$item->listingOfficeID                     = $property['listingOfficeID'];
		$item->listingPrice                        = $property['listingPrice'];
		$item->longitude                           = (float) $property['longitude'];
		$item->partialBaths                        = (int) $property['partialBaths'];
		$item->price                               = (int) $property['price'];
		$item->propStatus                          = $property['propStatus'];
		$item->propSubType                         = $property['propSubType'];
		$item->propType                            = $property['propType'];
		$item->remarksConcat                       = $property['remarksConcat'];
		$item->rntLse                              = $property['rntLse'];
		$item->rntLsePrice                         = (int) $property['rntLsePrice'];
		$item->sqFt                                = isset($property['sqFt']) ? floatval(str_ireplace(',', '', $property['sqFt'])) : 0;
		$item->addressState                        = $property['state'];
		$item->addressStateAbbr                    = $states[$property['state']];
		$item->streetName                          = $property['streetName'];
		$item->streetNumber                        = (int) $property['streetNumber'];
		$item->subdivision                         = $property['subdivision'];
		$item->totalBaths                          = (int) $property['totalBaths'];
		$item->fullBaths                           = isset($property['fullBaths']) ? (int) $property['fullBaths'] : 0;
		$item->yearBuilt                           = isset($property['yearBuilt']) ? $property['yearBuilt'] : '';
		$item->zipcode                             = $property['zipcode'];
		$item->appliances                          = $appliances;
		$item->associationFee                      = $property['advanced']['associationFee'];
		$item->associationFeeFrequency             = $associationFeeFrequency;
		$item->associationName                     = isset($property['advanced']['associationName']) ? $property['advanced']['associationName'] : '';
		$item->carComplexName                      = isset($property['advanced']['carComplexName']) ? $property['advanced']['carComplexName'] : '';
		$item->carConstructionType                 = isset($property['advanced']['carConstructionType']) ? $property['advanced']['carConstructionType'] : '';
		$item->carLandIncludedYN                   = isset($property['advanced']['carLandIncludedYN']) ? $property['advanced']['carLandIncludedYN'] : '';
		$item->carHoaSubjectToDues                 = isset($property['advanced']['carHoaSubjectToDues']) ? $property['advanced']['carHoaSubjectToDues'] : '';
		$item->communityFeatures                   = $communityFeatures;
		$item->constructionMaterials               = $constructionMaterials;
		$item->elementarySchool                    = $property['advanced']['elementarySchool'];
		$item->elevation                           = $property['advanced']['elevation'];
		$item->fireplaceFeatures                   = $fireplaceFeatures;
		$item->flooring                            = $flooring;
		$item->foundationDetails                   = $foundationDetails;
		$item->heating                             = $heating;
		$item->highSchool                          = $property['advanced']['highSchool'];
		$item->interiorFeatures                    = $interiorFeatures;
		$item->internetAutomatedValuationDisplayYN = isset($property['advanced']['internetAutomatedValuationDisplayYN']) ? $property['advanced']['internetAutomatedValuationDisplayYN'] : '';
		$item->internetConsumerCommentYN           = isset($property['advanced']['internetConsumerCommentYN']) ? $property['advanced']['internetConsumerCommentYN'] : '';
		$item->laundryFeatures                     = $laundryFeatures;
		$item->lotFeatures                         = $lotFeatures;
		$item->ownershipType                       = $ownershipType;
		$item->parkingFeatures                     = $parkingFeatures;
		$item->priceBeforeReduction                = isset($property['advanced']['priceBeforeReduction']) ? $property['advanced']['priceBeforeReduction'] : 0;
		$item->priceReductionDate                  = isset($property['advanced']['priceReductionDate']) ? $property['advanced']['priceReductionDate'] : null;
		$item->roadSurfaceType                     = $roadSurfaceType;
		$item->roof                                = $roof;
		$item->sewer                               = $sewer;
		$item->waterSource                         = $waterSource;
		// $item->modified                            = $dateModified->format('Y-m-d H:i:s');
		$item->modified                            = $today->format('Y-m-d H:i:s');
		$item->access                              = 1;
		$item->state                               = 1;
		$item->language                            = '*';

		$active_status = array('Coming Soon', 'Active');

		/* if (!in_array($property['propStatus'], $active_status))
		{
			$item->state = 0;
		} */

		if ($existing > 0)
		{
			$queryResult = $db->updateObject('#__idxproperties', $item, 'id');
		}
		else
		{
			// $item->catid   = (int) $catid;
			// $item->created = $dateModified->format('Y-m-d H:i:s');

			$queryResult = $db->insertObject('#__idxproperties', $item, 'id');
		}

		/* echo 'Property array key from API results: ' . $key . PHP_EOL;
		echo 'Property listing ID: ' . $item->listingID . PHP_EOL;
		echo 'Database entry modified on: ' . $item->modified . ' (UTC)' . PHP_EOL;
		echo 'Update/insert status: ' . PHP_EOL;
		var_export($queryResult);
		echo PHP_EOL . PHP_EOL; */
	}

	// throw new Exception('…is exception handling even freaking working!?');

	$db->transactionCommit();

	// echo 'IDX processing completed' . PHP_EOL;
}
catch(Exception $e)
{
	echo $e->getCode() . ': ' . $e->getMessage() . PHP_EOL;

	$db->transactionRollback();
}

// echo count($properties) . ' properties were found and processed.' . PHP_EOL;
