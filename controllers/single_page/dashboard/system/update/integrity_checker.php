<?php
namespace Concrete\Package\Concrete5IntegrityChecker\Controller\SinglePage\Dashboard\System\Update;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Support\Facade\Route;
use Concrete5IntegrityChecker\DiffChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Zend\Http\Client\Adapter\Exception\TimeoutException;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

class IntegrityChecker extends DashboardPageController
{
    /**
     * @var array
     */
    protected $versions = [
        '8.5.0' => 'https://www.concrete5.org/download_file/-/view/109116/8497/',
        '8.4.5' => 'https://www.concrete5.org/download_file/-/view/108839/8497/',
        '8.4.4' => 'https://www.concrete5.org/download_file/-/view/108181/8497/',
        '8.4.3' => 'https://www.concrete5.org/download_file/-/view/107738/8497/',
        '8.4.2' => 'https://www.concrete5.org/download_file/-/view/105477/8497/',
        '8.4.1' => 'https://www.concrete5.org/download_file/-/view/105022/8497/',
        '8.4.0' => 'https://www.concrete5.org/download_file/-/view/104344/8497/',
        '8.3.2' => 'https://www.concrete5.org/download_file/-/view/100595/8497/',
        '8.3.1' => 'https://www.concrete5.org/download_file/-/view/99963/8497/',
        '8.3.0' => 'https://www.concrete5.org/download_file/-/view/99806/8497/',
        '8.2.1' => 'https://www.concrete5.org/download_file/-/view/96959/8497/',
        '8.2.0' => 'https://www.concrete5.org/download_file/-/view/96765/8497/',
        '8.1.0' => 'https://www.concrete5.org/download_file/-/view/93797/8497/',
        '8.0.3' => 'https://www.concrete5.org/download_file/-/view/93074/8497/',
        '8.0.2' => 'https://www.concrete5.org/download_file/-/view/92910/8497/',
        '8.0.1' => 'https://www.concrete5.org/download_file/-/view/92834/8497/',
        '8.0.0' => 'https://www.concrete5.org/download_file/-/view/92663/8497/',
    ];

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @var \Concrete\Core\Error\ErrorList\ErrorList
     */
    protected $errors;

    /**
     * @var string|null
     */
    protected $version;

    /**
     * @var string
     */
    protected $downloadsPath = DIR_PACKAGES . '/concrete5_integrity_checker/downloads/';

    /**
     * @var string|null
     */
    protected $archiveURL;

    public function on_start()
    {
        $this->config = $this->app->make('config');
        $this->version = $this->config->get('concrete.version');
        $this->archiveURL = $this->versions[$this->version];
        $this->errors = $this->app->make('error');
    }

    public function view()
    {
        Route::register(
            '/check_core_integrity',
            'Concrete\Package\Concrete5IntegrityChecker\Controller\SinglePage\Dashboard\System\Update\IntegrityChecker::check_core_integrity'
        );
        Route::register(
            '/get_file_diff',
            'Concrete\Package\Concrete5IntegrityChecker\Controller\SinglePage\Dashboard\System\Update\IntegrityChecker::get_file_diff'
        );
        $this->checks();
        $this->requireAsset('javascript', 'underscore');
        $this->set('errors', $this->errors);
        $this->set('version', $this->version);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function check_core_integrity()
    {
        try {
            $fileHelper = $this->app->make('helper/file');
            $basePath = $this->downloadsPath . 'concrete5-' . $this->version;
            $file = $basePath . '.zip';
            if (!file_exists($file)) {
                file_put_contents($file, file_get_contents($this->archiveURL));
            }
            $fileHelper->removeAll($basePath, true);
            $zip = $this->app->make('helper/zip');
            $zip->unzip($file, $this->downloadsPath);
            $modifiedFiles = [];
            $coreFiles = $fileHelper->getDirectoryContents($basePath . '/' . DIRNAME_CORE, [], true);
            foreach ($coreFiles as $file) {

                // TODO: vérifier si il y a une update
                $coreFile = str_replace($basePath . '/' . DIRNAME_CORE, DIR_BASE . '/' . DIRNAME_CORE, $file);
                $fileHash = hash_file('sha256', $file);
                $coreFileHash = hash_file('sha256', $coreFile);
                if ($coreFileHash !== $fileHash) {
                    $modifiedFiles[] = [
                        'path' => $file,
                        'corePath' => $coreFile,
                        'fileMd5' => $fileHash,
                        'coreFileHash' => $coreFileHash,
                    ];
                }
            }
            sort($modifiedFiles);
        } catch (Exception $e) {
            $this->errors->add($e->getMessage());
        }

        $message = null;
        if (count($modifiedFiles) === 0) {
            $message = t('There is no modified file.');
        }

        return new JsonResponse([
            'errors' => $this->errors->jsonSerialize(),
            'message' => $message,
            'nb_checked_files' => count($coreFiles),
            'nb_modified_files' => count($modifiedFiles),
            'modified_files' => $modifiedFiles,
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function get_file_diff()
    {
        $file = $this->post('file');
        $basePath = $this->downloadsPath . 'concrete5-' . $this->version;
        $coreFile = str_replace($basePath . '/' . DIRNAME_CORE, DIR_BASE . '/' . DIRNAME_CORE, $file);
        try {
            $diffObj = new DiffChecker($file, $coreFile);
            $diff = null;
            if (is_object($diffObj)) {
                $diff = $diffObj->output();
            }
        } catch (Exception $e) {
            $this->errors->add($e->getMessage());
        }

        return new JsonResponse([
            'errors' => $this->errors->jsonSerialize(),
            'file' => $file,
            'diff' => $diff,
        ]);
    }

    protected function checks()
    {
        if (empty($this->archiveURL)) {
            $this->errors->add(t('The archive could not be downloaded.'));
        } else {
            if (empty($this->getArchiveSize())) {
                $this->errors->add(t('The archive could not be downloaded.'));
            }
        }
        if (!is_writable($this->downloadsPath)) {
            $this->errors->add(t("The directory %s must be writable.", $this->downloadsPath));
        }
    }

    /**
     * @return string
     */
    protected function getArchiveSize()
    {
        $contentLength = '';
        if (!empty($this->archiveURL)) {
            $ch = curl_init($this->archiveURL);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $data = curl_exec($ch);
            curl_close($ch);

            if (preg_match('/Content-Length: (\d+)/', $data, $matches)) {
                $contentLength = (int) $matches[1];
            }
        }

        return $contentLength;
    }
}
