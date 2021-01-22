<?php
namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class HelloCommand
 *
 * @package Grav\Plugin\Console
 */
class CleanupCommand extends ConsoleCommand
{
    /**
     * @var array
     */
    protected $options = [];

    /**
     * Greets a person with or without yelling
     */
    protected function configure()
    {
        $this
            ->setName("cleanup")
            ->setDescription("Deletes all assets and data.json from page tree")
            ->setHelp('Deletes all assets and data.json from page tree')
            ->addOption(
                'json',
                'j',
                InputOption::VALUE_NONE,
                'delete data.json files only'
            )
            ->addOption(
                'assets',
                'a',
                InputOption::VALUE_NONE,
                'delete assets folders and its content only'
            )
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        // TODO: remove when requiring Grav 1.7+
        if (method_exists($this, 'initializeGrav')) {
            $this->initializeThemes();
        }

        $this->options = [
            'json' => $this->input->getOption('json'),
            'assets' => $this->input->getOption('assets')
        ];

        $grav = Grav::instance();
        $config = $grav['config']->get('plugins.directus');

        $path = getcwd() . '/user/pages';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            if($fileinfo->isDir() && $fileinfo->getFilename() === $config['assetsFolderName']) {
                if($this->options['assets'] || (!$this->options['assets'] && !$this->options['json'])) {
                    dump ('deleted: ' . $fileinfo->getRealPath());
                    $this->deleteRecursive($fileinfo->getRealPath());
                    rmdir($fileinfo->getRealPath());
                }
            } elseif ($fileinfo->isFile() && $fileinfo->getFilename() === 'data.json') {
                if($this->options['json'] || (!$this->options['assets'] && !$this->options['json'])) {
                    dump ('deleted: ' . $fileinfo->getRealPath());
                    unlink($fileinfo->getRealPath());
                }
            }
        }
    }

    private function deleteRecursive($dir) {
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it,
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }
}