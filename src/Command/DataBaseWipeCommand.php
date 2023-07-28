<?php declare(strict_types=1);


namespace Elguitarraverde\Fsdev\Command;


use FacturaScripts\Core\Base\DataBase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DataBaseWipeCommand extends Command
{
    protected static $defaultName = 'db:wipe';
    protected static $defaultDescription = 'Borra todas las tablas de la base de datos según el archivo pasado(config.php, config-mysql.php, config-postgresql.php, etc).';

    protected function configure(): void
    {
        $this->addOption('configuracion', '-c', InputOption::VALUE_REQUIRED, 'Archivo de configuración');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        require_once $input->getOption('configuracion');

        $database = new DataBase();
        $database->connect();
        $tables = $database->getTables();

        if (count($tables) > 0) {
            $result = $database->exec('DROP TABLE ' . implode(', ', $tables));
            if(true === $result){
                $io->success('Todas las tablas se han borrado correctamente.');
            }else{
                $io->success('Error al borrar las tablas.');
            }
        }else{
            $io->info('No existen tablas para borrar. Base de datos vacía.');
        }

        return Command::SUCCESS;
    }
}
