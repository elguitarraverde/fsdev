<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Plugins;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PluginsEnableAll extends Command
{
    protected static $defaultName = 'plugins:enable';
    protected static $defaultDescription = 'Activa TODOS los Plugins instalados.';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        require_once FS_FOLDER . DIRECTORY_SEPARATOR . 'config.php';

        $pathMyFiles = FS_FOLDER . DIRECTORY_SEPARATOR . 'MyFiles';
        if (false === is_dir($pathMyFiles)) {
            mkdir($pathMyFiles);
        }

        $pathPluginsList = $pathMyFiles . DIRECTORY_SEPARATOR . 'plugins.json';
        if (false === is_file($pathPluginsList)) {
            touch($pathPluginsList);
        }
        
        $io = new SymfonyStyle($input, $output);

        (new DataBase())->connect();

        $plugins = Plugins::list();

        // Excluimos los plugins de temas y los que requieren de otros plugins de pago.
        $excludedPlugins = [
            'AdminLTE',
            'ArgonTheme',
            'Bootemas',
            'BracketTheme',
            'NeoTheme',
            'OldForms',
            'SBadmin',
        ];

        foreach ($plugins as $plugin){
            if(false === in_array($plugin->name, $excludedPlugins)){
                Plugins::enable($plugin->name);
            }
        }

        $io->success('Plugins correctamente activados.');

        return Command::SUCCESS;
    }
}
