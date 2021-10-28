<?php
/* echo '🐟';
return false; */
ini_set('display_errors', 'on');
setlocale(LC_MONETARY, 'en_US');

define('_JEXEC', 1);
define('JPATH_BASE', $_SERVER['DOCUMENT_ROOT'] . '' );
define('DS', DIRECTORY_SEPARATOR);

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\Registry\Registry;

$canFormat = class_exists('NumberFormatter');

if($canFormat === true)
{
	$money_format = new \NumberFormatter('en_US', NumberFormatter::CURRENCY);
}

require_once(JPATH_BASE . '/includes/defines.php');
require_once(JPATH_BASE . '/includes/framework.php');
require_once(__DIR__ . '/phpidx.php');

$app           = Factory::getApplication('site');
$app->initialise();
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

	if(isset($items['data']) && !empty($items['data']))
	{
		$properties = array_merge($properties, $items['data']);
	}
}

if(empty($properties))
{
	// var_export($idx);

	return false;
}

$db    = Factory::getDbo();
$query = $db->getQuery(true);

try
{
	$db->transactionStart();

	foreach($properties as $key => $property)
	{
		$catid = 0;

		$query->select('id')->from($db->qn('#__idxproperties'))->where('internalID = ' . $db->q($property['internalID']))->setLimit(1);

		$db->setQuery($query);

		$existing = (int) $db->loadResult();

		/* try
		{

		}
		catch(Exception $e)
		{
			$existing = 0;
		} */

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

		if(isset($property['image']['totalCount']) && $property['image']['totalCount'] > 0)
		{
			for($i = 0; $i < $property['image']['totalCount']; $i++)
			{
				$idxPhotos[$i]['url']     = $property['image'][$i]['url'];
				$idxPhotos[$i]['caption'] = $property['image'][$i]['caption'];
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

		$possibleUse = new Registry($property['advanced']['possibleUse']);
		$possibleUse = $possibleUse->toString();

		$roadFrontageType = new Registry($property['advanced']['roadFrontageType']);
		$roadFrontageType = $roadFrontageType->toString();

		$roadResponsibility = new Registry($property['advanced']['roadResponsibility']);
		$roadResponsibility = $roadResponsibility->toString();

		$specialListingConditions = new Registry($property['advanced']['specialListingConditions']);
		$specialListingConditions = $specialListingConditions->toString();

		$structureType = new Registry($property['advanced']['structureType']);
		$structureType = $structureType->toString();

		$utilities = new Registry($property['advanced']['utilities']);
		$utilities = $utilities->toString();

		$listingContractDate = new DateTime($property['advanced']['listingContractDate'], $utc_tz);
		$dateModified        = new DateTime($property['dateModified'], $utc_tz);


		$appliances              = new Registry($property['advanced']['appliances']);
		$appliances              = $appliances->toString();

		$interiorFeatures        = new Registry($property['advanced']['interiorFeatures']);
		$interiorFeatures        = $interiorFeatures->toString();

		$fireplaceFeatures       = new Registry($property['advanced']['fireplaceFeatures']);
		$fireplaceFeatures       = $fireplaceFeatures->toString();

		$constructionMaterials   = new Registry($property['advanced']['constructionMaterials']);
		$constructionMaterials   = $constructionMaterials->toString();

		$flooring                = new Registry($property['advanced']['flooring']);
		$flooring                = $flooring->toString();

		$foundationDetails       = new Registry($property['advanced']['foundationDetails']);
		$foundationDetails       = $foundationDetails->toString();

		$heating                 = new Registry($property['advanced']['heating']);
		$heating                 = $heating->toString();

		$laundryFeatures         = new Registry($property['advanced']['laundryFeatures']);
		$laundryFeatures         = $laundryFeatures->toString();

		$lotFeatures             = new Registry($property['advanced']['lotFeatures']);
		$lotFeatures             = $lotFeatures->toString();

		$parkingFeatures         = new Registry($property['advanced']['parkingFeatures']);
		$parkingFeatures         = $parkingFeatures->toString();

		$roadSurfaceType         = new Registry($property['advanced']['roadSurfaceType']);
		$roadSurfaceType         = $roadSurfaceType->toString();

		$roof                    = new Registry($property['advanced']['roof']);
		$roof                    = $roof->toString();

		$sewer                   = new Registry($property['advanced']['sewer']);
		$sewer                   = $sewer->toString();

		$communityFeatures       = new Registry($property['advanced']['communityFeatures']);
		$communityFeatures       = $communityFeatures->toString();

		$waterSource             = new Registry($property['advanced']['waterSource']);
		$waterSource             = $waterSource->toString();

		$associationFeeFrequency = new Registry($property['advanced']['associationFeeFrequency']);
		$associationFeeFrequency = $associationFeeFrequency->toString();

		$ownershipType           = new Registry($property['advanced']['ownershipType']);
		$ownershipType           = $ownershipType->toString();

		// Update item details
		$item                                      = new stdClass();
		$item->id                                  = ($existing > 0) ? (int) $existing : null;
		$item->title                               = $property['address'] . ' (' . $property['listingID'] . ')';
		$item->alias                               = OutputFilter::stringUrlSafe($item->title);
		$item->acres                               = $property['acres'];
		$item->bedrooms                            = $property['bedrooms'];
		$item->addressString                       = $property['address'];
		$item->buildingAreaTotal                   = (float) $property['advanced']['buildingAreaTotal'];
		$item->carBuyerAgentSaleYN                 = $property['advanced']['carBuyerAgentSaleYN'];
		$item->carCeilingHeightFt                  = (float) $property['advanced']['carCeilingHeightFt'];
		$item->carCeilingHeightIn                  = (float) $property['advanced']['carCeilingHeightIn'];
		$item->carDeedReference                    = $property['advanced']['carDeedReference'];
		$item->carDocuments                        = $property['advanced']['carDocuments'];
		$item->carHoaSubjectTo                     = $property['advanced']['carHoaSubjectTo'];
		$item->carInsideCityYN                     = $property['advanced']['carInsideCityYN'];
		$item->carNumberOfDriveInDoorsTotal        = (int) $property['advanced']['carNumberOfDriveInDoorsTotal'];
		$item->carOwnerAgentYN                     = $property['advanced']['carOwnerAgentYN'];
		$item->carPermitSyndicationYN              = $property['advanced']['carPermitSyndicationYN'];
		$item->carPropertySubTypeSecondary         = $property['advanced']['carPropertySubTypeSecondary'];
		$item->carRValueCeiling                    = (float) $property['advanced']['carRValueCeiling'];
		$item->carRestrictions                     = $property['advanced']['carRestrictions'];
		$item->carRestrictionsDescription          = $property['advanced']['carRestrictionsDescription'];
		$item->carRoadFrontage                     = (float) $property['advanced']['carRoadFrontage'];
		$item->carSqFtAvailableMaximum             = (float) $property['advanced']['carSqFtAvailableMaximum'];
		$item->carSqFtAvailableMinimum             = (float) $property['advanced']['carSqFtAvailableMinimum'];
		$item->carSqFtMaximumLease                 = (float) $property['advanced']['carSqFtMaximumLease'];
		$item->carSqFtMinimumLease                 = (float) $property['advanced']['carSqFtMinimumLease'];
		$item->carSqFtMain                         = (float) $property['advanced']['carSqFtMain'];
		$item->carSqFtUnheatedTotal                = (float) $property['advanced']['carSqFtUnheatedTotal'];
		$item->carSqFtUpper                        = (float) $property['advanced']['carSqFtUpper'];
		$item->carTable                            = $property['advanced']['carTable'];
		$item->carTransactionType                  = $property['advanced']['carTransactionType'];
		$item->carUnitCount                        = (int) $property['advanced']['carUnitCount'];
		$item->cumulativeDaysOnMarket              = (int) $property['advanced']['cumulativeDaysOnMarket'];
		$item->daysOnMarket                        = (int) $property['advanced']['daysOnMarket'];
		$item->inclusions                          = $property['advanced']['inclusions'];
		$item->listAgentDirectPhone                = $property['advanced']['listAgentDirectPhone'];
		$item->listAgentFullName                   = $property['advanced']['listAgentFullName'];
		$item->listAgentMlsId                      = $property['advanced']['listAgentMlsId'];
		$item->listOfficeMlsId                     = $property['advanced']['listOfficeMlsId'];
		$item->listOfficePhone                     = $property['advanced']['listOfficePhone'];
		$item->listingContractDate                 = $listingContractDate->format('Y-m-d H:i:s');
		$item->listingOfficeName                   = $property['advanced']['listingOfficeName'];
		$item->lotSizeDimensions                   = $property['advanced']['lotSizeDimensions'];
		$item->newConstructionYN                   = $property['advanced']['newConstructionYN'];
		$item->numberOfUnitsLeased                 = (int) $property['advanced']['numberOfUnitsLeased'];
		$item->possibleUse                         = $possibleUse;
		$item->roadFrontageType                    = $roadFrontageType;
		$item->roadResponsibility                  = $roadResponsibility;
		$item->specialListingConditions            = $specialListingConditions;
		$item->structureType                       = $structureType;
		$item->syndicationRemarks                  = $property['advanced']['syndicationRemarks'];
		$item->utilities                           = $utilities;
		$item->zoningDescription                   = $property['advanced']['zoningDescription'];
		$item->cityName                            = $property['cityName'];
		$item->countyName                          = $property['countyName'];
		$item->dateModified                        = $dateModified->format('Y-m-d H:i:s');
		$item->displayAddress                      = $property['displayAddress'];
		$item->idxFeatured                         = $property['featured'];
		$item->fullDetailsURL                      = $property['fullDetailsURL'];
		$item->idxID                               = $property['idxID'];
		$item->idxPropType                         = $property['idxPropType'];
		$item->idxStatus                           = $property['idxStatus'];
		$item->idxPhotos                           = $idxPhotos;
		$item->images                              = $images;
		$item->internalID                          = $property['internalID'];
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
		$item->sqFt                                = floatval(str_ireplace(',', '', $property['sqFt']));
		$item->addressState                        = $property['state'];
		$item->addressStateAbbr                    = $states[$property['state']];
		$item->streetName                          = $property['streetName'];
		$item->streetNumber                        = (int) $property['streetNumber'];
		$item->subdivision                         = $property['subdivision'];
		$item->totalBaths                          = (int) $property['totalBaths'];
		$item->fullBaths                           = (int) $property['fullBaths'];
		$item->yearBuilt                           = $property['yearBuilt'];
		$item->zipcode                             = $property['zipcode'];
		$item->appliances                          = $appliances;
		$item->associationFee                      = $property['advanced']['associationFee'];
		$item->associationFeeFrequency             = $associationFeeFrequency;
		$item->associationName                     = $property['advanced']['associationName'];
		$item->carComplexName                      = $property['advanced']['carComplexName'];
		$item->carConstructionType                 = $property['advanced']['carConstructionType'];
		$item->carLandIncludedYN                   = $property['advanced']['carLandIncludedYN'];
		$item->carHoaSubjectToDues                 = $property['advanced']['carHoaSubjectToDues'];
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
		$item->internetAutomatedValuationDisplayYN = $property['advanced']['internetAutomatedValuationDisplayYN'];
		$item->internetConsumerCommentYN           = $property['advanced']['internetConsumerCommentYN'];
		$item->laundryFeatures                     = $laundryFeatures;
		$item->lotFeatures                         = $lotFeatures;
		$item->ownershipType                       = $ownershipType;
		$item->parkingFeatures                     = $parkingFeatures;
		$item->priceBeforeReduction                = $property['advanced']['priceBeforeReduction'];
		$item->priceReductionDate                  = $property['advanced']['priceReductionDate'];
		$item->roadSurfaceType                     = $roadSurfaceType;
		$item->roof                                = $roof;
		$item->sewer                               = $sewer;
		$item->waterSource                         = $waterSource;
		$item->modified                            = $dateModified->format('Y-m-d H:i:s');
		$item->access                              = 1;
		$item->state                               = 1;
		$item->language                            = '*';
		// echo '<pre>'; print_r($property); echo '</pre>';

		if($existing > 0)
		{
			$db->updateObject('#__idxproperties', $item, 'id');
		}
		else
		{
			$item->catid   = (int) $catid;
			$item->created = $dateModified->format('Y-m-d H:i:s');

			$db->insertObject('#__idxproperties', $item, 'id');
		}

		/* try
		{

		}
		catch(Exception $e)
		{
			echo '<p>' . $e->getCode() . ': ' . $e->getMessage() . '</p>';
		} */
	}

	$db->transactionCommit();
}
catch(Exception $e)
{
	echo $e->getCode() . ': ' . $e->getMessage() . PHP_EOL;

	$db->transactionRollback();
}
