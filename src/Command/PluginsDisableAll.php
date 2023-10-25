<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Plugins;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginsDisableAll extends Command
{
    protected static $defaultName = 'plugins:disable';
    protected static $defaultDescription = 'Desactiva TODOS los Plugins instalados.';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        require_once FS_FOLDER . DIRECTORY_SEPARATOR . 'config.php';

        $io = new SymfonyStyle($input, $output);

        (new DataBase())->connect();

        $plugins = Plugins::list();

        foreach ($plugins as $plugin){
            if(true === $plugin->enabled){
                Plugins::disable($plugin->name);
            }
        }

        $io->success('Plugins correctamente desactivados.');

        return Command::SUCCESS;
    }
}
