<?php declare(strict_types=1);

namespace Elguitarraverde\Fsdev\Command;

use FacturaScripts\Core\Cache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheClearCommand extends Command
{

    protected static $defaultName = 'cache:clear';
    protected static $defaultDescription = 'Limpia la cache';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        Cache::clear();

        $io->success('Limpieza de la Cache realizada correctamente.');

        return Command::SUCCESS;
    }
}
