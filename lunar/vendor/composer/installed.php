<?php return array(
    'root' => array(
        'name' => 'lunar/payments-plugin-hikashop',
        'reference' => NULL,
        'type' => 'hikashop-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'lunar/payments-api-sdk' => array(
            'reference' => '36436f5181dbeb0fdb3bd77b6c040c7a7abcc366',
            'type' => 'library',
            'install_path' => __DIR__ . '/../lunar/payments-api-sdk',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'lunar/payments-plugin-hikashop' => array(
            'reference' => NULL,
            'type' => 'hikashop-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
