<?php declare(strict_types=1);


namespace Elguitarraverde\Fsdev\Command;


use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AppReconstructCommand extends Command
{
    protected static $defaultName = 'app:reconstruir';
    protected static $defaultDescription = 'Borra y reconstruye el directorio Dinamic y borra la Caché.';

    protected function configure(): void
    {
        $this->addOption(
            'configuracion',
            '-c',
            InputOption::VALUE_OPTIONAL,
            'Archivo de configuración',
            'config.php'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        require_once $input->getOption('configuracion');

        // Borramos el directorio Dinamic
        $directorioDinamic = FS_FOLDER . DIRECTORY_SEPARATOR . 'Dinamic';
        if(is_dir($directorioDinamic)){
            Tools::folderDelete($directorioDinamic);
        }

        // Borramos la caché
        Cache::clear();

        // Reconstruimos el directorio Dinamic
        Plugins::deploy();

        $io->info('Se ha reconstruido el Dinamic y borrada la Caché correctamente.');

        return Command::SUCCESS;
    }
}


