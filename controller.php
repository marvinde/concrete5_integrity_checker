<?php
namespace Concrete\Package\Concrete5IntegrityChecker;

use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Package\Package;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package
{

    /**
     * The package handle.
     *
     * @var string
     */
    protected $pkgHandle = 'concrete5_integrity_checker';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$appVersionRequired
     */
    protected $appVersionRequired = '8.0.0';

    /**
     * The package version.
     *
     * @var string
     */
    protected $pkgVersion = '1.0.0';

    /**
     * Map folders to PHP namespaces, for automatic class autoloading.
     *
     * @var array
     */
    protected $pkgAutoloaderRegistries = [
        'src' => 'Concrete5IntegrityChecker',
    ];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageName()
     */
    public function getPackageName()
    {
        return t('Concrete5 Integrity Checker');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageDescription()
     */
    public function getPackageDescription()
    {
        return t('This package allows to check if core files have been modified and list them.');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        parent::install();
        $contentImporter = $this->app->make(ContentImporter::class);
        $contentImporter->importContentFile($this->getPackagePath() . '/config/install.xml');
    }
}
