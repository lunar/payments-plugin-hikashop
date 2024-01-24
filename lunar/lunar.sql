
CREATE TABLE IF NOT EXISTS `#__lunar_transactions` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` int(11) UNSIGNED NOT NULL,
  `order_number` char(64) DEFAULT NULL,
  `paymentmethod_id` mediumint(2) UNSIGNED NOT NULL,
  `payment_name` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(15,5) NOT NULL DEFAULT '0.00000',
  `currency_code` varchar(225) NOT NULL,
  `status` varchar(225) NOT NULL DEFAULT 'created',
  `created_on` datetime NOT NULL DEFAULT now(),
  `created_by` int(11) NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT now(),
  `modified_by` int(11) NOT NULL DEFAULT '0',
  `locked_on` datetime NOT NULL DEFAULT now(),
  `locked_by` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
