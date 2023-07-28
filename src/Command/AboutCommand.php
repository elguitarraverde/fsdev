<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use FacturaScripts\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AboutCommand extends Command
{

    protected static $defaultName = 'about';
    protected static $defaultDescription = 'Devuelve información sobre la instalación de FacturaScripts';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [
            ['<info>FacturaScripts</>'],
            new TableSeparator(),
            ['Version', Kernel::version()],
            ['End of maintenance', '<error>Expired</>'],
            ['End of life', '<error>Expired</>'],
            new TableSeparator(),
            ['<info>Kernel</>'],
            new TableSeparator(),
//            ['Debug', FS_DEBUG ? 'Activado' : 'Desactivado'],
            ['Cache directory', '/MyFiles/Tmp'],
            new TableSeparator(),
            ['<info>PHP</>'],
            new TableSeparator(),
            ['Version', \PHP_VERSION],
            ['Architecture', (\PHP_INT_SIZE * 8).' bits'],
            ['Intl locale', class_exists(\Locale::class, false) && \Locale::getDefault() ? \Locale::getDefault() : 'n/a'],
            ['Timezone', date_default_timezone_get().' (<comment>'.(new \DateTime())->format(\DateTime::W3C).'</>)'],
            ['OPcache', \extension_loaded('Zend OPcache') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOL) ? 'true' : 'false'],
            ['APCu', \extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOL) ? 'true' : 'false'],
            ['Xdebug', \extension_loaded('xdebug') ? 'true' : 'false'],
        ];

        $io->table([], $rows);

        return Command::SUCCESS;
    }
}
