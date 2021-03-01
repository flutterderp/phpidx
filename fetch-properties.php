<?php
defined('_JEXEC') or die;
setlocale(LC_MONETARY, 'en_US');

require_once('/path/to/phpidx.php');

$canFormat = class_exists('NumberFormatter');

if($canFormat === true)
{
	$money_format = new \NumberFormatter('en_US', NumberFormatter::CURRENCY);
	// $money_format->setFormat('¤#,##0.#0'); // not sure where I pulled this method from…
}

$abbrevs    = json_decode(file_get_contents(__DIR__ . '/state-abbreviations.json'), true);
$idx        = new PhpIdx('', '');
$items      = $idx->activeProperties('featured', 10);
$counter    = 0;
$prop_types = array(/*'Multi-Family', 'Multifamily Residential',*/ 'Residential', 'Single Family Residential');

// echo '<pre>'; print_r($items['data']); echo '</pre>';
// return false;
// echo '<pre>'; print_r($idx->listmethods()); echo '</pre>';

if($items['data'] && !empty($items['data'])) : ?>
	<div class="grid-container">
		<div class="grid-x grid-padding-x small-up-1 medium-up-2 large-up-4">
			<?php /* shuffle($items); */ ?>

			<?php foreach($items['data'] as $key => $property) : ?>
				<?php
				// $counter++;

				// echo '<pre>'; print_r($property); echo '</pre>';
				// break;

				/* if(!in_array($property['idxPropType'], $prop_types))
				{
					$counter--;
					continue;
				}

				if($counter > 4)
				{
					break;
				} */

				$images     = array();
				$state_name = preg_replace('/\s+/', '_', $property['state']);

				preg_match('/(land)/i', $property['idxPropType'], $typeMatches);

				// Prices are strings for some reason…
				$property['listingPrice'] = preg_replace('/(\D+)/', '', $property['listingPrice']);
				?>
				<div class="cell text-center">
					<a href="<?php echo $property['fullDetailsURL']; ?>" target="_blank">
						<img src="<?php echo $property['image'][0]['url']; ?>" width="100%" alt="" class="img-responsive">

						<?php if($canFormat) : ?>
							<div class="cost"><?php echo $money_format->formatCurrency($property['listingPrice'], 'USD'); ?></div>
							<!-- <div class="cost"><?php echo $money_format->formatCurrency($property['originalListPrice'], 'USD'); ?></div> -->
						<?php else : ?>
							<div class="cost"><?php echo money_format('%.0n', $property['listingPrice']); ?></div>
							<!-- <div class="cost"><?php echo money_format('%.0n', $property['advanced']['originalListPrice']); ?></div> -->
						<?php endif; ?>

						<?php echo $property['streetNumber'] . ' ' . $property['streetName']; ?><br>
						<?php echo $property['cityName'] . ', ' . $abbrevs[$state_name]; ?><br>

						<?php if(empty($typeMatches)) : ?>
							<?php echo $property['bedrooms'] . ' rooms | ' . $property['totalBaths'] . ' bath'; ?><br>
						<?php endif; ?>

						<?php echo (empty($typeMatches) ? $property['sqFt'] . ' sq ft | ' : '') . $property['acres']. ' acres'; ?>

						<?php if($property['propStatus'] == 'Under Contract-Show') : ?>
							<div class="uc">Under Contract</div>
						<?php endif; ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
