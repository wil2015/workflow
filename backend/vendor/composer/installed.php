<?php return array(
    'root' => array(
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'reference' => null,
        'name' => '__root__',
        'dev' => true,
    ),
    'versions' => array(
        '__root__' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'reference' => null,
            'dev_requirement' => false,
        ),
        'laminas/laminas-escaper' => array(
            'pretty_version' => '2.6.1',
            'version' => '2.6.1.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../laminas/laminas-escaper',
            'aliases' => array(),
            'reference' => '25f2a053eadfa92ddacb609dcbbc39362610da70',
            'dev_requirement' => false,
        ),
        'laminas/laminas-zendframework-bridge' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '*',
            ),
        ),
        'phpoffice/phpword' => array(
            'pretty_version' => '0.18.0',
            'version' => '0.18.0.0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../phpoffice/phpword',
            'aliases' => array(),
            'reference' => '1bd7cd62381051db6d6c7174d3c95a3ada48bc0f',
            'dev_requirement' => false,
        ),
        'zendframework/zend-escaper' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '2.6.1',
            ),
        ),
    ),
);
