<?php
defined('_JEXEC') or die;
setlocale(LC_MONETARY, 'en_US');
require_once(JPATH_BASE . '/templates/hannush/includes/phpidx.php');

$idx				= new PhpIdx('', '');
$properties	= $idx->activeProperties('featured');
$counter		= 0;
$prop_types	= array(/*'Multi-Family', 'Multifamily Residential',*/ 'Residential', 'Single Family Residential');

// echo '<pre>'; print_r($idx->listmethods()); echo '</pre>';

if($properties && !empty($properties))
{
	?>
	<ul class="small-block-grid-1 medium-block-grid-2 large-block-grid-4 prop-showcase">
		<?php
		// shuffle($properties);
		
		foreach($properties as $key => $property)
		{
			$counter++;
			if(!in_array($property['idxPropType'], $prop_types))
			{
				$counter--;
				continue;
			}
			
			if($counter > 4)
				break;
			
			$images = array();
			
			// Prices are strings for some reason…
			$property['listingPrice']					= preg_replace('/(\D+)/', '', $property['listingPrice']);
			?>
			<li>
				<a href="<?php echo $property['fullDetailsURL']; ?>" target="_blank">
					<img src="<?php echo $property['image'][0]['url']; ?>" width="100%" alt="" class="img-responsive" />
					<?php if($property['propStatus'] == 'Under Contract-Show') : ?>
						<div class="uc">Under Contract</div>
					<?php endif; ?>
					
					<div class="cost"><?php echo money_format('%.0n', $property['listingPrice']); ?></div>
					<!-- <div class="cost"><?php echo money_format('%.0n', $property['currentPrice']); ?></div> -->
				</a>
			</li>
			<?php
		}
		?>
	</ul>
	<?php
}
else
{
	// Do stuff
}