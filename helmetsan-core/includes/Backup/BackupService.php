<?php

declare(strict_types=1);

namespace Helmetsan\Core\Backup;

use Helmetsan\Core\Media\CloudflareR2Service;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Support\Logger;
use ZipArchive;

class BackupService
{
    private Config $config;
    private Logger $logger;
    private CloudflareR2Service $r2Service;

    public function __construct(Config $config, Logger $logger, CloudflareR2Service $r2Service)
    {
        $this->config    = $config;
        $this->logger    = $logger;
        $this->r2Service = $r2Service;
    }

    /**
     * Executes the off-site backup to R2.
     * @return array<string,mixed> Status result
     */
    public function runBackup(): array
    {
        if (! $this->r2Service->isEnabled()) {
            return ['ok' => false, 'message' => 'Cloudflare R2 is not configured or enabled.'];
        }

        $uploadDir = wp_upload_dir();
        $backupDir = $uploadDir['basedir'] . '/helmetsan-backups';
        
        if (! is_dir($backupDir)) {
            if (! wp_mkdir_p($backupDir)) {
                return ['ok' => false, 'message' => 'Could not create backup directory ' . $backupDir];
            }
            // Protect backup directory
            file_put_contents($backupDir . '/.htaccess', 'Deny from all');
            file_put_contents($backupDir . '/index.php', '<?php // Silence is golden');
        }

        $timestamp = date('Y-m-d_H-i-s');
        $dbFile    = $backupDir . '/db_backup_' . $timestamp . '.sql';
        $themeFile = $backupDir . '/theme_backup_' . $timestamp . '.zip';
        $dataFile  = $backupDir . '/data_backup_' . $timestamp . '.zip';

        $results = [];

        // 1. Dump Database
        try {
            $this->dumpDatabase($dbFile);
            $r2KeyDb = 'backups/db/db_backup_' . $timestamp . '.sql.gz';
            $dbFileGz = $dbFile . '.gz';
            if (file_exists($dbFileGz)) {
                $uploadResult = $this->r2Service->uploadFile($dbFileGz, $r2KeyDb, 'application/gzip');
                if (is_wp_error($uploadResult)) {
                    $results['db'] = 'R2 Upload Failed: ' . $uploadResult->get_error_message();
                } else {
                    $results['db'] = 'Success: Database uploaded.';
                }
                unlink($dbFileGz);
            } else {
                $results['db'] = 'Failed to generate database dump.';
            }
        } catch (\Exception $e) {
            $results['db'] = 'Error: ' . $e->getMessage();
        }

        // 2. Zip Theme Files
        try {
            if ($this->zipDirectory(WP_CONTENT_DIR . '/themes/', $themeFile)) {
                $r2KeyTheme = 'backups/themes/theme_backup_' . $timestamp . '.zip';
                $uploadResult = $this->r2Service->uploadFile($themeFile, $r2KeyTheme, 'application/zip');
                if (is_wp_error($uploadResult)) {
                    $results['theme'] = 'R2 Upload Failed: ' . $uploadResult->get_error_message();
                } else {
                    $results['theme'] = 'Success: Themes uploaded.';
                }
                unlink($themeFile);
            } else {
                $results['theme'] = 'Failed to create theme backup zip.';
            }
        } catch (\Exception $e) {
            $results['theme'] = 'Error: ' . $e->getMessage();
        }

        // 3. Zip Data Directory (Source JSON catalogs)
        $dataRoot = $this->config->dataRoot();
        if (is_dir($dataRoot)) {
            try {
                if ($this->zipDirectory($dataRoot, $dataFile)) {
                    $r2KeyData = 'backups/data/data_backup_' . $timestamp . '.zip';
                    $uploadResult = $this->r2Service->uploadFile($dataFile, $r2KeyData, 'application/zip');
                    if (is_wp_error($uploadResult)) {
                        $results['data'] = 'R2 Upload Failed: ' . $uploadResult->get_error_message();
                    } else {
                        $results['data'] = 'Success: Data uploaded.';
                    }
                    unlink($dataFile);
                } else {
                    $results['data'] = 'Failed to create data backup zip.';
                }
            } catch (\Exception $e) {
                $results['data'] = 'Error: ' . $e->getMessage();
            }
        }

        return [
            'ok' => true,
            'message' => 'Backup routine completed',
            'details' => $results,
        ];
    }

    /**
     * Dumps the WordPress database using mysqldump via shell_exec and gzips it.
     */
    private function dumpDatabase(string $outputFile): void
    {
        $user = escapeshellarg(DB_USER);
        $pass = escapeshellarg(DB_PASSWORD);
        $name = escapeshellarg(DB_NAME);
        $host = escapeshellarg(DB_HOST);
        $out  = escapeshellarg($outputFile);

        $command = "mysqldump --user={$user} --password={$pass} --host={$host} {$name} > {$out} && gzip -f {$out}";
        
        // Execute the command
        shell_exec($command);
    }

    /**
     * Zips a given directory recursively.
     */
    private function zipDirectory(string $sourcePath, string $outZipPath): bool
    {
        if (! extension_loaded('zip') || ! file_exists($sourcePath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($outZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sourcePath = realpath($sourcePath);
        if ($sourcePath === false) {
            return false;
        }

        if (is_dir($sourcePath)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourcePath, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($sourcePath) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        } elseif (is_file($sourcePath)) {
            $zip->addFile($sourcePath, basename($sourcePath));
        }

        $zip->close();
        return true;
    }
}
