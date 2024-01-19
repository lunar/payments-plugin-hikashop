CREATE TABLE `#__hikashop_payment_plg_lunar` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` int(1) UNSIGNED DEFAULT NULL,
  `order_number` char(64) DEFAULT NULL,
  `paymentmethod_id` mediumint(1) UNSIGNED DEFAULT NULL,
  `payment_name` varchar(5000) DEFAULT NULL,
  `transaction_id` varchar(29) DEFAULT NULL,
  `amount` decimal(15,5) NOT NULL DEFAULT '0.00000',
  `currency_code` varchar(225) DEFAULT NULL,
  `status` varchar(225) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT now(),
  `created_by` int(11) NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT now(),
  `modified_by` int(11) NOT NULL DEFAULT '0',
  `locked_on` datetime NOT NULL DEFAULT now(),
  `locked_by` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `#__hikashop_payment_plg_lunar` ADD PRIMARY KEY (`id`);

ALTER TABLE `#__hikashop_payment_plg_lunar` MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
