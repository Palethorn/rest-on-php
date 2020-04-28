<?php
namespace RestOnPhp\Command;

use RestOnPhp\Utils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ClearCacheCommand extends Command {
    protected static $defaultName = 'api:clear-cache';
    private $cache_dir;

    protected function configure() {
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln("\e[32mClearing cache\e[0m");
        Utils::scandir($this->cache_dir, function($dir, $file) use($output) {
            $output->writeln(sprintf("\e[31m-\e[0m\e[90m%s\e[0m", $dir . '/' . $file));

            if(is_dir($dir . '/' . $file)) {
                rmdir($dir . '/' . $file);
            } else {
                @unlink($dir . '/' . $file);
            }
        }, true);

        return 0;
    }

    public function setCacheDir(string $cache_dir) {
        $this->cache_dir = $cache_dir;
    }
}
