<?php

defined('_JEXEC') or die('Restricted access');

?>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][payment_method]">
			<?php echo JText::_( 'LUNAR_PAYMENT_METHOD' ); ?>
		</label>
	</td>
	<td>
	<?php 
		echo JHTML::_('hikaselect.radiolist',  
			[
				JHTML::_('select.option', 'card', 'Card' ),
				JHTML::_('select.option', 'mobilePay', 'MobilePay' ),
			],
			"data[payment][payment_params][payment_method]", '', 'value', 'text',
			@$this->element->payment_params->payment_method
		);
		?>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][app_key]">
			<?php echo JText::_( 'LUNAR_APP_KEY' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][app_key]"
				value="<?php echo $this->escape(@$this->element->payment_params->app_key); ?>" />
		<span style="float:right;">Get it from your Lunar dashboard</span>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][public_key]">
			<?php echo JText::_( 'LUNAR_PUBLIC_KEY' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][public_key]"
				value="<?php echo $this->escape(@$this->element->payment_params->public_key); ?>" />
		<span style="float:right;">Get it from your Lunar dashboard</span>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][configuration_id]">
			<?php echo JText::_( 'LUNAR_CONFIGURATION_ID' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][configuration_id]" id="lunar_configuration_id"
				value="<?php echo $this->escape(@$this->element->payment_params->configuration_id); ?>" />
		<span style="float:right;">Email onlinepayments@lunar.app to get it</span>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][logo_url]">
			<?php echo JText::_( 'LUNAR_LOGO_URL' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][logo_url]" 
				value="<?php echo $this->escape(@$this->element->payment_params->logo_url); ?>" />
		<span style="float:right;">The logo used to the Lunar hosted checkout page</span>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][shop_title]">
			<?php echo JText::_( 'LUNAR_SHOP_TITLE' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][shop_title]" 
				value="<?php 
						echo $this->escape(
							@$this->element->payment_params->shop_title
							? $this->element->payment_params->shop_title
							: \Joomla\CMS\Factory::getApplication()->get('sitename')
						);
					?>" />
		<span style="float:right;">The title used to the Lunar hosted checkout page</span>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][capture_mode]">
			<?php echo JText::_('LUNAR_CAPTURE_MODE'); ?>
		</label>
	</td>
	<td>
		<?php 
			echo JHTML::_('hikaselect.radiolist',  
				[
					JHTML::_('select.option', 'delayed', 'Delayed' ),
					JHTML::_('select.option', 'instant', 'Instant' ),
				],
				"data[payment][payment_params][capture_mode]", '', 'value', 'text',
				@$this->element->payment_params->capture_mode
			);
		?>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][order_status]">
			<?php echo JText::_('LUNAR_PENDING_STATUS'); ?>
		</label>
	</td>
	<td>
		<?php 
			echo $this->data['order_statuses']->display(
				"data[payment][payment_params][order_status]", 
				@$this->element->payment_params->order_status
			); 
		?>
	</td>
</tr>

<tr>
	<td class="key">
		<label for="data[payment][payment_params][confirmed_status]">
			<?php echo JText::_('LUNAR_CONFIRMED_STATUS'); ?>
		</label>
	</td>
	<td>
		<?php 
			echo $this->data['order_statuses']->display(
				"data[payment][payment_params][confirmed_status]", 
				@$this->element->payment_params->confirmed_status
			); 
		?>
	</td>
</tr>
