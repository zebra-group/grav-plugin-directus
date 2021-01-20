<?php
namespace Grav\Plugin\Console;

use Grav\Common\Grav;
use Grav\Console\ConsoleCommand;
use Grav\Plugin\DirectusPlugin;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class HelloCommand
 *
 * @package Grav\Plugin\Console
 */
class RefreshGlobalCommand extends ConsoleCommand
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
            ->setName("refresh-global")
            ->setDescription("Refresh all data.json files and sync with Directus Backend")
            ->setHelp('Refresh all data.json files and sync with Directus Backend')
        ;
    }

    /**
     * @return int|null|void
     */
    protected function serve()
    {
        $grav = Grav::instance();
        //$grav->fireEvent('onTwigInitialized');
        $grav->fireEvent('onPagesInitialized');
        $grav['pages']->init();
        //$pages = $grav['pages']->all();
        dd($grav['pages']);
        foreach ($pages as $page) {
            // Just output something for now
            $this->output->writeln( $page->rawRoute() );
        }
        // $this->output->writeln($grav['pages']->instances());


//        $directusPlugin = new DirectusPlugin('Directus Plugin', );
//
//        $greetings = 'Greetings, dear <cyan>test</cyan>!';


        // finally we write to the output the greetings
   //     $this->output->writeln($directusPlugin->refreshGlobalDataFiles());
    }
}